<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Employee;
use App\Services\Devices\EmployeeSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DeviceEmployeesController extends Controller
{
    public function update(Request $request, Device $device, EmployeeSync $sync): RedirectResponse
    {
        $this->authorize('update', $device);

        $ids = $request->validate(['employee_ids' => ['array'], 'employee_ids.*' => ['string']])['employee_ids'] ?? [];
        $ids = Employee::whereIn('id', $ids)->pluck('id')->all(); // tenant-scoped

        DB::transaction(function () use ($device, $ids, $sync) {
            $changes = $device->employees()->sync($ids);
            $touched = [...$changes['attached'], ...$changes['detached']];

            Employee::whereIn('id', $touched)->get()->each(fn (Employee $e) => $sync->changed($e, 'upsert', [$device->id]));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Allowed employees updated.']);

        return back();
    }
}
