<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantProfileController extends Controller
{
    public function edit(Request $request, Tenant $tenant): Response
    {
        return Inertia::render('Settings/TenantProfile', [
            'tenant'       => $tenant->only([
                'id', 'name', 'type', 'status',
                'phone', 'email', 'website', 'gstin', 'pan', 'logo_url',
                'address_line1', 'address_line2', 'city', 'state', 'pincode',
            ]),
            'bankAccounts' => $tenant->bankAccounts()->get(['id', 'bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'account_type', 'branch', 'is_primary']),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'email'         => ['nullable', 'string', 'email', 'max:255'],
            'website'       => ['nullable', 'url', 'max:500'],
            'gstin'         => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'pan'           => ['nullable', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'logo_url'      => ['nullable', 'url', 'max:500'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'pincode'       => ['nullable', 'string', 'max:10'],
        ]);

        $tenant->fill($validated)->save();

        AuditEvent::log('tenant.profile_updated');

        return back()->with('success', 'Profile saved successfully.');
    }
}
