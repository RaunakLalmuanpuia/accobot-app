<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'type', 'status', 'is_personal', 'created_by_user_id',
        'phone', 'email', 'website', 'gstin', 'pan', 'logo_url',
        'address_line1', 'address_line2', 'city', 'state', 'pincode',
        'onboarding_dismissed_at', 'tally_managed_by_ca',
    ];

    protected $casts = [
        'is_personal'              => 'boolean',
        'onboarding_dismissed_at'  => 'datetime',
        'tally_managed_by_ca'      => 'boolean',
    ];

    // type: business | ca_firm
    // status: active | suspended | pending_verification

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function narrationHeads(): HasMany
    {
        return $this->hasMany(NarrationHead::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUserRole::class);
    }

    public function chatRooms(): HasMany
    {
        return $this->hasMany(ChatRoom::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(TenantBankAccount::class)->orderByDesc('is_primary')->orderBy('id');
    }

    // For CA firms: businesses they manage externally
    public function linkedBusinessClients(): \Illuminate\Database\Eloquent\Builder
    {
        return static::whereHas('users', function ($q) {
            $q->where('tenant_user.member_type', 'external')
              ->where('tenant_user.source_tenant_id', $this->id);
        });
    }

    // For business tenants: CA firms that have access
    public function linkedCaFirms(): \Illuminate\Database\Eloquent\Builder
    {
        return static::whereIn('id',
            \Illuminate\Support\Facades\DB::table('tenant_user')
                ->where('tenant_id', $this->id)
                ->where('member_type', 'external')
                ->whereNotNull('source_tenant_id')
                ->pluck('source_tenant_id')
        );
    }
}
