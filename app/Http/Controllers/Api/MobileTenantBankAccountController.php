<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileTenantBankAccountController extends Controller
{
    /**
     * GET /api/mobile/tenants/{tenant}/bank-accounts
     */
    public function index(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'bank_accounts' => $tenant->bankAccounts()->get()->map(fn ($a) => $this->accountData($a)),
        ]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/bank-accounts
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'bank_name'           => ['required', 'string', 'max:255'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'account_number'      => ['required', 'string', 'max:50'],
            'ifsc_code'           => ['required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_type'        => ['required', Rule::in(['savings', 'current', 'overdraft'])],
            'branch'              => ['nullable', 'string', 'max:255'],
        ]);

        $account = $tenant->bankAccounts()->create($validated);

        if ($tenant->bankAccounts()->count() === 1) {
            $account->update(['is_primary' => true]);
        }

        AuditEvent::log('tenant.bank_account_added', ['bank_name' => $account->bank_name]);

        return response()->json([
            'message'      => 'Bank account added.',
            'bank_account' => $this->accountData($account->fresh()),
        ], 201);
    }

    /**
     * PUT /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}
     */
    public function update(Request $request, Tenant $tenant, TenantBankAccount $bankAccount): JsonResponse
    {
        abort_if($bankAccount->tenant_id !== $tenant->id, 403);

        $validated = $request->validate([
            'bank_name'           => ['sometimes', 'required', 'string', 'max:255'],
            'account_holder_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_number'      => ['sometimes', 'required', 'string', 'max:50'],
            'ifsc_code'           => ['sometimes', 'required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_type'        => ['sometimes', 'required', Rule::in(['savings', 'current', 'overdraft'])],
            'branch'              => ['nullable', 'string', 'max:255'],
        ]);

        $bankAccount->update($validated);

        AuditEvent::log('tenant.bank_account_updated', ['id' => $bankAccount->id]);

        return response()->json([
            'message'      => 'Bank account updated.',
            'bank_account' => $this->accountData($bankAccount->fresh()),
        ]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}/set-primary
     */
    public function setPrimary(Tenant $tenant, TenantBankAccount $bankAccount): JsonResponse
    {
        abort_if($bankAccount->tenant_id !== $tenant->id, 403);

        $bankAccount->makePrimary();

        AuditEvent::log('tenant.bank_account_set_primary', ['id' => $bankAccount->id]);

        return response()->json(['message' => 'Primary account updated.']);
    }

    /**
     * DELETE /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}
     */
    public function destroy(Tenant $tenant, TenantBankAccount $bankAccount): JsonResponse
    {
        abort_if($bankAccount->tenant_id !== $tenant->id, 403);

        $wasPrimary = $bankAccount->is_primary;

        AuditEvent::log('tenant.bank_account_deleted', ['id' => $bankAccount->id, 'bank_name' => $bankAccount->bank_name]);

        $bankAccount->delete();

        if ($wasPrimary) {
            $tenant->bankAccounts()->first()?->update(['is_primary' => true]);
        }

        return response()->json(['message' => 'Bank account removed.']);
    }

    private function accountData(TenantBankAccount $account): array
    {
        return [
            'id'                   => $account->id,
            'bank_name'            => $account->bank_name,
            'account_holder_name'  => $account->account_holder_name,
            'account_number'       => $account->account_number,
            'ifsc_code'            => $account->ifsc_code,
            'account_type'         => $account->account_type,
            'branch'               => $account->branch,
            'is_primary'           => $account->is_primary,
        ];
    }
}
