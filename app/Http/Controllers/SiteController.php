<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Site::class);

        return Inertia::render('sites/index', [
            'sites' => Site::with('locations:id,site_id,name')->orderBy('name')->get(['id', 'name', 'address']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Site::class);
        Site::create($request->validate(['name' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:255']]));

        return back();
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);
        $site->delete();

        return back();
    }

    public function storeLocation(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $site->locations()->create($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back();
    }

    public function destroyLocation(Site $site, Location $location): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($location->site_id === $site->id, 404);
        $location->delete();

        return back();
    }
}
