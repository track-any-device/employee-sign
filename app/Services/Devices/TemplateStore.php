<?php

namespace App\Services\Devices;

use App\Models\BiometricTemplate;
use App\Models\Employee;

class TemplateStore
{
    /**
     * Validate a template payload. Returns an error string or null.
     *
     * @param  array<string, mixed>  $t
     */
    public static function validate(array $t): ?string
    {
        $spec = config('devices.templates.'.($t['type'] ?? ''));
        if (! is_array($spec)) {
            return 'unsupported_type';
        }
        if (($t['model_version'] ?? null) !== $spec['model_version']) {
            return 'unsupported_model_version';
        }
        $bin = base64_decode((string) ($t['data_base64'] ?? ''), true);
        if ($bin === false || $bin === '') {
            return 'invalid_data';
        }
        if (($spec['bytes'] && strlen($bin) !== $spec['bytes']) || strlen($bin) > $spec['max_bytes']) {
            return 'invalid_length';
        }
        if (isset($t['sha256']) && ! hash_equals(strtolower((string) $t['sha256']), hash('sha256', $bin))) {
            return 'sha256_mismatch';
        }

        return null;
    }

    /**
     * Store a validated template unless the employee already has one with the
     * same hash. Returns true when a new row was created.
     *
     * @param  array<string, mixed>  $t
     */
    public static function add(Employee $employee, array $t, ?string $sourceDeviceId): bool
    {
        $bin = base64_decode((string) $t['data_base64'], true);
        $sha = hash('sha256', (string) $bin);

        if ($employee->templates()->where('sha256', $sha)->exists()) {
            return false;
        }

        BiometricTemplate::create([
            'employee_id' => $employee->id,
            'type' => $t['type'],
            'model_version' => $t['model_version'],
            'data' => base64_encode((string) $bin),
            'quality' => $t['quality'] ?? null,
            'source_device_id' => $sourceDeviceId,
            'sha256' => $sha,
        ]);

        // Keep at most N templates per type: drop the oldest.
        $max = (int) config('devices.max_templates_per_type');
        $employee->templates()->where('type', $t['type'])->orderByDesc('created_at')->orderByDesc('id')
            ->skip($max)->take(PHP_INT_MAX)->get()->each->delete();

        return true;
    }
}
