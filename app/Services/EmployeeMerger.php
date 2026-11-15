<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\Devices\EmployeeSync;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmployeeMerger
{
    public const MERGEABLE_FIELDS = ['name', 'code', 'status'];

    public function __construct(private EmployeeSync $sync) {}

    /**
     * Merge $other into $survivor. $takeFromOther lists the fields whose value
     * should come from $other. Templates, device assignments and attendance
     * move to the survivor; $other is soft-deleted and devices receive
     * "merge: other -> survivor".
     *
     * @param  list<string>  $takeFromOther
     */
    public function merge(Employee $survivor, Employee $other, array $takeFromOther = []): Employee
    {
        if ($survivor->is($other)) {
            throw new InvalidArgumentException('Cannot merge an employee into itself.');
        }

        return DB::transaction(function () use ($survivor, $other, $takeFromOther) {
            $devicesBefore = array_values(array_unique([
                ...$this->sync->deviceIdsFor($survivor),
                ...$this->sync->deviceIdsFor($other),
            ]));

            $values = [];
            foreach (array_intersect($takeFromOther, self::MERGEABLE_FIELDS) as $field) {
                $values[$field] = $other->{$field};
            }

            // Free the code before it moves to the survivor (unique per tenant).
            $otherCode = $other->code;
            $other->forceFill(['code' => null, 'merged_into_id' => $survivor->id])->save();

            if (array_key_exists('code', $values)) {
                $values['code'] = $otherCode;
            }
            $survivor->fill($values);
            $survivor->all_devices = $survivor->all_devices || $other->all_devices;
            $survivor->save();

            $existing = $survivor->templates()->pluck('sha256')->flip();
            foreach ($other->templates as $template) {
                $existing->has($template->sha256)
                    ? $template->delete()
                    : $template->update(['employee_id' => $survivor->id]);
            }

            $survivor->devices()->syncWithoutDetaching($other->devices()->pluck('devices.id'));
            $other->devices()->detach();

            AttendanceEvent::where('employee_id', $other->id)->update(['employee_id' => $survivor->id]);
            Employee::withTrashed()->where('merged_into_id', $other->id)->update(['merged_into_id' => $survivor->id]);

            $other->delete();

            $this->sync->changed($other, 'merge', $devicesBefore, $survivor->id);
            $this->sync->changed($survivor->refresh(), 'upsert', $devicesBefore);

            AuditLog::record('employee.merged', $survivor, ['merged' => $other->id, 'fields_from_merged' => array_keys($values)]);

            return $survivor;
        });
    }
}
