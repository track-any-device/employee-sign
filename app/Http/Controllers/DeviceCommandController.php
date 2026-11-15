<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Services\Devices\CommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DeviceCommandController extends Controller
{
    public function store(Request $request, Device $device, CommandService $commands): RedirectResponse
    {
        $this->authorize('update', $device);

        $name = $request->validate(['name' => ['required', Rule::in(array_keys(DeviceCommand::COMMANDS))]])['name'];

        $rules = collect(DeviceCommand::COMMANDS[$name])->mapWithKeys(fn ($r, $k) => ["args.$k" => $r])->all();
        $args = $rules ? $request->validate($rules)['args'] : [];

        $commands->issue($device, $name, $args, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => $device->online ? 'Command sent.' : 'Command queued until the device connects.']);

        return back();
    }
}
