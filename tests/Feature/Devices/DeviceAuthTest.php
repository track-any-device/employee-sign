<?php

namespace Tests\Feature\Devices;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAuthTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    public function test_requests_without_or_with_a_bad_token_are_rejected(): void
    {
        $this->getJson('/api/device/v1/me')->assertUnauthorized();
        $this->getJson('/api/device/v1/me', $this->deviceHeaders('tdt_nope'))->assertUnauthorized();
    }

    public function test_authenticated_request_updates_last_seen_and_ip(): void
    {
        [$device, $token] = $this->pairedDevice();
        $device->forceFill(['last_seen_at' => now()->subHour(), 'last_ip' => null])->save();

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->getJson('/api/device/v1/me', $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonStructure(['server_time']);

        $device->refresh();
        $this->assertSame('10.1.2.3', $device->last_ip);
        $this->assertTrue($device->last_seen_at->gt(now()->subMinute()));
    }

    public function test_revoked_device_gets_401(): void
    {
        [$device, $token] = $this->pairedDevice();

        $this->actingAs($this->verifiedUser())->delete(route('devices.token.destroy', $device))->assertRedirect();

        $this->assertSame('revoked', $device->fresh()->status);
        $this->getJson('/api/device/v1/me', $this->deviceHeaders($token))->assertUnauthorized();
        $this->assertTrue(AuditLog::where('action', 'device.token_revoked')->exists());
    }

    public function test_device_token_cannot_access_user_routes(): void
    {
        [, $token] = $this->pairedDevice();

        $this->get(route('devices.index'), $this->deviceHeaders($token))->assertUnauthorized();
        $this->get(route('dashboard'), ['Authorization' => 'Bearer '.$token])->assertRedirect(route('login'));
    }
}
