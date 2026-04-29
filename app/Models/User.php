<?php

namespace App\Models;

use App\Models\ChatRoom;
use App\Models\TenantRolePermission;
use App\Models\TenantUserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    // type: human | integration
    // status: active | platform_suspended

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'last_tenant_id',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user');
    }

    public function lastTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'last_tenant_id');
    }

    public function tenantRoles(): HasMany
    {
        return $this->hasMany(TenantUserRole::class);
    }

    // ── Personal tenant ───────────────────────────────────────────────

    public function createPersonalTenant(string $roleName = 'owner', ?string $tenantName = null, string $tenantType = 'business'): Tenant
    {
        $tenant = Tenant::create([
            'name'               => $tenantName ?? $this->name . "'s Business",
            'type'               => $tenantType,
            'status'             => 'active',
            'is_personal'        => true,
            'created_by_user_id' => $this->id,
        ]);

        $ownerRole = Role::where('name', $roleName)->first();

        $this->tenants()->attach($tenant->id, [
            'status'      => 'active',
            'member_type' => 'internal',
            'role_name'   => $roleName,
            'joined_at'   => now(),
        ]);

        TenantUserRole::create([
            'user_id'   => $this->id,
            'tenant_id' => $tenant->id,
            'role_id'   => $ownerRole->id,
        ]);

        ChatRoom::addToGeneralIfQualified($tenant->id, $this->id, $roleName);

        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->id)
            ->update(['last_tenant_id' => $tenant->id]);

        AuditEvent::log('tenant.created', [
            'tenant_name' => $tenant->name,
            'tenant_type' => $tenantType,
        ], tenantId: $tenant->id);

        return $tenant;
    }

    // ── Permission helpers ────────────────────────────────────────────

    public function hasRoleInTenant(string $roleName, ?string $tenantId = null): bool
    {
        $tenantId ??= request()->route('tenant')?->id;

        return $this->tenantRoles()
            ->where('tenant_id', $tenantId)
            ->whereHas('role', fn($q) => $q->where('name', $roleName))
            ->exists();
    }

    public function hasPermissionInTenant(string $permission, Tenant $tenant): bool
    {
        $tenantRole = $this->tenantRoles()
            ->where('tenant_id', $tenant->id)
            ->with('role.permissions')
            ->first();

        if (! $tenantRole) return false;

        $overrides = TenantRolePermission::where('tenant_id', $tenant->id)
            ->where('role_id', $tenantRole->role_id)
            ->with('permission')
            ->get();

        $effective = $overrides->isNotEmpty()
            ? $overrides->pluck('permission.name')
            : $tenantRole->role->permissions->pluck('name');

        return $effective->contains($permission);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_members')
            ->withPivot('role', 'joined_at', 'last_read_message_id')
            ->withTimestamps();
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
