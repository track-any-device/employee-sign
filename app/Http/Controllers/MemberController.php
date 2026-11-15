<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentTenant;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(CurrentTenant $tenant): Response
    {
        return Inertia::render('members/index', [
            'members' => User::query()
                ->when($tenant->id() === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenant->id()))
                ->orderBy('name')
                ->get(['uuid', 'name', 'email', 'email_verified_at', 'created_at']),
        ]);
    }
}
