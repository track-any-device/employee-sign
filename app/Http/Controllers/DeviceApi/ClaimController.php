<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceApiPayload;
use App\Services\Devices\PairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ClaimController extends Controller
{
    public function __invoke(Request $request, PairingService $pairing): JsonResponse
    {
        $lockKey = 'device-claim-failures:'.$request->ip();
        if (RateLimiter::tooManyAttempts($lockKey, config('devices.claim_max_failures'))) {
            return response()->json([
                'error' => 'too_many_attempts',
                'message' => 'Too many failed codes. Try again later.',
                'retry_after' => RateLimiter::availableIn($lockKey),
            ], 429);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{4}-?\d{4}-?\d{4}$/'],
            'hardware_id' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Fa-f:-]+$/'],
            'mac' => ['nullable', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
            'model' => ['nullable', 'string', 'max:64'],
            'firmware_version' => ['nullable', 'string', 'max:32'],
            'variant' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $pairing->claim($data['code'], $data, $request->ip());

        if (is_string($result)) {
            RateLimiter::hit($lockKey, config('devices.claim_lockout_minutes') * 60);

            return response()->json(['error' => $result, 'message' => match ($result) {
                'code_expired' => 'This code has expired. Create a new code.',
                'code_used' => 'This code was already used.',
                default => 'This code is not valid.',
            }], 422);
        }

        RateLimiter::clear($lockKey);
        $device = $result['device'];

        return response()->json([
            ...DeviceApiPayload::device($device),
            'device_token' => $result['token'],
            'ws' => DeviceApiPayload::ws($device),
        ], 201);
    }
}
