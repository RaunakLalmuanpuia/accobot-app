<?php

namespace App\Models;

use App\Models\BusinessRolePermission;
use App\Models\BusinessUserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'last_business_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_user');
    }

    public function personalBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'last_business_id')
            ->where('is_personal', true);
    }

    public function lastBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'last_business_id');
    }

    public function businessRoles(): HasMany
    {
        return $this->hasMany(BusinessUserRole::class);
    }

    // ── Personal business ─────────────────────────────────────────────

    /**
     * Auto-create a personal business for the user on registration.
     * Sets it as their last visited business.
     */
    public function createPersonalBusiness(string $roleName = 'owner', ?string $businessName = null): Business
    {
        $business = Business::create([
            'name'        => $businessName ?? $this->name . "'s Business",
            'is_personal' => true,
        ]);

        $ownerRole = Role::where('name', $roleName)->first();

        $this->businesses()->attach($business->id);

        BusinessUserRole::create([
            'user_id'     => $this->id,
            'business_id' => $business->id,
            'role_id'     => $ownerRole->id,
        ]);

        // Record as last visited (use query builder to bypass any fillable edge cases)
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->id)
            ->update(['last_business_id' => $business->id]);

        return $business;
    }

    // ── Permission helpers ────────────────────────────────────────────

    public function hasRoleInBusiness($roleName, ?int $businessId = null): bool
    {
        $businessId ??= request()->route('business')?->id;

        return $this->businessRoles()
            ->where('business_id', $businessId)
            ->whereHas('role', fn($q) => $q->where('name', $roleName))
            ->exists();
    }

    public function hasPermissionInBusiness(string $permission, Business $business): bool
    {
        $businessRole = $this->businessRoles()
            ->where('business_id', $business->id)
            ->with('role.permissions')
            ->first();

        if (! $businessRole) return false;

        $overrides = BusinessRolePermission::where('business_id', $business->id)
            ->where('role_id', $businessRole->role_id)
            ->with('permission')
            ->get();

        $effective = $overrides->isNotEmpty()
            ? $overrides->pluck('permission.name')
            : $businessRole->role->permissions->pluck('name');

        return $effective->contains($permission);
    }
}
