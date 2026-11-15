<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Devices\ChangeFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeChangesController extends Controller
{
    public function __invoke(Request $request, Device $device, ChangeFeed $feed): JsonResponse
    {
        $data = $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('devices.changes_max_limit')],
            'types' => ['nullable', 'string'],
        ]);

        $types = isset($data['types'])
            ? array_values(array_intersect(explode(',', $data['types']), array_keys(config('devices.templates'))))
            : null;

        return response()->json($feed->since(
            $device,
            (int) ($data['cursor'] ?? 0),
            (int) ($data['limit'] ?? config('devices.changes_default_limit')),
            $types,
        ));
    }
}
