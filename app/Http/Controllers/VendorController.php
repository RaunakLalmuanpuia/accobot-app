<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Tenant $tenant)
    {
        return inertia('Vendors/Index', [
            'tenant'  => $tenant,
            'vendors' => Vendor::orderBy('name')->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $tenant->vendors()->create($request->only('name', 'email', 'phone'));

        return back();
    }

    public function update(Request $request, Tenant $tenant, Vendor $vendor)
    {
        abort_if($vendor->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $vendor->update($request->only('name', 'email', 'phone'));

        return back();
    }

    public function destroy(Tenant $tenant, Vendor $vendor)
    {
        abort_if($vendor->tenant_id !== $tenant->id, 403);

        $vendor->delete();

        return back();
    }
}
