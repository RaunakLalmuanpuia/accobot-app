<?php

namespace App\Models;

use App\Jobs\GenerateEmbeddingJob;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone',
        'address', 'company', 'tax_id', 'notes', 'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(fn (self $m) => GenerateEmbeddingJob::dispatch(self::class, $m->id));
        static::updated(fn (self $m) => GenerateEmbeddingJob::dispatch(self::class, $m->id));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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
