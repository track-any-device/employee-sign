<?php

namespace App\Events;

use App\Models\Device;
use App\Support\CurrentTenant;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Tells the devices screen that an OTT was claimed, so the code dialog closes. */
class DevicePaired implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Device $device) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant.'.CurrentTenant::keyFor($this->device->tenant_id).'.devices');
    }

    public function broadcastAs(): string
    {
        return 'DevicePaired';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->device->id,
            'name' => $this->device->name,
            'hardware_id' => $this->device->shortHardwareId(),
        ];
    }
}
