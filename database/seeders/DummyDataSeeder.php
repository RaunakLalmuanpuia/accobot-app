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
        $tili     = Tenant::create(['name' => 'Tili',            'type' => 'business', 'status' => 'active']);
        $awab     = Tenant::create(['name' => 'Awab',            'type' => 'business', 'status' => 'active']);
        $eightsis = Tenant::create(['name' => 'Eightsis',        'type' => 'business', 'status' => 'active']);
        $alphaCA  = Tenant::create(['name' => 'Alpha Advisors',  'type' => 'ca_firm',  'status' => 'active']);
        $betaCA   = Tenant::create(['name' => 'Beta Consulting', 'type' => 'ca_firm',  'status' => 'active']);

        // ─── Role map ─────────────────────────────────────────────────
        $r = Role::all()->keyBy('name');

        // ─── Platform Admin ───────────────────────────────────────────
        $admin = $this->user('Platform Admin', 'admin@example.com');
        $admin->assignRole($r['admin']);

        // ─── Fela — owner of all three ────────────────────────────────
        $fela = $this->user('Fela', 'fela@example.com', $tili);
        $this->assign($fela, $tili,     $r['owner']);
        $this->assign($fela, $awab,     $r['owner']);
        $this->assign($fela, $eightsis, $r['owner']);

        // ─── Zoa — owner of Eightsis ──────────────────────────────────
        $zoa = $this->user('Zoa', 'zoa@example.com', $eightsis);
        $this->assign($zoa, $eightsis, $r['owner']);

        // ─── Zira — TenantAdmin of Tili and Awab ─────────────────────
        $zira = $this->user('Zira', 'zira@example.com', $tili);
        $this->assign($zira, $tili, $r['TenantAdmin']);
        $this->assign($zira, $awab, $r['TenantAdmin']);

        // ─── Dini — Manager of Tili, Awab, Eightsis ──────────────────
        $dini = $this->user('Dini', 'dini@example.com', $tili);
        $this->assign($dini, $tili,     $r['Manager']);
        $this->assign($dini, $awab,     $r['Manager']);
        $this->assign($dini, $eightsis, $r['Manager']);

        // ─── Raunak — member of Eightsis ─────────────────────────────
        $raunak = $this->user('Raunak', 'raunak@example.com', $eightsis);
        $this->assign($raunak, $eightsis, $r['Staff']);

        // ─── Dennis — member of Tili ──────────────────────────────────
        $dennis = $this->user('Dennis', 'dennis@example.com', $tili);
        $this->assign($dennis, $tili, $r['Staff']);

        // ─── Madini — member of Awab ──────────────────────────────────
        $madini = $this->user('Madini', 'madini@example.com', $awab);
        $this->assign($madini, $awab, $r['Staff']);

        // ─── CA1 — CA for Tili & Awab (Alpha Advisors) ───────────────
        $ca1 = $this->user('CA1', 'ca1@example.com', $alphaCA);
        $this->assign($ca1, $alphaCA, $r['OwnerPartner']);
        $this->assign($ca1, $tili, $r['ExternalAccountant'], 'external', $alphaCA->id);
        $this->assign($ca1, $awab, $r['ExternalAccountant'], 'external', $alphaCA->id);

        // ─── CA2 — CA for Eightsis (Beta Consulting) ─────────────────
        $ca2 = $this->user('CA2', 'ca2@example.com', $betaCA);
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
    }
}
