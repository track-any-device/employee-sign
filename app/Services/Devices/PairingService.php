<?php

namespace App\Services\Devices;

use App\Events\DevicePaired;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DevicePairingToken;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\Broadcasts;
use App\Support\CurrentTenant;
use App\Support\DeviceSecrets;
use Illuminate\Support\Facades\DB;

class PairingService
{
    /**
     * Create a new one-time pairing code. Any live code for the device is
     * invalidated, so there is at most one usable code per device.
     *
     * @return array{code: string, token: DevicePairingToken}
     */
    public function createCode(Device $device, ?User $user): array
    {
        return DB::transaction(function () use ($device, $user) {
            $device->pairingTokens()->live()->update(['expires_at' => now()]);

            do {
                $code = DeviceSecrets::pairingCode();
                $hash = DeviceSecrets::hash($code);
            } while (DevicePairingToken::where('code_hash', $hash)->exists());

            $token = $device->pairingTokens()->create([
                'code_hash' => $hash,
                'expires_at' => now()->addMinutes(config('devices.ott_lifetime_minutes')),
                'created_by' => $user?->id,
            ]);

            AuditLog::record('device.ott_created', $device, ['expires_at' => $token->expires_at->toIso8601String()], $device);

            return ['code' => $code, 'token' => $token];
        });
    }

    /**
     * @param  array{hardware_id: string, mac?: string|null, model?: string|null, firmware_version?: string|null, variant?: string|null}  $hardware
     * @return array{device: Device, token: string}|string Error code on failure.
     */
    public function claim(string $code, array $hardware, ?string $ip): array|string
    {
        $hash = DeviceSecrets::hash(DeviceSecrets::normalizeCode($code));

        return DB::transaction(function () use ($hash, $hardware, $ip) {
            $pairing = DevicePairingToken::where('code_hash', $hash)->lockForUpdate()->first();

            if (! $pairing || ! hash_equals($pairing->code_hash, $hash)) {
                return 'code_invalid';
            }
            if ($pairing->claimed_at !== null) {
                return 'code_used';
            }
            if ($pairing->isExpired()) {
                return 'code_expired';
            }

            $device = $pairing->device;
            app(CurrentTenant::class)->set($device->tenant_id);

            $pairing->update(['claimed_at' => now()]);

            // Re-pair: the old token stops working the moment the new claim succeeds.
            $device->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $device->forceFill([
                'status' => Device::STATUS_ACTIVE,
                'hardware_id' => strtoupper($hardware['hardware_id']),
                'mac' => isset($hardware['mac']) ? strtoupper($hardware['mac']) : $device->mac,
                'model' => $hardware['model'] ?? $device->model,
                'firmware_version' => $hardware['firmware_version'] ?? $device->firmware_version,
                'variant' => $hardware['variant'] ?? $device->variant,
                'claimed_at' => now(),
                'last_seen_at' => now(),
                'last_ip' => $ip,
            ])->save();

            $plain = DeviceSecrets::deviceToken();
            DeviceToken::create(['device_id' => $device->id, 'token_hash' => DeviceSecrets::hash($plain)]);

            $device->log('claimed', $ip, ['hardware_id' => $device->hardware_id]);
            AuditLog::record('device.claimed', $device, ['hardware_id' => $device->hardware_id], $device);

            DB::afterCommit(fn () => Broadcasts::send(new DevicePaired($device)));

            return ['device' => $device, 'token' => $plain];
        });
    }

    public function revoke(Device $device): void
    {
        DB::transaction(function () use ($device) {
            $device->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $device->pairingTokens()->live()->update(['expires_at' => now()]);
            $device->forceFill(['status' => Device::STATUS_REVOKED, 'online' => false])->save();
            $device->log('revoked', request()->ip());
            AuditLog::record('device.token_revoked', $device, [], $device);
        });
    }
}
