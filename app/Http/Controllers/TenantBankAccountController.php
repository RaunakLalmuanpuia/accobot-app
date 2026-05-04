<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantBankAccountController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name'            => ['required', 'string', 'max:255'],
            'account_holder_name'  => ['required', 'string', 'max:255'],
            'account_number'       => ['required', 'string', 'max:50'],
            'ifsc_code'            => ['required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_type'         => ['required', Rule::in(['savings', 'current', 'overdraft'])],
            'branch'               => ['nullable', 'string', 'max:255'],
        ]);

        $account = $tenant->bankAccounts()->create($validated);

        if ($tenant->bankAccounts()->count() === 1) {
            $account->update(['is_primary' => true]);
        }

        AuditEvent::log('tenant.bank_account_added', ['bank_name' => $account->bank_name]);

        return back()->with('success', 'Bank account added.');
    }

    public function update(Request $request, Tenant $tenant, TenantBankAccount $bankAccount): RedirectResponse
    {
        abort_if($bankAccount->tenant_id !== $tenant->id, 403);

        $validated = $request->validate([
            'bank_name'            => ['required', 'string', 'max:255'],
            'account_holder_name'  => ['required', 'string', 'max:255'],
            'account_number'       => ['required', 'string', 'max:50'],
            'ifsc_code'            => ['required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_type'         => ['required', Rule::in(['savings', 'current', 'overdraft'])],
            'branch'               => ['nullable', 'string', 'max:255'],
        ]);

        $bankAccount->update($validated);

        AuditEvent::log('tenant.bank_account_updated', ['id' => $bankAccount->id]);

        return back()->with('success', 'Bank account updated.');
    }

    public function setPrimary(Tenant $tenant, TenantBankAccount $bankAccount): RedirectResponse
    {
        abort_if($bankAccount->tenant_id !== $tenant->id, 403);

        $bankAccount->makePrimary();

        AuditEvent::log('tenant.bank_account_set_primary', ['id' => $bankAccount->id]);

        return back();
    }

    public function destroy(Tenant $tenant, TenantBankAccount $bankAccount): RedirectResponse
    {
        abort_if($bankAccount->tenant_id !== $tenant->id, 403);

        AuditEvent::log('tenant.bank_account_deleted', ['id' => $bankAccount->id, 'bank_name' => $bankAccount->bank_name]);

        $bankAccount->delete();

        // If deleted account was primary, promote the next one
        if ($bankAccount->is_primary) {
            $tenant->bankAccounts()->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Bank account removed.');
    }
}
