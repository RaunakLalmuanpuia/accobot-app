<?php

namespace App\Tools;

use App\Models\Product;
use App\Services\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageProductTool implements Tool
{
    private array $lastResults = [];
    private ?array $lastProduct = null;

    public function __construct(private EmbeddingService $embeddingService) {}

    public function description(): Stringable|string
    {
        return 'List, search, create, or update inventory products and services. '
            . 'Use action=search before creating to avoid duplicates. '
            . 'Name and unit_price are required when creating.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action'          => $schema->string()->description('What to do: "list", "search", "create", or "update".')->required(),
            'query'           => $schema->string()->description('Search term for action=search. Pass null otherwise.')->nullable()->required(),
            'filter'          => $schema->string()->description('Text filter for action=list. Pass null for no filter.')->nullable()->required(),
            'category'        => $schema->string()->description('Filter by main category for action=list. Pass null for all.')->nullable()->required(),
            'sub_category'    => $schema->string()->description('Filter by sub-category for action=list. Pass null for all.')->nullable()->required(),
            'main_group'      => $schema->string()->description('Filter by main group for action=list. Pass null for all.')->nullable()->required(),
            'sub_group'       => $schema->string()->description('Filter by sub group for action=list. Pass null for all.')->nullable()->required(),
            'include_inactive'=> $schema->boolean()->description('Include inactive products in action=list. Defaults to false.')->required(),
            'limit'           => $schema->integer()->description('Results to return (default: 20 for list, 5 for search). Pass null for default.')->nullable()->required(),
            'page'            => $schema->integer()->description('Page number for action=list (default: 1). Pass null for default.')->nullable()->required(),
            'product_id'      => $schema->integer()->description('Product ID — required for action=update. Pass null otherwise.')->nullable()->required(),
            'name'            => $schema->string()->description('Product name — required for action=create. Pass null to leave unchanged on update.')->nullable()->required(),
            'unit_price'      => $schema->number()->description('Price per unit — required for action=create. Pass null to leave unchanged on update.')->nullable()->required(),
            'description'     => $schema->string()->description('Product description. Pass null to skip or leave unchanged.')->nullable()->required(),
            'unit'            => $schema->string()->description('Unit of measure e.g. piece, hour, kg. Pass null to default to "unit" on create or leave unchanged on update.')->nullable()->required(),
            'tax_rate'        => $schema->number()->description('GST rate percentage (0, 5, 12, 18, 28). Determine automatically from product type. Pass null to leave unchanged on update.')->nullable()->required(),
            'sku'             => $schema->string()->description('SKU code. Pass null to auto-generate on create or leave unchanged on update.')->nullable()->required(),
            'stock_quantity'  => $schema->integer()->description('Stock quantity. Pass null to skip or leave unchanged.')->nullable()->required(),
            'is_active'       => $schema->boolean()->description('Set false to deactivate. Pass null to leave unchanged.')->nullable()->required(),
        ];
    }

    public function getLastResults(): array  { return $this->lastResults; }
    public function getLastProduct(): ?array { return $this->lastProduct; }

    private function tenantId(): ?string
    {
        return request()->route('tenant')?->id;
    }

    public function handle(Request $request): Stringable|string
    {
        $action = trim((string) ($request['action'] ?? ''));
        Log::info('ManageProductTool', ['action' => $action]);

        try {
            return match ($action) {
                'list'   => $this->list($request),
                'search' => $this->search($request),
                'create' => $this->create($request),
                'update' => $this->update($request),
                default  => "Unknown action \"{$action}\". Use list, search, create, or update.",
            };
        } catch (\Exception $e) {
            Log::error('ManageProductTool error', ['action' => $action, 'error' => $e->getMessage()]);
            return "Error: {$e->getMessage()}";
        }
    }

    private function list(Request $request): string
    {
        $filter          = $this->nullableString($request['filter'] ?? null);
        $category        = $this->nullableString($request['category'] ?? null);
        $subCategory     = $this->nullableString($request['sub_category'] ?? null);
        $mainGroup       = $this->nullableString($request['main_group'] ?? null);
        $subGroup        = $this->nullableString($request['sub_group'] ?? null);
        $includeInactive = (bool) ($request['include_inactive'] ?? false);
        $limit           = min((int) ($request['limit'] ?? 20) ?: 20, 100);
        $page            = max((int) ($request['page'] ?? 1) ?: 1, 1);

        $query = Product::query()->orderBy('category')->orderBy('sub_category')->orderBy('name');

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        if ($filter) {
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$filter}%")
                ->orWhere('description', 'ilike', "%{$filter}%")
                ->orWhere('category', 'ilike', "%{$filter}%")
                ->orWhere('sub_category', 'ilike', "%{$filter}%")
                ->orWhere('main_group', 'ilike', "%{$filter}%")
                ->orWhere('sub_group', 'ilike', "%{$filter}%")
                ->orWhere('sku', 'ilike', "%{$filter}%")
            );
        }

        if ($category)    $query->where('category', 'ilike', "%{$category}%");
        if ($subCategory) $query->where('sub_category', 'ilike', "%{$subCategory}%");
        if ($mainGroup)   $query->where('main_group', 'ilike', "%{$mainGroup}%");
        if ($subGroup)    $query->where('sub_group', 'ilike', "%{$subGroup}%");

        $total      = $query->count();
        $products   = $query->offset(($page - 1) * $limit)->limit($limit)->get();
        $totalPages = (int) ceil($total / $limit);

        if ($products->isEmpty()) {
            return $filter ? "No products found matching \"{$filter}\"." : 'No products in the inventory yet.';
        }

        $this->lastResults = $products->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
            'category' => $p->category, 'sub_category' => $p->sub_category,
            'main_group' => $p->main_group, 'sub_group' => $p->sub_group,
            'unit_price' => $p->unit_price, 'unit' => $p->unit,
            'tax_rate' => $p->tax_rate, 'stock_quantity' => $p->stock_quantity,
        ])->toArray();

        $header  = "| # | Name | Category | SKU | Price | GST | Stock |\n";
        $header .= "|---|------|----------|-----|-------|-----|-------|\n";

        $rows = $products->map(fn ($p, $i) => sprintf(
            '| %d | **%s** | %s | %s | ₹%s/%s | %s%% | %s |',
            ($page - 1) * $limit + $i + 1,
            $p->name, $p->category ?? '—', $p->sku ?? '—',
            number_format((float) $p->unit_price, 2), $p->unit,
            $p->tax_rate, $p->stock_quantity ?? '—'
        ))->implode("\n");

        return "Showing {$products->count()} of {$total} product(s) (page {$page}/{$totalPages})\n\n{$header}{$rows}";
    }

    private function search(Request $request): string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $limit = (int) ($request['limit'] ?? 5) ?: 5;
        $tid   = $this->tenantId();

        if (empty($query)) {
            return 'A search query is required.';
        }

        $queryEmbedding = $this->embeddingService->embedQuery($query);
        $vectorLiteral  = '[' . implode(',', $queryEmbedding) . ']';

        $rows = DB::select(
            "SELECT id, name, description, sku, unit, unit_price, tax_rate, stock_quantity,
                    category, sub_category, main_group, sub_group,
                    1 - (embedding <=> :vec::vector) AS similarity
             FROM products
             WHERE embedding IS NOT NULL AND is_active = true AND tenant_id = :tid
             ORDER BY embedding <=> :vec2::vector LIMIT :limit",
            ['vec' => $vectorLiteral, 'vec2' => $vectorLiteral, 'tid' => $tid, 'limit' => $limit]
        );

        if (empty($rows)) {
            $rows = DB::select(
                "SELECT id, name, description, sku, unit, unit_price, tax_rate, stock_quantity,
                        category, sub_category, main_group, sub_group, 0 AS similarity
                 FROM products
                 WHERE is_active = true AND tenant_id = :tid
                   AND (name ILIKE :q OR description ILIKE :q2 OR sku ILIKE :q3
                        OR category ILIKE :q4 OR sub_category ILIKE :q5)
                 LIMIT :limit",
                ['tid' => $tid, 'q' => "%{$query}%", 'q2' => "%{$query}%", 'q3' => "%{$query}%",
                 'q4' => "%{$query}%", 'q5' => "%{$query}%", 'limit' => $limit]
            );
        }

        if (empty($rows)) {
            return "No products found matching \"{$query}\". Try a different search term.";
        }

        $this->lastResults = [];
        $lines = [];

        foreach ($rows as $i => $row) {
            $score = round((float) $row->similarity * 100, 1);
            $this->lastResults[] = [
                'id' => $row->id, 'name' => $row->name, 'sku' => $row->sku,
                'unit' => $row->unit, 'unit_price' => $row->unit_price,
                'tax_rate' => $row->tax_rate, 'stock_quantity' => $row->stock_quantity,
                'category' => $row->category, 'similarity' => $score,
            ];
            $classification = implode(' › ', array_filter([
                $row->category, $row->sub_category, $row->main_group, $row->sub_group,
            ]));
            $lines[] = sprintf(
                "%d. **%s** (product_id: %d)\n   📂 %s | SKU: %s | 💰 ₹%s/%s | 🏷️ Tax: %s%% | 📊 Stock: %s | 🎯 Match: %s%%",
                $i + 1, $row->name, $row->id,
                $classification ?: 'Uncategorized', $row->sku ?? 'N/A',
                number_format((float) $row->unit_price, 2), $row->unit,
                $row->tax_rate, $row->stock_quantity, $score
            );
        }

        return 'Found ' . count($lines) . " product(s):\n\n" . implode("\n\n", $lines);
    }

    private function create(Request $request): string
    {
        $name        = $this->nullableString($request['name'] ?? null);
        $unitPrice   = $request['unit_price'] ?? null;
        $description = $this->nullableString($request['description'] ?? null);
        $unit        = $this->nullableString($request['unit'] ?? null) ?? 'unit';
        $rawTaxRate  = $request['tax_rate'] ?? null;
        $taxRate     = ($rawTaxRate !== null && $rawTaxRate !== 'null') ? (float) $rawTaxRate : 18.0;
        $category    = $this->nullableString($request['category'] ?? null);
        $subCategory = $this->nullableString($request['sub_category'] ?? null);
        $mainGroup   = $this->nullableString($request['main_group'] ?? null);
        $subGroup    = $this->nullableString($request['sub_group'] ?? null);
        $sku         = $this->nullableString($request['sku'] ?? null) ?? $this->generateSku($name ?? '');
        $tid         = $this->tenantId();

        if (! $name) {
            return 'Product name is required.';
        }
        if ($unitPrice === null || $unitPrice === 'null') {
            return 'Unit price is required.';
        }

        $existing = Product::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

        if ($existing) {
            $this->lastProduct = [
                'id' => $existing->id, 'name' => $existing->name, 'unit_price' => $existing->unit_price,
                'unit' => $existing->unit, 'tax_rate' => $existing->tax_rate,
                'description' => $existing->description, 'category' => $existing->category, 'sku' => $existing->sku,
            ];
            return "Product already exists: **{$existing->name}** — ₹{$existing->unit_price}/{$existing->unit} (product_id: {$existing->id}). Using existing record.";
        }

        $product = Product::create([
            'tenant_id'    => $tid,
            'name'         => $name, 'description' => $description, 'sku' => $sku,
            'unit'         => $unit, 'unit_price' => (float) $unitPrice, 'tax_rate' => $taxRate,
            'category'     => $category, 'sub_category' => $subCategory,
            'main_group'   => $mainGroup, 'sub_group' => $subGroup, 'is_active' => true,
        ]);

        try {
            $vector = '[' . implode(',', $this->embeddingService->embed($product->toEmbeddingText())) . ']';
            DB::statement('UPDATE products SET embedding = :vec::vector WHERE id = :id', ['vec' => $vector, 'id' => $product->id]);
        } catch (\Exception $e) {
            Log::warning('ManageProductTool: embedding failed', ['error' => $e->getMessage()]);
        }

        $this->lastProduct = [
            'id' => $product->id, 'name' => $product->name, 'unit_price' => $product->unit_price,
            'unit' => $product->unit, 'tax_rate' => $product->tax_rate,
            'description' => $product->description, 'category' => $product->category, 'sku' => $product->sku,
        ];

        return sprintf(
            "Product created successfully! **%s** — ₹%.2f/%s | Tax: %s%% (product_id: %d)",
            $product->name, (float) $product->unit_price, $product->unit, $product->tax_rate, $product->id
        );
    }

    private function update(Request $request): string
    {
        $productId = $request['product_id'] ?? null;
        if (! $productId || $productId === 'null') {
            return 'product_id is required for update.';
        }

        $product = Product::find((int) $productId);
        if (! $product) {
            return 'Product not found.';
        }

        $changes = [];

        foreach (['name', 'description', 'unit', 'sku', 'category', 'sub_category', 'main_group', 'sub_group'] as $field) {
            $val = $this->nullableString($request[$field] ?? null);
            if ($val !== null) $changes[$field] = $val;
        }

        foreach (['unit_price', 'tax_rate'] as $field) {
            $val = $request[$field] ?? null;
            if ($val !== null && $val !== 'null') $changes[$field] = (float) $val;
        }

        $stockVal = $request['stock_quantity'] ?? null;
        if ($stockVal !== null && $stockVal !== 'null') $changes['stock_quantity'] = (int) $stockVal;

        $activeVal = $request['is_active'] ?? null;
        if ($activeVal !== null && $activeVal !== 'null') $changes['is_active'] = (bool) $activeVal;

        if (empty($changes)) {
            return 'No changes provided — product was not updated.';
        }

        $product->update($changes);

        try {
            $product->refresh();
            $vector = '[' . implode(',', $this->embeddingService->embed($product->toEmbeddingText())) . ']';
            DB::statement('UPDATE products SET embedding = :vec::vector WHERE id = :id', ['vec' => $vector, 'id' => $product->id]);
        } catch (\Exception $e) {
            Log::warning('ManageProductTool: re-embedding failed', ['error' => $e->getMessage()]);
        }

        return "Product **{$product->name}** updated. Changed: " . implode(', ', array_keys($changes)) . '.';
    }

    private function generateSku(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        $prefix = str_pad($prefix, 3, 'X');
        return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === 'null' || trim((string) $value) === '') {
            return null;
        }
        return trim((string) $value);
    }
}
