<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Business $business)
    {
        return inertia('Vendors/Index', [
            'business' => $business,
            'vendors'  => Vendor::orderBy('name')->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function store(Request $request, Business $business)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $business->vendors()->create($request->only('name', 'email', 'phone'));

        return back();
    }

    public function update(Request $request, Business $business, Vendor $vendor)
    {
        abort_if($vendor->business_id !== $business->id, 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $vendor->update($request->only('name', 'email', 'phone'));

        return back();
    }

    public function destroy(Business $business, Vendor $vendor)
    {
        abort_if($vendor->business_id !== $business->id, 403);

        $vendor->delete();

        return back();
    }
}
