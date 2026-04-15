<?php

namespace App\Http\Controllers;

use App\Models\NarrationHead;
use App\Models\NarrationSubHead;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NarrationHeadController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────

    private function uniqueHeadSlug(string $base, string $tenantId): string
    {
        $slug = $base;
        $i    = 2;
        while (NarrationHead::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function uniqueSubHeadSlug(int $headId, string $base): string
    {
        $slug = $base;
        $i    = 2;
        while (NarrationSubHead::where('narration_head_id', $headId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    // ── Heads ─────────────────────────────────────────────────────

    public function index(Tenant $tenant)
    {
        return inertia('NarrationHeads/Index', [
            'tenant' => $tenant,
            'heads'  => NarrationHead::where('tenant_id', $tenant->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->with(['subHeads' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
                ->get(),
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:credit,debit,both',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $slug = $this->uniqueHeadSlug(Str::slug($request->name), $tenant->id);

        $tenant->narrationHeads()->create([
            'name'        => $request->name,
            'slug'        => $slug,
            'type'        => $request->type,
            'description' => $request->description,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return back();
    }

    public function update(Request $request, Tenant $tenant, NarrationHead $narrationHead)
    {
        abort_if($narrationHead->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:credit,debit,both',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $narrationHead->update([
            'name'        => $request->name,
            'type'        => $request->type,
            'description' => $request->description,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return back();
    }

    public function destroy(Tenant $tenant, NarrationHead $narrationHead)
    {
        abort_if($narrationHead->tenant_id !== $tenant->id, 403);

        $narrationHead->subHeads()->delete();
        $narrationHead->delete();

        return back();
    }

    // ── Sub-heads ─────────────────────────────────────────────────

    public function storeSubHead(Request $request, Tenant $tenant, NarrationHead $narrationHead)
    {
        abort_if($narrationHead->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'           => 'required|string|max:255',
            'ledger_code'    => 'nullable|string|max:100',
            'ledger_name'    => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'requires_party' => 'boolean',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
        ]);

        $slug = $this->uniqueSubHeadSlug($narrationHead->id, Str::slug($request->name));

        $narrationHead->subHeads()->create([
            'name'           => $request->name,
            'slug'           => $slug,
            'ledger_code'    => $request->ledger_code,
            'ledger_name'    => $request->ledger_name,
            'description'    => $request->description,
            'requires_party' => $request->boolean('requires_party', false),
            'sort_order'     => $request->sort_order ?? 0,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return back();
    }

    public function updateSubHead(Request $request, Tenant $tenant, NarrationHead $narrationHead, NarrationSubHead $narrationSubHead)
    {
        abort_if($narrationHead->tenant_id !== $tenant->id, 403);
        abort_if($narrationSubHead->narration_head_id !== $narrationHead->id, 403);

        $request->validate([
            'name'           => 'required|string|max:255',
            'ledger_code'    => 'nullable|string|max:100',
            'ledger_name'    => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'requires_party' => 'boolean',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
        ]);

        $narrationSubHead->update([
            'name'           => $request->name,
            'ledger_code'    => $request->ledger_code,
            'ledger_name'    => $request->ledger_name,
            'description'    => $request->description,
            'requires_party' => $request->boolean('requires_party', false),
            'sort_order'     => $request->sort_order ?? 0,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return back();
    }

    public function destroySubHead(Tenant $tenant, NarrationHead $narrationHead, NarrationSubHead $narrationSubHead)
    {
        abort_if($narrationHead->tenant_id !== $tenant->id, 403);
        abort_if($narrationSubHead->narration_head_id !== $narrationHead->id, 403);

        $narrationSubHead->delete();

        return back();
    }
}
