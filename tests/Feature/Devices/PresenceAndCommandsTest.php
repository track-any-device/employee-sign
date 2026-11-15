<?php

namespace Tests\Feature\Devices;

use App\Events\DeviceCommandIssued;
use App\Events\DeviceStatusChanged;
use App\Jobs\ExpireDeviceCommands;
use App\Jobs\MarkStaleDevicesOffline;
use App\Models\DeviceCommand;
use App\Services\Devices\DevicePresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PresenceAndCommandsTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    public function test_presence_marks_online_and_offline_and_broadcasts(): void
    {
        [$device] = $this->pairedDevice();
        Event::fake([DeviceStatusChanged::class, DeviceCommandIssued::class]);

        app(DevicePresence::class)->online($device->id);
        $this->assertTrue($device->fresh()->online);

        app(DevicePresence::class)->offline($device->id);
        $this->assertFalse($device->fresh()->online);

        Event::assertDispatchedTimes(DeviceStatusChanged::class, 2);
        $this->assertSame(['claimed', 'connected', 'disconnected'], $device->connectionLogs()->orderBy('id')->pluck('event')->all());
    }

    public function test_stale_devices_are_marked_offline(): void
    {
        [$device] = $this->pairedDevice();
        Event::fake([DeviceStatusChanged::class]);
        $device->forceFill(['online' => true, 'last_seen_at' => now()->subSeconds(91)])->save();

        app()->call([new MarkStaleDevicesOffline, 'handle']);

        $this->assertFalse($device->fresh()->online);
    }

    public function test_command_lifecycle(): void
    {
        [$device, $token] = $this->pairedDevice();
        Event::fake([DeviceCommandIssued::class, DeviceStatusChanged::class]);

        // Offline: queued, not pushed.
        $this->actingAs($this->verifiedUser())
            ->post(route('devices.commands.store', $device), ['name' => 'show_message', 'args' => ['text' => 'Hello', 'seconds' => 5]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $command = DeviceCommand::sole();
        $this->assertSame('queued', $command->status);
        Event::assertNotDispatched(DeviceCommandIssued::class);

        // Device connects: queued commands are delivered.
        app(DevicePresence::class)->online($device->id);
        Event::assertDispatched(DeviceCommandIssued::class, fn ($e) => $e->command->is($command)
            && $e->broadcastAs() === 'command'
            && $e->broadcastWith()['args']->text === 'Hello');
        $this->assertSame('sent', $command->fresh()->status);

        $this->postJson("/api/device/v1/commands/{$command->id}/result", ['status' => 'ok', 'detail' => 'shown'], $this->deviceHeaders($token))
            ->assertOk()->assertJson(['status' => 'done']);
        $this->assertSame('shown', $command->fresh()->result_detail);

        $this->postJson("/api/device/v1/commands/{$command->id}/result", ['status' => 'ok'], $this->deviceHeaders($token))->assertStatus(409);
    }

    public function test_unknown_command_or_bad_args_are_rejected(): void
    {
        [$device] = $this->pairedDevice();

        $this->actingAs($this->verifiedUser())->post(route('devices.commands.store', $device), ['name' => 'format_disk'])->assertSessionHasErrors('name');
        $this->post(route('devices.commands.store', $device), ['name' => 'show_message', 'args' => ['text' => 'x']])->assertSessionHasErrors('args.seconds');
    }

    public function test_heartbeat_returns_pending_commands_and_commands_expire(): void
    {
        [$device, $token] = $this->pairedDevice();
        $this->actingAs($this->verifiedUser())->post(route('devices.commands.store', $device), ['name' => 'sync_now']);

        $this->postJson('/api/device/v1/heartbeat', ['firmware_version' => '0.2.2', 'uptime_s' => 50, 'free_heap' => 123456, 'stats' => ['queue' => 0]], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('commands.0.name', 'sync_now')
            ->assertJsonStructure(['server_time']);
        $this->assertSame('0.2.2', $device->fresh()->firmware_version);

        $this->post(route('devices.commands.store', $device), ['name' => 'reboot']);
        $this->travel(11)->minutes();
        app()->call([new ExpireDeviceCommands, 'handle']);
        $this->assertSame(2, DeviceCommand::where('status', 'expired')->count());
    }
}
