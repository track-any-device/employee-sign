<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceApiPayload;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __invoke(Device $device): JsonResponse
    {
        return response()->json([...DeviceApiPayload::device($device), 'ws' => DeviceApiPayload::ws($device)]);
    }
}
