<?php

namespace App\Http\Requests\Web;

use App\Models\Device;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,inactive'],
            'all_devices' => ['boolean'],
            'device_ids' => ['array'],
            'device_ids.*' => ['string'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $v) {
            $employee = $this->route('employee');
            $ignoreId = $employee instanceof Employee ? $employee->id : null;
            $code = $this->input('code');

            // Unique per tenant; done here because NULL tenant_id escapes the DB unique index.
            if (filled($code) && Employee::where('code', $code)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()) {
                $v->errors()->add('code', 'This employee number is already used.');
            }

            $ids = (array) $this->input('device_ids', []);
            if ($ids && Device::whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                $v->errors()->add('device_ids', 'Unknown device.');
            }
        }];
    }
}
