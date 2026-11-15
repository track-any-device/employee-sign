<?php

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\User;
use App\Services\Devices\PairingService;
use App\Support\CurrentTenant;

trait InteractsWithDevices
{
    /** @return array{0: Device, 1: string} active device and its plain bearer token */
    protected function pairedDevice(?int $tenantId = null, array $attributes = []): array
    {
        $tenant = app(CurrentTenant::class);
        $previous = $tenant->id();
        $tenant->set($tenantId);

        $device = Device::factory()->forTenant($tenantId)->create($attributes);
        $code = app(PairingService::class)->createCode($device, null)['code'];
        $result = app(PairingService::class)->claim($code, $this->hardware(), '127.0.0.1');

        $tenant->set($previous);
        $this->assertIsArray($result);

        return [$result['device']->fresh(), $result['token']];
    }

    /** @return array<string, string> */
    protected function hardware(string $hw = 'A1B2C3D409A4'): array
    {
        return [
            'hardware_id' => $hw,
            'mac' => 'A1:B2:C3:D4:09:A4',
            'model' => 'TAD-PAK AD',
            'firmware_version' => '0.2.1',
            'variant' => 'AD-DEV-1',
        ];
    }

    /** @return array<string, string> */
    protected function deviceHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    protected function verifiedUser(): User
    {
        return User::factory()->create();
    }

    /** @return array<string, mixed> */
    protected function faceTemplate(?string $bytes = null): array
    {
        $bin = $bytes ?? random_bytes(2048);

        return [
            'type' => 'face_mfn_s8_v1',
            'model_version' => 'MFN_S8_V1',
            'data_base64' => base64_encode($bin),
            'sha256' => hash('sha256', $bin),
        ];
    }

    /** Reset the scoped tenant between "requests" inside one test. */
    protected function resetTenant(): void
    {
        app(CurrentTenant::class)->set(null);
    }
}
