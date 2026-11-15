<?php

namespace App\Services\Devices;

use App\Events\DeviceStatusChanged;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Support\Broadcasts;

class DevicePresence
{
    public function online(string $deviceId, ?string $ip = null): void
    {
        $device = Device::withoutGlobalScopes()->find($deviceId);
        if (! $device || $device->status !== Device::STATUS_ACTIVE) {
            return;
        }

        $wasOnline = $device->online;
        $device->forceFill(['online' => true, 'last_seen_at' => now()])->save();

        if (! $wasOnline) {
            $device->log('connected', $ip);
            Broadcasts::send(new DeviceStatusChanged($device));

            // Deliver commands queued while the device was away.
            $device->commands()->where('status', DeviceCommand::QUEUED)->where('expires_at', '>', now())
                ->orderBy('issued_at')->get()
                ->each(fn (DeviceCommand $c) => app(CommandService::class)->deliver($c));
        }
    }

    public function offline(string $deviceId, string $reason = 'disconnected'): void
    {
        $device = Device::withoutGlobalScopes()->find($deviceId);
        if (! $device || ! $device->online) {
            return;
        }

        $device->forceFill(['online' => false])->save();
        $device->log('disconnected', null, ['reason' => $reason]);
        Broadcasts::send(new DeviceStatusChanged($device));
    }

    public function touch(string $deviceId): void
    {
        Device::withoutGlobalScopes()->whereKey($deviceId)->update(['last_seen_at' => now()]);
    }

    /** Device id from "private-device.{id}", or null. */
    public static function deviceIdFromChannel(?string $channel): ?string
    {
        if ($channel && preg_match('/^private-device\.([0-9a-f-]{36})$/i', $channel, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }
}
