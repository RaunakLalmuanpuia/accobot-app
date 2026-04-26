<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TallyConnection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TallyConnectionController extends Controller
{
    public function show(Tenant $tenant)
    {
        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->first();

        return Inertia::render('Tally/Connection', [
            'tenant'     => $tenant,
            'connection' => $connection ? [
                'id'                         => $connection->id,
                'company_id'                 => $connection->company_id,
                'is_active'                  => $connection->is_active,
                'inbound_token'              => $connection->inbound_token,
                'inbound_token_last_used_at' => $connection->inbound_token_last_used_at,
                'last_synced_at'             => $connection->last_synced_at,
            ] : null,
            'base_url' => url('/'),
        ]);
    }

    public function save(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'company_id' => ['required', 'string', 'max:255'],
            'is_active'  => ['boolean'],
        ]);

        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($connection) {
            $connection->update($data);
        } else {
            TallyConnection::create(array_merge($data, ['tenant_id' => $tenant->id]));
        }

        AuditEvent::log('tally.connection.saved', ['company_id' => $data['company_id']]);

        return back()->with('success', 'Tally connection saved.');
    }

    public function regenerateToken(Request $request, Tenant $tenant)
    {
        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $connection->regenerateToken();

        AuditEvent::log('tally.connection.token_regenerated');

        return back()->with('success', 'Token regenerated.');
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        AuditEvent::log('tally.connection.deleted');

        TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->delete();

        return back()->with('success', 'Tally connection removed.');
    }

    public function testConnection(Tenant $tenant)
    {
        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$connection) {
            return back()->with('error', 'No connection configured.');
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($connection->inbound_token)
                ->timeout(5)
                ->get(url('/api/MastersAPI/stock-master'), ['companyId' => $connection->company_id]);

            if ($response->successful()) {
                return back()->with('success', 'Connection test successful.');
            }

            return back()->with('error', 'Connection test failed: HTTP ' . $response->status());
        } catch (\Throwable $e) {
            return back()->with('error', 'Connection test failed: ' . $e->getMessage());
        }
    }
}
