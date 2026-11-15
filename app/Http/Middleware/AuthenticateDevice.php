<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\DeviceToken;
use App\Support\CurrentTenant;
use App\Support\DeviceSecrets;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Device bearer-token auth. Only used on /api/device/v1 routes: device
 * tokens are not a guard, so they can never authenticate user routes.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        $hash = $plain === '' ? null : DeviceSecrets::hash($plain);

        $token = $hash ? DeviceToken::where('token_hash', $hash)->first() : null;

        if (! $token || ! hash_equals($token->token_hash, (string) $hash) || $token->revoked_at !== null) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'Invalid or revoked device token.'], 401);
        }

        $device = $token->device;
        if (! $device || $device->status !== Device::STATUS_ACTIVE) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'Device is not active.'], 401);
        }

        app(CurrentTenant::class)->set($device->tenant_id);

        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('device', $device);
        app()->instance(Device::class, $device);

        return $next($request);
    }
}
