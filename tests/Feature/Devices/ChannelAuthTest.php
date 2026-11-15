<?php

namespace Tests\Feature\Devices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelAuthTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.connections.reverb.key' => 'app-key', 'broadcasting.connections.reverb.secret' => 'app-secret']);
    }

    public function test_device_can_authorise_its_own_channel(): void
    {
        [$device, $token] = $this->pairedDevice();
        $channel = 'private-device.'.$device->id;

        $this->postJson('/api/device/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => $channel], $this->deviceHeaders($token))
            ->assertOk()
            ->assertExactJson(['auth' => 'app-key:'.hash_hmac('sha256', '123.456:'.$channel, 'app-secret')]);
    }

    public function test_device_cannot_authorise_another_devices_channel(): void
    {
        [, $token] = $this->pairedDevice();
        [$other] = $this->pairedDevice(attributes: ['name' => 'Other']);

        $this->postJson('/api/device/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-device.'.$other->id], $this->deviceHeaders($token))
            ->assertForbidden();
        $this->postJson('/api/device/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-tenant.none.devices'], $this->deviceHeaders($token))
            ->assertForbidden();
    }

    public function test_channel_auth_requires_a_device_token(): void
    {
        $this->postJson('/api/device/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-device.x'])->assertUnauthorized();
    }
}
