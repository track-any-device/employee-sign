<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Server -> device hint: pull GET /employees/changes now. */
class EmployeesChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $deviceId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('device.'.$this->deviceId);
    }

    public function broadcastAs(): string
    {
        return 'employees_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['at' => now()->toIso8601ZuluString()];
    }
}
