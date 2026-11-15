<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\Employee;
use App\Models\SyncVersion;
use Illuminate\Support\Facades\DB;

/**
 * Builds the GET /employees/changes response for one device.
 *
 * The cursor is the id of the last sync_versions row the device has seen.
 * Rows only say "this employee changed"; the op sent to the device is
 * derived from the employee's state *now* as seen by this device:
 *   - merged into another employee   -> merge
 *   - deleted, inactive, not allowed -> delete (tombstone)
 *   - otherwise                      -> upsert with current templates
 */
class ChangeFeed
{
    /**
     * @param  list<string>|null  $types  template types the device supports (null = all)
     * @return array{cursor: string, has_more: bool, changes: list<array<string, mixed>>}
     */
    public function since(Device $device, int $cursor, int $limit, ?array $types): array
    {
        $rows = SyncVersion::where('id', '>', $cursor)->orderBy('id')->limit($limit + 1)->get(['id', 'employee_id']);

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $next = $rows->isEmpty() ? $cursor : (int) $rows->last()->id;

        // One entry per employee, in order of its last change on this page.
        $employeeIds = $rows->reverse()->pluck('employee_id')->unique()->reverse()->values();

        $employees = Employee::withTrashed()
            ->whereIn('id', $employeeIds)
            ->with(['templates' => fn ($q) => $q->when($types, fn ($q) => $q->whereIn('type', $types))])
            ->get()
            ->keyBy('id');

        $assigned = DB::table('employee_device')
            ->where('device_id', $device->id)
            ->whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->flip();

        $changes = [];
        foreach ($employeeIds as $id) {
            $employee = $employees->get($id);
            if (! $employee) {
                continue; // row from another tenant cannot reach here (scoped), hard-deleted employee
            }

            $base = ['id' => $employee->id, 'name' => $employee->name, 'code' => $employee->code];

            if ($employee->merged_into_id !== null) {
                $changes[] = ['op' => 'merge', 'employee' => $base, 'templates' => [], 'merged_into' => $employee->merged_into_id];

                continue;
            }

            $allowed = $employee->all_devices || $assigned->has($employee->id);

            if ($employee->trashed() || $employee->status !== Employee::STATUS_ACTIVE || ! $allowed) {
                $changes[] = ['op' => 'delete', 'employee' => $base, 'templates' => [], 'merged_into' => null];

                continue;
            }

            $changes[] = [
                'op' => 'upsert',
                'employee' => $base,
                'templates' => $employee->templates->map->toDevicePayload()->values()->all(),
                'merged_into' => null,
            ];
        }

        return ['cursor' => (string) $next, 'has_more' => $hasMore, 'changes' => $changes];
    }
}
