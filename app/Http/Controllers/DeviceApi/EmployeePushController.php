<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BiometricConsent;
use App\Models\Device;
use App\Models\Employee;
use App\Services\Devices\EmployeeSync;
use App\Services\Devices\TemplateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeePushController extends Controller
{
    public function __invoke(Request $request, Device $device, EmployeeSync $sync): JsonResponse
    {
        $data = $request->validate([
            'employees' => ['required', 'array', 'min:1', 'max:'.config('devices.push_max_employees')],
            'employees.*.local_id' => ['required'],
            'employees.*.name' => ['required', 'string', 'max:255'],
            'employees.*.code' => ['nullable', 'string', 'max:64'],
            'employees.*.templates' => ['array', 'max:6'],
            'employees.*.templates.*.type' => ['required', 'string'],
            'employees.*.templates.*.model_version' => ['required', 'string'],
            'employees.*.templates.*.data_base64' => ['required', 'string'],
            'employees.*.templates.*.sha256' => ['nullable', 'string', 'size:64'],
            'employees.*.templates.*.quality' => ['nullable', 'numeric'],
            'consent' => ['required', 'array'],
            'consent.given_by' => ['required', 'string', 'max:255'],
            'consent.given_at' => ['required', 'date'],
        ]);

        // Validate every template before writing anything.
        foreach ($data['employees'] as $i => $e) {
            foreach ($e['templates'] ?? [] as $j => $t) {
                if ($error = TemplateStore::validate($t)) {
                    throw ValidationException::withMessages(["employees.$i.templates.$j" => $error]);
                }
            }
        }

        $mapping = DB::transaction(function () use ($data, $device, $sync) {
            $mapping = [];

            foreach ($data['employees'] as $e) {
                $employee = filled($e['code'] ?? null)
                    ? Employee::where('code', $e['code'])->first()
                    : null;

                $created = false;
                if (! $employee) {
                    $employee = Employee::create(['name' => $e['name'], 'code' => $e['code'] ?? null]);
                    $created = true;
                }

                // Enrolled here, so it must sync back to this device.
                $employee->devices()->syncWithoutDetaching([$device->id]);

                $added = 0;
                foreach ($e['templates'] ?? [] as $t) {
                    $added += (int) TemplateStore::add($employee, $t, $device->id);
                }

                if ($created || $added > 0) {
                    $sync->changed($employee);
                }

                $mapping[] = ['local_id' => $e['local_id'], 'employee_id' => $employee->id, 'created' => $created, 'templates_added' => $added];
            }

            BiometricConsent::create([
                'device_id' => $device->id,
                'given_by' => $data['consent']['given_by'],
                'given_at' => Carbon::parse($data['consent']['given_at'])->utc(),
                'employee_ids' => array_column($mapping, 'employee_id'),
            ]);

            AuditLog::record('employee.consent_upload', null, [
                'given_by' => $data['consent']['given_by'],
                'employees' => count($mapping),
            ], $device);

            return $mapping;
        });

        return response()->json(['mapping' => $mapping]);
    }
}
