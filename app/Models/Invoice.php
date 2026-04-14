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
        'amount_paid', 'amount_due',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'due_date'    => 'date',
        'subtotal'    => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'total'       => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due'  => 'decimal:2',
    ];

    // Ensure client_name accessor is included in JSON serialization (e.g. via Inertia)
    protected $appends = ['client_name'];

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

    // ── Accessors (for invoice matching compatibility) ─────────────────────

    public function getClientNameAttribute(): ?string
    {
        return $this->client?->name;
    }

    /** Alias for `total` — used by InvoiceMatchingService. */
    public function getTotalAmountAttribute(): float
    {
        return (float) $this->total;
    }

    /** Alias for `issue_date` — used by InvoiceMatchingService. */
    public function getInvoiceDateAttribute()
    {
        return $this->issue_date;
    }

    // ── Reconciliation helper ──────────────────────────────────────────────

    public function recordPayment(float $amount): void
    {
        $paid = min((float) $this->total, (float) $this->amount_paid + $amount);
        $due  = max(0.0, (float) $this->total - $paid);

        $status = $due <= 0.0
            ? 'paid'
            : ($paid > 0 ? 'partial' : $this->status);

        $this->update([
            'amount_paid' => $paid,
            'amount_due'  => $due,
            'status'      => $status,
        ]);
    }

    /**
     * Generate the next sequential invoice number for the given tenant.
     * Uses a lock to prevent duplicates under concurrent requests.
     */
    public static function generateNumber(string $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            // Lock a real row (not an aggregate) — FOR UPDATE is invalid with MAX() in PostgreSQL.
            // Order by the numeric part of the invoice number to get the highest, lock it, read it.
            $last = static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereRaw("invoice_number ~ '^INV-[0-9]+$'")
                ->orderByDesc(DB::raw("CAST(SUBSTRING(invoice_number FROM 5) AS INTEGER)"))
                ->lockForUpdate()
                ->value('invoice_number');

            $lastNum = $last ? (int) str_replace('INV-', '', $last) : 0;
            return 'INV-' . str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);
        });
    }
}
