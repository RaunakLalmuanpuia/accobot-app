<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class TeamMemberController extends Controller
{
    public function index(Tenant $tenant)
    {
        $members = User::whereHas('tenants', fn($q) => $q->where('tenants.id', $tenant->id))
            ->with(['tenantRoles' => fn($q) => $q->where('tenant_id', $tenant->id)->with('role')])
            ->get()
            ->map(fn($user) => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->tenantRoles->first()?->role
                    ? ['id' => $user->tenantRoles->first()->role->id, 'name' => $user->tenantRoles->first()->role->name]
                    : null,
            ]);

        $pendingInvitations = Invitation::with('role')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get()
            ->map(fn($inv) => [
                'id'         => $inv->id,
                'email'      => $inv->email,
                'role_name'  => $inv->role->name,
                'expires_at' => $inv->expires_at->toDateString(),
            ]);

        return inertia('Settings/Team', [
            'tenant'             => $tenant,
            'members'            => $members,
            'pendingInvitations' => $pendingInvitations,
            'roles'              => Role::select('id', 'name')
                ->where('name', '!=', 'admin')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Tenant $tenant, User $user)
    {
        $request->validate(['role_id' => 'required|exists:roles,id']);

        $old = TenantUserRole::where('user_id', $user->id)->where('tenant_id', $tenant->id)->first();

        TenantUserRole::updateOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['role_id' => $request->role_id]
        );

        AuditEvent::log('member.role.changed', [
            'target_user_id' => $user->id,
            'from_role_id'   => $old?->role_id,
            'to_role_id'     => $request->role_id,
        ]);

        return back();
    }

    public function destroy(Tenant $tenant, User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot remove yourself.');

        TenantUserRole::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->delete();

        $user->tenants()->detach($tenant->id);

        AuditEvent::log('member.removed', ['target_user_id' => $user->id]);

        return back();
    }
}
