<?php

namespace App\Http\Controllers;

use App\Models\TallyConnection;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function status(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'dismissed' => $tenant->onboarding_dismissed_at !== null,
            'checklist' => $this->buildChecklist($tenant),
        ]);
    }

    public function dismiss(Tenant $tenant)
    {
        $tenant->update(['onboarding_dismissed_at' => now()]);

        return back();
    }

    public function buildChecklist(Tenant $tenant): array
    {
        if ($tenant->type === 'ca_firm') {
            $hasTeamMember = $tenant->users()
                ->where('tenant_user.member_type', 'internal')
                ->count() > 1;

            $hasClient = DB::table('tenant_user')
                ->where('source_tenant_id', $tenant->id)
                ->where('member_type', 'external')
                ->exists();

            return [
                ['key' => 'account_created', 'label' => 'Create your account',   'done' => true],
                ['key' => 'firm_profile',     'label' => 'Complete firm profile', 'done' => (bool) ($tenant->phone || $tenant->gstin || $tenant->pan), 'href' => route('settings.profile', $tenant)],
                ['key' => 'connect_tally',    'label' => 'Connect Tally',         'done' => $this->tallyConnected($tenant), 'href' => route('tally.connection.show', $tenant)],
                ['key' => 'add_team',         'label' => 'Invite team members',   'done' => $hasTeamMember, 'href' => route('team.index', $tenant)],
                ['key' => 'first_client',     'label' => 'Add your first client', 'done' => $hasClient, 'href' => route('ca.businesses.index', $tenant)],
            ];
        }

        $checklist = [
            ['key' => 'account_created',  'label' => 'Create your account',       'done' => true],
            ['key' => 'business_profile', 'label' => 'Complete business profile', 'done' => (bool) ($tenant->gstin || $tenant->pan || ($tenant->city && $tenant->state)), 'href' => route('settings.profile', $tenant)],
            ['key' => 'bank_account',     'label' => 'Add a bank account',         'done' => $tenant->bankAccounts()->exists(), 'href' => route('settings.profile', $tenant)],
        ];

        if (! $tenant->tally_managed_by_ca) {
            $checklist[] = ['key' => 'connect_tally', 'label' => 'Connect Tally', 'done' => $this->tallyConnected($tenant), 'href' => route('tally.connection.show', $tenant)];
        }

        return $checklist;
    }

    private function tallyConnected(Tenant $tenant): bool
    {
        return TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('inbound_token_last_used_at')
            ->exists();
    }
}
