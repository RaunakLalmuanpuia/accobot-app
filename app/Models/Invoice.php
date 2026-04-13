<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'invoice_number', 'client_id',
        'issue_date', 'due_date', 'status',
        'subtotal', 'tax_amount', 'total', 'currency', 'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date'   => 'date',
        'subtotal'   => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Generate the next sequential invoice number globally unique.
     * Uses a lock to prevent duplicates under concurrent requests.
     */
    public static function generateNumber(): string
    {
        return DB::transaction(function () {
            $last = static::withoutGlobalScopes()->orderByDesc('id')->lockForUpdate()->value('id') ?? 0;
            return 'INV-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
        });
    }
}
