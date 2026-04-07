<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantRolePermission;
use App\Models\TenantUserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Tenants ──────────────────────────────────────────────────
        $alphaCA  = Tenant::create(['name' => 'Alpha Accounting Firm', 'type' => 'ca_firm',  'status' => 'active']);
        $betaBiz  = Tenant::create(['name' => 'Beta Finance Group',    'type' => 'business', 'status' => 'active']);
        $gammaCA  = Tenant::create(['name' => 'Gamma Tax Consultants', 'type' => 'ca_firm',  'status' => 'active']);
        $deltaBiz = Tenant::create(['name' => 'Delta Retail Co.',      'type' => 'business', 'status' => 'active']);

        // ─── Role map ─────────────────────────────────────────────────
        $r = Role::all()->keyBy('name');

        // ─── Platform Admin ───────────────────────────────────────────
        $admin = $this->user('Platform Admin', 'admin@example.com');
        $admin->assignRole($r['admin']);

        // ─────────────────────────────────────────────────────────────
        // Single-tenant users — one clear role per person
        // ─────────────────────────────────────────────────────────────

        // betaBiz — owner
        $owner = $this->user('Aisha Owner', 'owner@example.com', $betaBiz);
        $this->assign($owner, $betaBiz, $r['owner']);

        // betaBiz — TenantAdmin
        $bizAdmin = $this->user('Rohan Admin', 'admin-biz@example.com', $betaBiz);
        $this->assign($bizAdmin, $betaBiz, $r['TenantAdmin']);

        // betaBiz — Viewer (read-only)
        $viewer = $this->user('Leena Viewer', 'viewer@example.com', $betaBiz);
        $this->assign($viewer, $betaBiz, $r['Viewer']);

        // deltaBiz — owner
        $deltaOwner = $this->user('Sam Delta Owner', 'owner-delta@example.com', $deltaBiz);
        $this->assign($deltaOwner, $deltaBiz, $r['owner']);

        // alphaCA — OwnerPartner
        $ownerPartner = $this->user('Neha OwnerPartner', 'ownerpartner@example.com', $alphaCA);
        $this->assign($ownerPartner, $alphaCA, $r['OwnerPartner']);

        // alphaCA — Auditor
        $auditor = $this->user('Meera Auditor', 'auditor@example.com', $alphaCA);
        $this->assign($auditor, $alphaCA, $r['Auditor']);

        // gammaCA — OwnerPartner
        $gammaPartner = $this->user('Sonal GammaPartner', 'partner-gamma@example.com', $gammaCA);
        $this->assign($gammaPartner, $gammaCA, $r['OwnerPartner']);

        // betaBiz — integration user (type=integration)
        $integration = $this->user('Tally Sync', 'tally@integration.example.com', $betaBiz, 'integration');
        $this->assign($integration, $betaBiz, $r['IntegrationUser']);

        // ─────────────────────────────────────────────────────────────
        // Multi-tenant users — different roles in different tenants
        // ─────────────────────────────────────────────────────────────

        // Priya: Manager @ betaBiz  |  CAManager @ alphaCA
        $priya = $this->user('Priya Multi', 'priya@example.com', $betaBiz);
        $this->assign($priya, $betaBiz, $r['Manager']);
        $this->assign($priya, $alphaCA,  $r['CAManager']);

        // Dev: Staff @ betaBiz  |  CAStaff @ alphaCA  |  Staff @ deltaBiz
        $dev = $this->user('Dev Multi', 'dev@example.com', $betaBiz);
        $this->assign($dev, $betaBiz,  $r['Staff']);
        $this->assign($dev, $alphaCA,  $r['CAStaff']);
        $this->assign($dev, $deltaBiz, $r['Staff']);

        // Kiran: ExternalAccountant @ betaBiz (external from alphaCA)  |  CAStaff @ alphaCA
        $kiran = $this->user('Kiran External', 'kiran@example.com', $betaBiz);
        $this->assign($kiran, $betaBiz, $r['ExternalAccountant'], 'external', $alphaCA->id);
        $this->assign($kiran, $alphaCA, $r['CAStaff']);

        // Vijay: CAManager @ alphaCA  |  Auditor @ gammaCA
        $vijay = $this->user('Vijay Multi', 'vijay@example.com', $alphaCA);
        $this->assign($vijay, $alphaCA, $r['CAManager']);
        $this->assign($vijay, $gammaCA, $r['Auditor']);

        // Amit: Staff @ deltaBiz  |  Viewer @ betaBiz
        $amit = $this->user('Amit Multi', 'amit@example.com', $deltaBiz);
        $this->assign($amit, $deltaBiz, $r['Staff']);
        $this->assign($amit, $betaBiz,  $r['Viewer']);

        // Ravi: owner @ deltaBiz  |  TenantAdmin @ betaBiz  |  CAStaff @ gammaCA
        $ravi = $this->user('Ravi Multi', 'ravi@example.com', $deltaBiz);
        $this->assign($ravi, $deltaBiz, $r['owner']);
        $this->assign($ravi, $betaBiz,  $r['TenantAdmin']);
        $this->assign($ravi, $gammaCA,  $r['CAStaff']);

        // Divya: Manager @ deltaBiz  |  Auditor @ alphaCA  |  Viewer @ betaBiz
        $divya = $this->user('Divya Multi', 'divya@example.com', $deltaBiz);
        $this->assign($divya, $deltaBiz, $r['Manager']);
        $this->assign($divya, $alphaCA,  $r['Auditor']);
        $this->assign($divya, $betaBiz,  $r['Viewer']);

        // ─── Tenant-specific permission overrides ─────────────────────

        // betaBiz: Manager gets read-only on clients/vendors (no create/edit/delete)
        $this->override($betaBiz->id, $r['Manager']->id, [
            'members.view',
            'clients.view',
            'vendors.view',
            'invoices.view',
            'reports.view',
            'reports.export',
            'integrations.view',
        ]);

        // deltaBiz: Staff gets vendor delete + client delete (elevated trust)
        $this->override($deltaBiz->id, $r['Staff']->id, [
            'clients.view',  'clients.create',  'clients.edit',  'clients.delete',
            'vendors.view',  'vendors.create',  'vendors.edit',  'vendors.delete',
            'invoices.view', 'invoices.create', 'invoices.edit',
            'reports.view',  'reports.export',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function user(string $name, string $email, ?Tenant $lastTenant = null, string $type = 'human'): User
    {
        return User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'type'              => $type,
            'status'            => 'active',
            'last_tenant_id'    => $lastTenant?->id,
        ]);
    }

    private function assign(
        User    $user,
        Tenant  $tenant,
        Role    $role,
        string  $memberType = 'internal',
        ?string $sourceTenantId = null,
    ): void {
        $user->tenants()->syncWithoutDetaching([
            $tenant->id => [
                'status'           => 'active',
                'member_type'      => $memberType,
                'source_tenant_id' => $sourceTenantId,
                'role_name'        => $role->name,
                'joined_at'        => now(),
            ],
        ]);

        TenantUserRole::firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['role_id' => $role->id]
        );
    }

    private function override(string $tenantId, int $roleId, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        foreach ($permissionIds as $permId) {
            TenantRolePermission::firstOrCreate([
                'tenant_id'     => $tenantId,
                'role_id'       => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }
}
