<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NarrationHead extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'type', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subHeads(): HasMany
    {
        return $this->hasMany(NarrationSubHead::class)->orderBy('sort_order');
    }

    public function activeSubHeads(): HasMany
    {
        return $this->hasMany(NarrationSubHead::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
