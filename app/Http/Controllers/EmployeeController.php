<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\EmployeeRequest;
use App\Models\Device;
use App\Models\Employee;
use App\Services\Devices\EmployeeSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);
        $search = (string) $request->query('search', '');

        $employees = Employee::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%")))
            ->with('devices:id')
            ->withCount(['devices', 'templates as face_templates_count' => fn ($q) => $q->where('type', 'face_mfn_s8_v1'),
                'templates as fp_templates_count' => fn ($q) => $q->where('type', 'fp_zn600')])
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees->through(fn (Employee $e) => $this->row($e)),
            'devices' => Device::orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(EmployeeRequest $request, EmployeeSync $sync): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = DB::transaction(function () use ($request, $sync) {
            $employee = Employee::create($request->safe()->except('device_ids'));
            $employee->devices()->sync($request->input('device_ids', []));
            $sync->changed($employee);

            return $employee;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Employee {$employee->name} created."]);

        return to_route('employees.index');
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeSync $sync): RedirectResponse
    {
        $this->authorize('update', $employee);

        DB::transaction(function () use ($request, $employee, $sync) {
            $before = $sync->deviceIdsFor($employee);
            $employee->update($request->safe()->except('device_ids'));
            $employee->devices()->sync($request->input('device_ids', []));
            $sync->changed($employee, 'upsert', $before);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee updated.']);

        return back();
    }

    public function destroy(Employee $employee, EmployeeSync $sync): RedirectResponse
    {
        $this->authorize('delete', $employee);

        DB::transaction(function () use ($employee, $sync) {
            $before = $sync->deviceIdsFor($employee);
            $employee->delete();
            $sync->changed($employee, 'delete', $before);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee deleted.']);

        return to_route('employees.index');
    }

    /** @return array<string, mixed> Never includes template data, only counts. */
    private function row(Employee $e): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'code' => $e->code,
            'status' => $e->status,
            'all_devices' => $e->all_devices,
            'device_ids' => $e->relationLoaded('devices') ? $e->devices->pluck('id') : $e->devices()->pluck('devices.id'),
            'devices_count' => $e->devices_count,
            'templates' => ['face_mfn_s8_v1' => $e->face_templates_count, 'fp_zn600' => $e->fp_templates_count],
        ];
    }
}
