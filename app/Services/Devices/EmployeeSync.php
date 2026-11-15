<?php

namespace App\Services\Devices;

use App\Events\EmployeesChanged;
use App\Models\Device;
use App\Models\Employee;
use App\Models\SyncVersion;
use App\Support\Broadcasts;
use Illuminate\Support\Facades\DB;

/**
 * Records employee changes in the per-tenant change feed (sync_versions)
 * and tells the affected devices to pull right away.
 */
class EmployeeSync
{
    /**
     * @param  list<string>  $alsoNotifyDeviceIds  devices that were allowed before the change
     */
    public function changed(Employee $employee, string $op = 'upsert', array $alsoNotifyDeviceIds = [], ?string $mergedInto = null): void
    {
        SyncVersion::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'op' => $op,
            'merged_into' => $mergedInto,
        ]);

        $deviceIds = array_values(array_unique([...$this->deviceIdsFor($employee), ...$alsoNotifyDeviceIds]));

        DB::afterCommit(function () use ($deviceIds) {
            foreach ($deviceIds as $id) {
                Broadcasts::send(new EmployeesChanged($id));
            }
        });
    }

    /** @return list<string> */
    public function deviceIdsFor(Employee $employee): array
    {
        $query = Device::withoutGlobalScopes()
            ->where('status', Device::STATUS_ACTIVE)
            ->when(
                $employee->tenant_id === null,
                fn ($q) => $q->whereNull('tenant_id'),
                fn ($q) => $q->where('tenant_id', $employee->tenant_id),
            );

        if (! $employee->all_devices) {
            $query->whereIn('id', DB::table('employee_device')->where('employee_id', $employee->id)->select('device_id'));
        }

        return array_values($query->get(['id'])->map(fn (Device $d) => $d->id)->all());
    }
}
