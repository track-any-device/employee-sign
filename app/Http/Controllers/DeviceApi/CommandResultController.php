<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandResultController extends Controller
{
    public function __invoke(Request $request, Device $device, string $command): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:ok,error'],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);

        $cmd = DeviceCommand::where('device_id', $device->id)->find($command);
        if (! $cmd) {
            return response()->json(['error' => 'not_found'], 404);
        }
        if (! $cmd->isOpen()) {
            return response()->json(['error' => 'command_closed', 'status' => $cmd->status], 409);
        }

        $cmd->forceFill([
            'status' => $data['status'] === 'ok' ? DeviceCommand::DONE : DeviceCommand::FAILED,
            'completed_at' => now(),
            'result_detail' => $data['detail'] ?? null,
        ])->save();

        return response()->json(['id' => $cmd->id, 'status' => $cmd->status]);
    }
}
