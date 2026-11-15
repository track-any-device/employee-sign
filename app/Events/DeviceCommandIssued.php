<?php

namespace App\Events;

use App\Models\DeviceCommand;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Server -> device: event "command" on private-device.{id}. */
class DeviceCommandIssued implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public DeviceCommand $command) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('device.'.$this->command->device_id);
    }

    public function broadcastAs(): string
    {
        return 'command';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->command->toDevicePayload();
    }
}
