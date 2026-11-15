<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Pairing codes and device tokens are only ever stored as
 * HMAC-SHA256(value, APP_KEY). Lookups go through the unique hash index and
 * are re-checked with hash_equals.
 */
class DeviceSecrets
{
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public static function matches(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::hash($plain));
    }

    /** 12 random digits, e.g. "483109275516". */
    public static function pairingCode(): string
    {
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    public static function formatCode(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    public static function normalizeCode(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }

    public static function deviceToken(): string
    {
        return 'tdt_'.Str::random(48);
    }
}
