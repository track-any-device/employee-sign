<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Devices\DevicePresence;
use Illuminate\Foundation\Queue\Queueable;

/** Safety net: devices with no ping/request for offline_after_seconds are offline. */
class MarkStaleDevicesOffline
{
    use Queueable;

    public function handle(DevicePresence $presence): void
    {
        Device::withoutGlobalScopes()
            ->where('online', true)
            ->where(fn ($q) => $q->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subSeconds(config('devices.offline_after_seconds'))))
            ->pluck('id')
            ->each(fn (string $id) => $presence->offline($id, 'timeout'));
    }
}
