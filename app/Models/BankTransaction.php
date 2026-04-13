<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'bank_account_name', 'transaction_date', 'bank_reference', 'raw_narration',
        'type', 'amount', 'balance_after',
        'narration_head_id', 'narration_sub_head_id',
        'narration_note', 'party_name', 'party_reference',
        'narration_source', 'review_status',
        'ai_confidence', 'ai_suggestions', 'ai_metadata',
        'is_reconciled', 'reconciled_invoice_id', 'reconciled_at',
        'applied_rule_id',
        'import_source', 'import_batch_id', 'dedup_hash', 'is_duplicate',
    ];

    protected $casts = [
        'transaction_date'  => 'date',
        'reconciled_at'     => 'date',
        'amount'            => 'decimal:2',
        'balance_after'     => 'decimal:2',
        'ai_confidence'     => 'decimal:2',
        'ai_suggestions'    => 'array',
        'ai_metadata'       => 'array',
        'is_reconciled'     => 'boolean',
        'is_duplicate'      => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function narrationHead(): BelongsTo
    {
        return $this->belongsTo(NarrationHead::class);
    }

    public function narrationSubHead(): BelongsTo
    {
        return $this->belongsTo(NarrationSubHead::class);
    }

    public function reconciledInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'reconciled_invoice_id');
    }

    public function appliedRule(): BelongsTo
    {
        return $this->belongsTo(NarrationRule::class, 'applied_rule_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public static function makeDedupHash(string $date, float $amount, string $type, string $ref = ''): string
    {
        return md5("{$date}|{$amount}|{$type}|{$ref}");
    }

    public function isCredit(): bool { return $this->type === 'credit'; }
    public function isDebit(): bool  { return $this->type === 'debit'; }
}
