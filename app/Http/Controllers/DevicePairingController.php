<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Devices\PairingService;
use App\Support\DeviceSecrets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DevicePairingController extends Controller
{
    /** "Create OTT" / "New code" / "Re-pair": the code is flashed once, never stored. */
    public function store(Request $request, Device $device, PairingService $pairing): RedirectResponse
    {
        $this->authorize('update', $device);

        ['code' => $code, 'token' => $token] = $pairing->createCode($device, $request->user());

        Inertia::flash('pairing', [
            'device_id' => $device->id,
            'code' => DeviceSecrets::formatCode($code),
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);

        return back();
    }

    public function destroy(Device $device, PairingService $pairing): RedirectResponse
    {
        $this->authorize('update', $device);
        $pairing->revoke($device);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Device token revoked.']);

        return back();
    }
}
