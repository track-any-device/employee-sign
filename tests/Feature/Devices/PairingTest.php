<?php

namespace Tests\Feature\Devices;

use App\Events\DevicePaired;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DevicePairingToken;
use App\Services\Devices\PairingService;
use App\Support\DeviceSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    public function test_user_can_add_a_pending_device(): void
    {
        $this->actingAs($this->verifiedUser())
            ->post(route('devices.store'), ['name' => 'Main gate'])
            ->assertRedirect();

        $this->assertDatabaseHas('devices', ['name' => 'Main gate', 'status' => 'pending', 'tenant_id' => null]);
    }

    public function test_creating_an_ott_flashes_a_12_digit_code_and_stores_only_its_hash(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->verifiedUser())
            ->post(route('devices.pairing.store', $device))
            ->assertRedirect();

        $flash = session(SessionKey::FLASH_DATA)['pairing'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{4}$/', $flash['code']);

        $token = DevicePairingToken::sole();
        $plain = str_replace('-', '', $flash['code']);
        $this->assertSame(DeviceSecrets::hash($plain), $token->code_hash);
        $this->assertNotSame($plain, $token->code_hash);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $token->expires_at->timestamp, 2);
        $this->assertTrue(AuditLog::where('action', 'device.ott_created')->exists());
        $response->assertSessionHasNoErrors();
    }

    public function test_a_new_code_invalidates_the_previous_one(): void
    {
        $device = Device::factory()->create();
        $pairing = app(PairingService::class);

        $old = $pairing->createCode($device, null)['code'];
        $new = $pairing->createCode($device, null)['code'];

        $this->assertSame(1, $device->pairingTokens()->live()->count());
        $this->postJson('/api/device/v1/claim', ['code' => $old, ...$this->hardware()])
            ->assertStatus(422)->assertJson(['error' => 'code_expired']);
        $this->postJson('/api/device/v1/claim', ['code' => $new, ...$this->hardware()])->assertCreated();
    }

    public function test_device_claims_a_valid_code(): void
    {
        Event::fake([DevicePaired::class]);
        $device = Device::factory()->create(['name' => 'Main gate']);
        $code = app(PairingService::class)->createCode($device, null)['code'];

        $response = $this->postJson('/api/device/v1/claim', ['code' => DeviceSecrets::formatCode($code), ...$this->hardware()])
            ->assertCreated()
            ->assertJsonStructure(['device_id', 'device_token', 'name', 'tenant', 'site', 'location', 'server_time', 'ws' => ['host', 'port', 'scheme', 'app_key', 'channel']])
            ->assertJson(['device_id' => $device->id, 'name' => 'Main gate', 'ws' => ['channel' => 'private-device.'.$device->id]]);

        $device->refresh();
        $this->assertSame('active', $device->status);
        $this->assertSame('A1B2C3D409A4', $device->hardware_id);
        $this->assertSame('09A4', $device->shortHardwareId());
        $this->assertNotNull($device->claimed_at);

        $token = $response->json('device_token');
        $this->assertDatabaseMissing('device_tokens', ['token_hash' => $token]);
        $this->getJson('/api/device/v1/me', $this->deviceHeaders($token))->assertOk()->assertJson(['device_id' => $device->id]);

        Event::assertDispatched(DevicePaired::class, fn ($e) => $e->device->is($device));
        $this->assertTrue(AuditLog::where('action', 'device.claimed')->exists());
    }

    public function test_expired_code_is_rejected(): void
    {
        $device = Device::factory()->create();
        $code = app(PairingService::class)->createCode($device, null)['code'];

        $this->travel(11)->minutes();

        $this->postJson('/api/device/v1/claim', ['code' => $code, ...$this->hardware()])
            ->assertStatus(422)->assertJson(['error' => 'code_expired']);
        $this->assertSame('pending', $device->fresh()->status);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $device = Device::factory()->create();
        $code = app(PairingService::class)->createCode($device, null)['code'];

        $this->postJson('/api/device/v1/claim', ['code' => $code, ...$this->hardware()])->assertCreated();
        $this->postJson('/api/device/v1/claim', ['code' => $code, ...$this->hardware()])
            ->assertStatus(422)->assertJson(['error' => 'code_used']);
        $this->assertSame(1, $device->tokens()->count());
    }

    public function test_unknown_code_is_invalid(): void
    {
        $this->postJson('/api/device/v1/claim', ['code' => '000000000000', ...$this->hardware()])
            ->assertStatus(422)->assertJson(['error' => 'code_invalid']);
    }

    public function test_ip_is_locked_after_five_failed_codes(): void
    {
        $device = Device::factory()->create();
        $code = app(PairingService::class)->createCode($device, null)['code'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/device/v1/claim', ['code' => sprintf('%012d', $i), ...$this->hardware()])->assertStatus(422);
        }

        // Even the correct code is refused while locked.
        $this->postJson('/api/device/v1/claim', ['code' => $code, ...$this->hardware()])
            ->assertStatus(429)->assertJson(['error' => 'too_many_attempts']);
        $this->assertSame('pending', $device->fresh()->status);

        $this->travel(16)->minutes();
        $code = app(PairingService::class)->createCode($device, null)['code'];
        $this->postJson('/api/device/v1/claim', ['code' => $code, ...$this->hardware()])->assertCreated();
    }

    public function test_claim_route_is_rate_limited_per_ip(): void
    {
        config(['devices.claim_max_failures' => 1000]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/device/v1/claim', ['code' => 'bad'])->assertStatus(422);
        }
        $this->postJson('/api/device/v1/claim', ['code' => 'bad'])->assertStatus(429);
    }

    public function test_re_pair_revokes_the_old_token_only_on_successful_claim(): void
    {
        [$device, $oldToken] = $this->pairedDevice();
        $code = app(PairingService::class)->createCode($device, null)['code'];

        // Old token still works while the new code is unclaimed.
        $this->getJson('/api/device/v1/me', $this->deviceHeaders($oldToken))->assertOk();

        $newToken = $this->postJson('/api/device/v1/claim', ['code' => $code, ...$this->hardware()])->assertCreated()->json('device_token');

        $this->getJson('/api/device/v1/me', $this->deviceHeaders($oldToken))->assertUnauthorized();
        $this->getJson('/api/device/v1/me', $this->deviceHeaders($newToken))->assertOk();
    }
}
