<?php

use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Broadcast;

/*
| Web (session) channel auth. Device channels (private-device.{id}) are
| authorised by POST /api/device/v1/broadcasting/auth with the device token;
| users can never subscribe to them.
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenant}.devices', function (User $user, string $tenant) {
    return $user->hasVerifiedEmail() && $tenant === app(CurrentTenant::class)->key();
});
