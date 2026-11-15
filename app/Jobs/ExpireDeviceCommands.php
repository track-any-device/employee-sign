<?php

namespace App\Jobs;

use App\Models\DeviceCommand;
use Illuminate\Foundation\Queue\Queueable;

class ExpireDeviceCommands
{
    use Queueable;

    public function handle(): void
    {
        DeviceCommand::whereIn('status', [DeviceCommand::QUEUED, DeviceCommand::SENT])
            ->where('expires_at', '<=', now())
            ->update(['status' => DeviceCommand::EXPIRED, 'updated_at' => now()]);
    }
}
