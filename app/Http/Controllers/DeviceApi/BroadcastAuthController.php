<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pusher-protocol channel auth for devices. A device may only subscribe to
 * its own channel, private-device.{id}.
 */
class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request, Device $device): JsonResponse
    {
        $data = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string'],
        ]);

        if (! hash_equals('private-'.$device->channelName(), $data['channel_name'])) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $key = (string) config('broadcasting.connections.reverb.key');
        $secret = (string) config('broadcasting.connections.reverb.secret');
        $signature = hash_hmac('sha256', $data['socket_id'].':'.$data['channel_name'], $secret);

        return response()->json(['auth' => $key.':'.$signature]);
    }
}
