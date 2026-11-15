<?php

namespace App\Http\Requests\Web;

use App\Models\Location;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeviceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'site_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $v) {
            $siteId = $this->filled('site_id') ? $this->integer('site_id') : null;
            $locationId = $this->filled('location_id') ? $this->integer('location_id') : null;

            // Site::find is tenant-scoped, so other tenants' sites are "not found".
            if ($siteId && ! Site::find($siteId)) {
                $v->errors()->add('site_id', 'Unknown site.');
            }
            if ($locationId) {
                $location = Location::whereKey($locationId)->first();
                if (! $location || ! Site::find($location->site_id) || ($siteId && $location->site_id !== $siteId)) {
                    $v->errors()->add('location_id', 'Unknown location for this site.');
                }
            }
        }];
    }
}
