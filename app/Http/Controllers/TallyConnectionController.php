<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TallyConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class TallyConnectionController extends Controller
{
    public function show(Tenant $tenant)
    {
        $this->guardManagedByCa($tenant);

        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->first();

        return Inertia::render('Tally/Connection', [
            'tenant'     => $tenant,
            'connection' => $connection ? [
                'id'                         => $connection->id,
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
        $this->guardManagedByCa($tenant);

        $data = $request->validate([
            'is_active' => ['boolean'],
        ]);

        TallyConnection::withoutGlobalScope('tenant')
            ->updateOrCreate(
                ['tenant_id' => $tenant->id],
                $data
            );

        AuditEvent::log('tally.connection.saved');

        return back()->with('success', 'Tally connection saved.');
    }

    public function regenerateToken(Request $request, Tenant $tenant)
    {
        $this->guardManagedByCa($tenant);

        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $connection->regenerateToken();

        AuditEvent::log('tally.connection.token_regenerated');

        return back()->with('success', 'Token regenerated.');
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        $this->guardManagedByCa($tenant);

        AuditEvent::log('tally.connection.deleted');

        TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->delete();

        return back()->with('success', 'Tally connection removed.');
    }

    public function testConnection(Tenant $tenant)
    {
        $this->guardManagedByCa($tenant);

        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $connection) {
            return back()->with('error', 'No connection configured.');
        }

        try {
            $response = Http::withToken($connection->inbound_token)
                ->timeout(5)
                ->get(url('/api/MastersAPI/stock-master'));

            if ($response->successful()) {
                return back()->with('success', 'Connection test successful.');
            }

            return back()->with('error', 'Connection test failed: HTTP ' . $response->status());
        } catch (\Throwable $e) {
            return back()->with('error', 'Connection test failed: ' . $e->getMessage());
        }
    }

    private function guardManagedByCa(Tenant $tenant): void
    {
        abort_if($tenant->tally_managed_by_ca, 403, 'Your Tally connection is managed by your CA firm.');
    }
}
