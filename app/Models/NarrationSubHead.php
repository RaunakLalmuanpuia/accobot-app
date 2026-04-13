<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NarrationSubHead extends Model
{
    protected $fillable = [
        'narration_head_id', 'name', 'slug', 'description',
        'ledger_code', 'ledger_name', 'requires_party',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'requires_party' => 'boolean',
    ];

    public function narrationHead(): BelongsTo
    {
        return $this->belongsTo(NarrationHead::class);
    }
}
