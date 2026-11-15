<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeMergeController extends Controller
{
    public function create(): Response
    {
        $this->authorize('viewAny', Employee::class);

        return Inertia::render('employees/merge', [
            'employees' => Employee::withCount('templates', 'attendanceEvents', 'devices')->orderBy('name')->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id, 'name' => $e->name, 'code' => $e->code, 'status' => $e->status,
                    'templates_count' => $e->templates_count,
                    'attendance_count' => $e->attendance_events_count,
                    'devices_count' => $e->devices_count,
                ]),
            'fields' => EmployeeMerger::MERGEABLE_FIELDS,
        ]);
    }

    public function store(Request $request, EmployeeMerger $merger): RedirectResponse
    {
        $data = $request->validate([
            'survivor_id' => ['required', 'string'],
            'merged_id' => ['required', 'string', 'different:survivor_id'],
            'take_from_merged' => ['array'],
            'take_from_merged.*' => [Rule::in(EmployeeMerger::MERGEABLE_FIELDS)],
        ]);

        $survivor = Employee::whereKey($data['survivor_id'])->firstOrFail();
        $other = Employee::whereKey($data['merged_id'])->firstOrFail();
        $this->authorize('update', $survivor);
        $this->authorize('delete', $other);

        $merger->merge($survivor, $other, $data['take_from_merged'] ?? []);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Merged {$other->name} into {$survivor->name}."]);

        return to_route('employees.index');
    }
}
