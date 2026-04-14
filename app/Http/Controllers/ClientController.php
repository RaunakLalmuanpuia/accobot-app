<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Tenant $tenant)
    {
        return inertia('Clients/Index', [
            'tenant'  => $tenant,
            'clients' => Client::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $tenant->clients()->create($request->only('name', 'email', 'phone'));

        return back();
    }

    public function update(Request $request, Tenant $tenant, Client $client)
    {
        abort_if($client->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $client->update($request->only('name', 'email', 'phone'));

        return back();
    }

    public function destroy(Tenant $tenant, Client $client)
    {
        abort_if($client->tenant_id !== $tenant->id, 403);

        $client->delete();

        return back();
    }
}
