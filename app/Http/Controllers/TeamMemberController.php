<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ChatRoom;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUserRole;
use App\Models\User;
use App\Services\ChatNotificationService;
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

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'email'   => 'required|email|max:255',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::where('email', $request->email)->first();

        $request->validate([
            'name'     => $user ? 'nullable' : 'required|string|max:255',
            'password' => $user ? 'nullable' : 'required|string|min:8',
        ]);

        $role = Role::findOrFail($request->role_id);

        if (! $user) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
                'type'     => 'human',
                'status'   => 'active',
            ]);
        }

        abort_if(
            $user->tenants()->where('tenants.id', $tenant->id)->exists(),
            422,
            'This user is already a member of this tenant.'
        );

        $user->tenants()->attach($tenant->id, [
            'status'      => 'active',
            'member_type' => 'internal',
            'role_name'   => $role->name,
            'joined_at'   => now(),
        ]);

        TenantUserRole::create([
            'user_id'   => $user->id,
            'tenant_id' => $tenant->id,
            'role_id'   => $role->id,
        ]);

        ChatRoom::addToGeneralIfQualified($tenant->id, $user->id, $role->name);

        AuditEvent::log('member.added', [
            'target_user_id' => $user->id,
            'role_id'        => $role->id,
        ]);

        ChatNotificationService::notify(
            tenantId:  $tenant->id,
            title:     'New Team Member',
            body:      "{$user->name} has been added to the team.",
            eventType: 'member.added',
            data:      ['user_id' => $user->id],
        );

        return back()->with('success', "{$user->name} added to the team.");
    }

    public function checkEmail(Request $request, Tenant $tenant)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        return response()->json([
            'exists' => (bool) $user,
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
