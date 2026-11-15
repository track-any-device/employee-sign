<?php

namespace App\Events;

use App\Models\Device;
use App\Support\CurrentTenant;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DeviceStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Device $device) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant.'.CurrentTenant::keyFor($this->device->tenant_id).'.devices');
    }

    public function broadcastAs(): string
    {
        return 'DeviceStatusChanged';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->device->id,
            'status' => $this->device->status,
            'online' => $this->device->online,
            'last_seen_at' => $this->device->last_seen_at?->toIso8601ZuluString(),
            'firmware_version' => $this->device->firmware_version,
        ];
    }
}
