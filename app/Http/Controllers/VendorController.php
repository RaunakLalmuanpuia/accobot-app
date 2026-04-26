<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Tenant $tenant)
    {
        return inertia('Vendors/Index', [
            'tenant'  => $tenant,
            'vendors' => Vendor::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes']),
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'tax_id'  => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $vendor = $tenant->vendors()->create($request->only('name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes'));

        AuditEvent::log('vendor.created', ['id' => $vendor->id, 'name' => $vendor->name]);

        return back();
    }

    public function update(Request $request, Tenant $tenant, Vendor $vendor)
    {
        abort_if($vendor->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'tax_id'  => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $vendor->update($request->only('name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes'));

        AuditEvent::log('vendor.updated', ['id' => $vendor->id, 'name' => $vendor->name]);

        return back();
    }

    public function destroy(Tenant $tenant, Vendor $vendor)
    {
        abort_if($vendor->tenant_id !== $tenant->id, 403);

        AuditEvent::log('vendor.deleted', ['id' => $vendor->id, 'name' => $vendor->name]);

        $vendor->delete();

        return back();
    }
}
