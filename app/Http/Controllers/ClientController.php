<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Tenant $tenant)
    {
        return inertia('Clients/Index', [
            'tenant'  => $tenant,
            'clients' => Client::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes']),
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'tax_id'  => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $client = $tenant->clients()->create($request->only('name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes'));

        AuditEvent::log('client.created', ['id' => $client->id, 'name' => $client->name]);

        return back();
    }

    public function update(Request $request, Tenant $tenant, Client $client)
    {
        abort_if($client->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'tax_id'  => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $client->update($request->only('name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes'));

        AuditEvent::log('client.updated', ['id' => $client->id, 'name' => $client->name]);

        return back();
    }

    public function destroy(Tenant $tenant, Client $client)
    {
        abort_if($client->tenant_id !== $tenant->id, 403);

        AuditEvent::log('client.deleted', ['id' => $client->id, 'name' => $client->name]);

        $client->delete();

        return back();
    }
}
