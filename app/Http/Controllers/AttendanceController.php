<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Device::class);

        $filters = $request->validate([
            'site_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'device_id' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $events = AttendanceEvent::query()
            ->with(['employee:id,name,code', 'device:id,name,site_id,location_id'])
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('device_id', $v))
            ->when($filters['site_id'] ?? null, fn ($q, $v) => $q->whereIn('device_id', Device::where('site_id', $v)->select('id')))
            ->when($filters['location_id'] ?? null, fn ($q, $v) => $q->whereIn('device_id', Device::where('location_id', $v)->select('id')))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('occurred_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('occurred_at', '<', now()->parse($v)->addDay()))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(50)->withQueryString()
            ->through(fn (AttendanceEvent $a) => [
                'id' => $a->id,
                'employee' => $a->employee ? ['id' => $a->employee->id, 'name' => $a->employee->name, 'code' => $a->employee->code] : null,
                'device' => $a->device?->name,
                'occurred_at' => $a->occurred_at?->toIso8601String(),
                'received_at' => $a->received_at->toIso8601String(),
                'method' => $a->method,
                'similarity' => $a->similarity,
            ]);

        return Inertia::render('attendance/index', [
            'events' => $events,
            'filters' => $filters,
            'sites' => Site::with('locations:id,site_id,name')->orderBy('name')->get(['id', 'name']),
            'devices' => Device::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
