<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Tenant $tenant)
    {
        return inertia('Products/Index', [
            'tenant'   => $tenant,
            'products' => Product::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get([
                    'id', 'name', 'description', 'sku', 'unit', 'unit_price',
                    'tax_rate', 'stock_quantity', 'category', 'sub_category',
                    'main_group', 'sub_group', 'is_active',
                ]),
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'sku'            => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:50',
            'unit_price'     => 'required|numeric|min:0',
            'tax_rate'       => 'nullable|numeric|min:0|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'category'       => 'nullable|string|max:255',
            'sub_category'   => 'nullable|string|max:255',
            'main_group'     => 'nullable|string|max:255',
            'sub_group'      => 'nullable|string|max:255',
            'is_active'      => 'boolean',
        ]);

        $tenant->products()->create($request->only(
            'name', 'description', 'sku', 'unit', 'unit_price',
            'tax_rate', 'stock_quantity', 'category', 'sub_category',
            'main_group', 'sub_group', 'is_active'
        ));

        return back();
    }

    public function update(Request $request, Tenant $tenant, Product $product)
    {
        abort_if($product->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'sku'            => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:50',
            'unit_price'     => 'required|numeric|min:0',
            'tax_rate'       => 'nullable|numeric|min:0|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'category'       => 'nullable|string|max:255',
            'sub_category'   => 'nullable|string|max:255',
            'main_group'     => 'nullable|string|max:255',
            'sub_group'      => 'nullable|string|max:255',
            'is_active'      => 'boolean',
        ]);

        $product->update($request->only(
            'name', 'description', 'sku', 'unit', 'unit_price',
            'tax_rate', 'stock_quantity', 'category', 'sub_category',
            'main_group', 'sub_group', 'is_active'
        ));

        return back();
    }

    public function destroy(Tenant $tenant, Product $product)
    {
        abort_if($product->tenant_id !== $tenant->id, 403);

        $product->delete();

        return back();
    }
}
