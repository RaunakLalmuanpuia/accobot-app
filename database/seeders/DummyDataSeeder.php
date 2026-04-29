<?php

namespace Database\Seeders;

use App\Models\ChatRoom;
use App\Models\Tenant;
use App\Models\TenantUserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Role map ─────────────────────────────────────────────────
        $r = Role::all()->keyBy('name');

        // ─── Platform Admin ───────────────────────────────────────────
        $admin = $this->user('Platform Admin', 'admin@example.com');
        $admin->assignRole($r['admin']);

        // ─── Create users first so we can set created_by_user_id ─────
        // (last_tenant_id is set after tenants are created)
        $fela   = $this->user('Fela',   'fela@example.com');
        $zoa    = $this->user('Zoa',    'zoa@example.com');
        $zira   = $this->user('Zira',   'zira@example.com');
        $dini   = $this->user('Dini',   'dini@example.com');
        $raunak = $this->user('Raunak', 'raunak@example.com');
        $dennis = $this->user('Dennis', 'dennis@example.com');
        $madini = $this->user('Madini', 'madini@example.com');
        $piyush = $this->user('Piyush', 'piyush@example.com');
        $ca1    = $this->user('CA1',    'ca1@example.com');
        $ca2    = $this->user('CA2',    'ca2@example.com');

        // ─── Tenants ──────────────────────────────────────────────────
        // Business tenants are shared (is_personal = false).
        $tili     = Tenant::create(['name' => 'Tili',     'type' => 'business', 'status' => 'active','is_personal' => true, 'created_by_user_id' => $fela->id]);
        $awab     = Tenant::create(['name' => 'Awab',     'type' => 'business', 'status' => 'active','is_personal' => true, 'created_by_user_id' => $fela->id]);
        $eightsis = Tenant::create(['name' => 'Eightsis', 'type' => 'business', 'status' => 'active','is_personal' => true, 'created_by_user_id' => $zoa->id]);

        // CA firm tenants are personal to the owning CA (is_personal = true).
        $alphaCA = Tenant::create(['name' => 'Alpha Advisors',  'type' => 'ca_firm', 'status' => 'active', 'is_personal' => true, 'created_by_user_id' => $ca1->id]);
        $betaCA  = Tenant::create(['name' => 'Beta Consulting', 'type' => 'ca_firm', 'status' => 'active', 'is_personal' => true, 'created_by_user_id' => $ca2->id]);

        // ─── Set last_tenant_id now that tenants exist ────────────────
        $fela->update(['last_tenant_id' => $tili->id]);
        $zoa->update(['last_tenant_id' => $eightsis->id]);
        $zira->update(['last_tenant_id' => $tili->id]);
        $dini->update(['last_tenant_id' => $tili->id]);
        $raunak->update(['last_tenant_id' => $eightsis->id]);
        $dennis->update(['last_tenant_id' => $tili->id]);
        $madini->update(['last_tenant_id' => $awab->id]);
        $piyush->update(['last_tenant_id' => $tili->id]);
        $ca1->update(['last_tenant_id' => $alphaCA->id]);
        $ca2->update(['last_tenant_id' => $betaCA->id]);

        // ─── Fela — owner of all three ────────────────────────────────
        $this->assign($fela, $tili,     $r['owner']);
        $this->assign($fela, $awab,     $r['owner']);
        $this->assign($fela, $eightsis, $r['owner']);

        // ─── Zoa — owner of Eightsis ──────────────────────────────────
        $this->assign($zoa, $eightsis, $r['owner']);

        // ─── Zira — TenantAdmin of Tili, Staff of Awab ───────────────
        $this->assign($zira, $tili, $r['TenantAdmin']);
        $this->assign($zira, $awab, $r['Staff']);

        // ─── Piyush — TenantAdmin of Tili and Awab ───────────────────
        $this->assign($piyush, $tili, $r['TenantAdmin']);
        $this->assign($piyush, $awab, $r['TenantAdmin']);

        // ─── Dini — Manager of Tili, Awab, Eightsis ──────────────────
        $this->assign($dini, $tili,     $r['Manager']);
        $this->assign($dini, $awab,     $r['Manager']);
        $this->assign($dini, $eightsis, $r['Manager']);

        // ─── Raunak — member of Eightsis ─────────────────────────────
        $this->assign($raunak, $eightsis, $r['Staff']);

        // ─── Dennis — member of Tili ──────────────────────────────────
        $this->assign($dennis, $tili, $r['Staff']);

        // ─── Madini — member of Awab ──────────────────────────────────
        $this->assign($madini, $awab, $r['Staff']);

        // ─── CA1 — owner of Alpha Advisors, services Tili & Awab ─────
        $this->assign($ca1, $alphaCA, $r['OwnerPartner']);
        $this->assign($ca1, $tili, $r['ExternalAccountant'], 'external', $alphaCA->id);
        $this->assign($ca1, $awab, $r['ExternalAccountant'], 'external', $alphaCA->id);

        // ─── CA2 — owner of Beta Consulting, services Eightsis ───────
        $this->assign($ca2, $betaCA, $r['OwnerPartner']);
        $this->assign($ca2, $eightsis, $r['ExternalAccountant'], 'external', $betaCA->id);
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
        User   $user,
        Tenant $tenant,
        Role   $role,
        string $memberType = 'internal',
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

        ChatRoom::addToGeneralIfQualified($tenant->id, $user->id, $role->name);
    }
}
