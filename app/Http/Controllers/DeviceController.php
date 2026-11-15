<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\DeviceRequest;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\Site;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(CurrentTenant $tenant): Response
    {
        $this->authorize('viewAny', Device::class);

        return Inertia::render('devices/index', [
            'devices' => Device::with('site:id,name', 'location:id,name')->orderBy('name')->get()->map(fn (Device $d) => $this->row($d)),
            'sites' => Site::with('locations:id,site_id,name')->orderBy('name')->get(['id', 'name']),
            'channel' => 'tenant.'.$tenant->key().'.devices',
        ]);
    }

    public function store(DeviceRequest $request): RedirectResponse
    {
        $this->authorize('create', Device::class);

        $device = Device::create($request->validated());

        return to_route('devices.show', $device);
    }

    public function show(Device $device, CurrentTenant $tenant): Response
    {
        $this->authorize('view', $device);

        $device->load('site:id,name', 'location:id,name');
        $live = $device->pairingTokens()->live()->latest('id')->first();

        return Inertia::render('devices/show', [
            'device' => [
                ...$this->row($device),
                'mac' => $device->mac,
                'model' => $device->model,
                'variant' => $device->variant,
                'hardware_id_full' => $device->hardware_id,
                'claimed_at' => $device->claimed_at?->toIso8601String(),
                'last_ip' => $device->last_ip,
                'last_stats' => $device->last_stats,
                'site_id' => $device->site_id,
                'location_id' => $device->location_id,
                'has_token' => $device->tokens()->whereNull('revoked_at')->exists(),
            ],
            // Only the expiry: the plain code is never stored, it was shown once.
            'livePairingExpiresAt' => $live?->expires_at->toIso8601String(),
            'sites' => Site::with('locations:id,site_id,name')->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::orderBy('name')->get(['id', 'name', 'code', 'all_devices'])->map(fn (Employee $e) => [
                'id' => $e->id, 'name' => $e->name, 'code' => $e->code, 'all_devices' => $e->all_devices,
            ]),
            'allowedEmployeeIds' => $device->employees()->pluck('employees.id'),
            'attendance' => $device->attendanceEvents()->with('employee:id,name,code')->latest('received_at')->limit(25)->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'employee' => $a->employee?->name,
                    'occurred_at' => $a->occurred_at?->toIso8601String(),
                    'received_at' => $a->received_at->toIso8601String(),
                    'method' => $a->method,
                    'similarity' => $a->similarity,
                ]),
            'connectionLog' => $device->connectionLogs()->latest('id')->limit(30)->get(['id', 'event', 'ip', 'meta', 'created_at']),
            'commands' => $device->commands()->latest('issued_at')->limit(15)->get(['id', 'name', 'args', 'status', 'issued_at', 'completed_at', 'result_detail']),
            'commandNames' => array_keys(DeviceCommand::COMMANDS),
            'channel' => 'tenant.'.$tenant->key().'.devices',
        ]);
    }

    public function update(DeviceRequest $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);
        $device->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Device updated.']);

        return back();
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorize('delete', $device);
        $device->delete();

        return to_route('devices.index');
    }

    /** @return array<string, mixed> */
    private function row(Device $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'site' => $d->site?->name,
            'location' => $d->location?->name,
            'status' => $d->status,
            'online' => $d->online,
            'last_seen_at' => $d->last_seen_at?->toIso8601String(),
            'firmware_version' => $d->firmware_version,
            'hardware_id' => $d->shortHardwareId(),
        ];
    }
}
