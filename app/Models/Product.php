<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'sku', 'unit',
        'unit_price', 'tax_rate', 'stock_quantity',
        'category', 'sub_category', 'main_group', 'sub_group',
        'is_active', 'embedding',
    ];

    protected $casts = [
        'unit_price'     => 'decimal:2',
        'tax_rate'       => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active'      => 'boolean',
        'embedding'      => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function toEmbeddingText(): string
    {
        $parts = array_filter([
            $this->name,
            $this->description,
            $this->category     ? "Main Category: {$this->category}"    : null,
            $this->sub_category ? "Sub-Category: {$this->sub_category}" : null,
            $this->main_group   ? "Main Group: {$this->main_group}"      : null,
            $this->sub_group    ? "Sub Group: {$this->sub_group}"        : null,
            $this->sku          ? "SKU: {$this->sku}"                    : null,
            "Unit: {$this->unit}",
            "Price: {$this->unit_price}",
        ]);

        return implode('. ', $parts);
    }
}
