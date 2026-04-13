<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone',
        'address', 'company', 'tax_id', 'notes', 'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toEmbeddingText(): string
    {
        $parts = array_filter([
            $this->name,
            $this->company ? "Company: {$this->company}" : null,
            $this->email,
            $this->phone,
            $this->address,
            $this->notes,
        ]);

        return implode('. ', $parts);
    }
}
