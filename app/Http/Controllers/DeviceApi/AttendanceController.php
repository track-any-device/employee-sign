<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\Device;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    public function __invoke(Request $request, Device $device): JsonResponse
    {
        $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:'.config('devices.attendance_max_batch')],
            'events.*' => ['array'],
        ]);

        $accepted = $duplicates = $rejected = $valid = [];

        foreach ($request->input('events') as $i => $event) {
            $v = Validator::make($event, [
                'device_event_id' => ['required', 'integer', 'min:0'],
                'occurred_at' => ['nullable', 'date'],
                'uptime_s' => ['nullable', 'integer', 'min:0'],
                'employee_id' => ['nullable', 'string', 'max:64'],
                'employee_local_id' => ['nullable'],
                'method' => ['required', 'in:face,fingerprint'],
                'similarity' => ['nullable', 'numeric', 'between:0,1'],
            ]);

            $id = $event['device_event_id'] ?? null;
            if ($v->fails()) {
                $rejected[] = ['id' => $id, 'reason' => 'invalid: '.implode(', ', array_keys($v->errors()->toArray()))];

                continue;
            }
            if (isset($valid[(int) $id])) {
                $duplicates[] = (int) $id; // repeated inside the same batch

                continue;
            }
            $valid[(int) $id] = $event;
        }

        $existing = AttendanceEvent::where('device_id', $device->id)
            ->whereIn('device_event_id', array_keys($valid))
            ->pluck('device_event_id')->map(fn ($v) => (int) $v)->flip();

        $employeeIds = $this->resolveEmployees(array_filter(array_column($valid, 'employee_id')));

        $rows = [];
        $now = now();
        foreach ($valid as $id => $event) {
            if ($existing->has($id)) {
                $duplicates[] = $id;

                continue;
            }
            $rows[] = [
                'tenant_id' => $device->tenant_id,
                'device_id' => $device->id,
                // Unknown ids are kept in raw, so nothing recorded on the device is lost.
                'employee_id' => isset($event['employee_id']) ? ($employeeIds[$event['employee_id']] ?? null) : null,
                'device_event_id' => $id,
                'occurred_at' => isset($event['occurred_at']) ? Carbon::parse($event['occurred_at'])->utc() : null,
                'device_uptime_s' => $event['uptime_s'] ?? null,
                'method' => $event['method'],
                'similarity' => $event['similarity'] ?? null,
                'raw' => json_encode($event),
                'received_at' => $now,
            ];
            $accepted[] = $id;
        }

        // insertOrIgnore keeps a concurrent re-upload idempotent too.
        foreach (array_chunk($rows, 250) as $chunk) {
            AttendanceEvent::insertOrIgnore($chunk);
        }

        return response()->json(compact('accepted', 'duplicates', 'rejected'));
    }

    /**
     * Map device-sent employee ids to current employees, following merges.
     *
     * @param  array<int, string>  $ids
     * @return array<string, string>
     */
    private function resolveEmployees(array $ids): array
    {
        $map = [];
        $employees = Employee::withTrashed()->whereIn('id', array_unique($ids))->get(['id', 'merged_into_id', 'deleted_at'])->keyBy('id');

        foreach (array_unique($ids) as $id) {
            $e = $employees->get($id);
            for ($hops = 0; $e && $e->merged_into_id && $hops < 5; $hops++) {
                $e = Employee::withTrashed()->find($e->merged_into_id);
            }
            if ($e) {
                $map[$id] = $e->id;
            }
        }

        return $map;
    }
}
