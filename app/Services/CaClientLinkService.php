<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\TallyConnection;
use App\Models\Tenant;
use App\Models\TenantUserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CaClientLinkService
{
    /**
     * Give $caUser (from $caFirm) external access to $businessTenant.
     */
    public function link(Tenant $caFirm, User $caUser, Tenant $businessTenant): void
    {
        $role = Role::where('name', 'ExternalAccountant')->first();

        if (! $caUser->tenants()->where('tenants.id', $businessTenant->id)->exists()) {
            $caUser->tenants()->attach($businessTenant->id, [
                'status'             => 'active',
                'member_type'        => 'external',
                'source_tenant_id'   => $caFirm->id,
                'role_name'          => 'ExternalAccountant',
                'invited_by_user_id' => $caUser->id,
                'joined_at'          => now(),
            ]);

            if ($role) {
                TenantUserRole::create([
                    'user_id'   => $caUser->id,
                    'tenant_id' => $businessTenant->id,
                    'role_id'   => $role->id,
                ]);
            }
        }

        // Ensure the business has a TallyConnection so the CA can copy the token
        TallyConnection::withoutGlobalScope('tenant')
            ->firstOrCreate(['tenant_id' => $businessTenant->id]);

        // Sync existing Client record in the CA firm if one already matches by email
        $this->syncClientRecord($caFirm, $businessTenant);

        AuditEvent::log('ca.client.linked', [
            'ca_firm_id'         => $caFirm->id,
            'ca_firm_name'       => $caFirm->name,
            'business_tenant_id' => $businessTenant->id,
        ], tenantId: $businessTenant->id);
    }

    /**
     * Remove all external CA firm members from $businessTenant for $caFirm.
     */
    public function unlink(Tenant $caFirm, Tenant $businessTenant): void
    {
        $caUserIds = DB::table('tenant_user')
            ->where('tenant_id', $businessTenant->id)
            ->where('member_type', 'external')
            ->where('source_tenant_id', $caFirm->id)
            ->pluck('user_id');

        foreach ($caUserIds as $userId) {
            TenantUserRole::where('user_id', $userId)
                ->where('tenant_id', $businessTenant->id)
                ->delete();

            DB::table('tenant_user')
                ->where('user_id', $userId)
                ->where('tenant_id', $businessTenant->id)
                ->where('source_tenant_id', $caFirm->id)
                ->delete();
        }

        // Clear linked_tenant_id on the Client record — keep the record itself for invoices
        Client::where('tenant_id', $caFirm->id)
            ->where('linked_tenant_id', $businessTenant->id)
            ->update(['linked_tenant_id' => null]);

        AuditEvent::log('ca.client.unlinked', [
            'ca_firm_id'         => $caFirm->id,
            'business_tenant_id' => $businessTenant->id,
        ], tenantId: $businessTenant->id);
    }

    /**
     * If the CA firm already has a Client record matching this business (by linked_tenant_id
     * or by email), update it with the latest data and set linked_tenant_id.
     * Never creates a new Client record — the CA manages their client list manually.
     */
    public function syncClientRecord(Tenant $caFirm, Tenant $businessTenant): ?Client
    {
        $existing = Client::where('tenant_id', $caFirm->id)
            ->where('linked_tenant_id', $businessTenant->id)
            ->first();

        if (! $existing && $businessTenant->email) {
            $existing = Client::where('tenant_id', $caFirm->id)
                ->where('email', $businessTenant->email)
                ->whereNull('linked_tenant_id')
                ->first();
        }

        if (! $existing) {
            return null;
        }

        $existing->update([
            'linked_tenant_id' => $businessTenant->id,
            'name'             => $businessTenant->name,
            'email'            => $businessTenant->email,
            'phone'            => $businessTenant->phone,
            'tax_id'           => $businessTenant->gstin ?? $businessTenant->pan,
            'address'          => trim(implode(', ', array_filter([
                $businessTenant->address_line1,
                $businessTenant->city,
                $businessTenant->state,
                $businessTenant->pincode,
            ]))),
        ]);

        return $existing->fresh();
    }
}
