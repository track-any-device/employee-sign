<?php

namespace App\Http\Resources;

use App\Models\Device;

class DeviceApiPayload
{
    /** @return array<string, mixed> */
    public static function device(Device $device): array
    {
        $device->loadMissing('site', 'location');

        return [
            'device_id' => $device->id,
            'name' => $device->name,
            'status' => $device->status,
            'hardware_id' => $device->hardware_id,
            'mac' => $device->mac,
            'model' => $device->model,
            'firmware_version' => $device->firmware_version,
            'variant' => $device->variant,
            // Tenancy is not live yet: Custom installs report tenant null.
            'tenant' => $device->tenant_id === null ? null : ['id' => $device->tenant_id, 'name' => null],
            'site' => $device->site ? ['id' => $device->site->id, 'name' => $device->site->name] : null,
            'location' => $device->location ? ['id' => $device->location->id, 'name' => $device->location->name] : null,
            'server_time' => now()->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function ws(Device $device): array
    {
        return [
            'host' => config('devices.ws.host'),
            'port' => config('devices.ws.port'),
            'scheme' => config('devices.ws.scheme'),
            'app_key' => config('devices.ws.app_key'),
            'channel' => 'private-'.$device->channelName(),
            'auth_endpoint' => '/api/device/v1/broadcasting/auth',
        ];
    }
}
