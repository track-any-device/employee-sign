<?php

namespace App\Services\Devices;

use App\Events\DeviceCommandIssued;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use App\Support\Broadcasts;

class CommandService
{
    /**
     * Queue a command and push it to the device if it is connected. Offline
     * devices get it when they reconnect, or in the /heartbeat response.
     *
     * @param  array<string, mixed>  $args
     */
    public function issue(Device $device, string $name, array $args = [], ?User $user = null): DeviceCommand
    {
        $command = $device->commands()->create([
            'name' => $name,
            'args' => $args ?: null,
            'status' => DeviceCommand::QUEUED,
            'issued_by' => $user?->id,
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(config('devices.command_ttl_minutes')),
        ]);

        if ($device->online) {
            $this->deliver($command);
        }

        return $command;
    }

    public function deliver(DeviceCommand $command): void
    {
        $command->forceFill(['status' => DeviceCommand::SENT, 'sent_at' => now()])->save();
        Broadcasts::send(new DeviceCommandIssued($command));
    }

    /**
     * Commands still waiting for the device, marked as sent (heartbeat fallback).
     *
     * @return list<array<string, mixed>>
     */
    public function takePending(Device $device): array
    {
        $commands = $device->commands()
            ->where('status', DeviceCommand::QUEUED)
            ->where('expires_at', '>', now())
            ->orderBy('issued_at')
            ->get()
            ->each(fn (DeviceCommand $c) => $c->forceFill(['status' => DeviceCommand::SENT, 'sent_at' => now()])->save())
            ->map(fn (DeviceCommand $c) => $c->toDevicePayload());

        return array_values($commands->all());
    }
}
