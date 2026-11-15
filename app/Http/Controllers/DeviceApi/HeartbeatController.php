<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Devices\CommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function __invoke(Request $request, Device $device, CommandService $commands): JsonResponse
    {
        $data = $request->validate([
            'firmware_version' => ['nullable', 'string', 'max:32'],
            'uptime_s' => ['nullable', 'integer', 'min:0'],
            'free_heap' => ['nullable', 'integer', 'min:0'],
            'stats' => ['nullable', 'array'],
        ]);

        $device->forceFill([
            'firmware_version' => $data['firmware_version'] ?? $device->firmware_version,
            'last_stats' => [
                'uptime_s' => $data['uptime_s'] ?? null,
                'free_heap' => $data['free_heap'] ?? null,
                'stats' => $data['stats'] ?? null,
                'at' => now()->toIso8601ZuluString(),
            ],
        ])->save();

        return response()->json([
            'server_time' => now()->toIso8601ZuluString(),
            'commands' => $commands->takePending($device),
        ]);
    }
}
