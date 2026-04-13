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
        'narration_note', 'party_name',
        'narration_source', 'review_status',
        'import_source', 'import_batch_id', 'dedup_hash',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
        'balance_after'    => 'decimal:2',
    ];

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
}
