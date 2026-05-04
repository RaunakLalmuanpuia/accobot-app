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
    ];

    protected $casts = [
        'is_personal' => 'boolean',
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
}
