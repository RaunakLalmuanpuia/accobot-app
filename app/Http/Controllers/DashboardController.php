<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    // ── Admin dashboard (no business context) ─────────────────────────
    public function adminIndex()
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $businesses = Business::withCount('users')->get();

        $roleBreakdown = Role::withCount('users')->get()
            ->map(fn($r) => ['name' => $r->name, 'count' => $r->users_count]);

        return inertia('Dashboard/Admin', [
            'stats' => [
                'businesses' => Business::count(),
                'users'      => User::count(),
                'roles'      => Role::count(),
            ],
            'businesses'    => $businesses,
            'roleBreakdown' => $roleBreakdown,
        ]);
    }

    // ── Business dashboard (role-based) ───────────────────────────────
    public function index(Business $business)
    {
        $user     = auth()->user();
        $roleName = $user->businessRoles()
            ->where('business_id', $business->id)
            ->with('role')
            ->first()
            ?->role
            ?->name;

        // Admin can view any business as an observer
        if ($user->hasRole('admin')) {
            return $this->ownerView($business);
        }

        return match ($roleName) {
            'owner' => $this->ownerView($business),
            'ca'    => $this->caView($business),
            default => $this->userView($business),
        };
    }

    private function ownerView(Business $business)
    {
        $members = User::whereHas('businesses', fn($q) => $q->where('businesses.id', $business->id))
            ->with(['businessRoles' => fn($q) => $q->where('business_id', $business->id)->with('role')])
            ->get()
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->businessRoles->first()?->role?->name,
            ]);

        return inertia('Dashboard/Owner', [
            'business'      => $business,
            'stats'         => ['members' => $members->count()],
            'members'       => $members->take(5),
            'roleBreakdown' => $members
                ->groupBy('role')
                ->map(fn($g, $r) => ['role' => $r ?? 'Unassigned', 'count' => $g->count()])
                ->values(),
        ]);
    }

    private function caView(Business $business)
    {
        $permissions = auth()->user()
            ->businessRoles()
            ->where('business_id', $business->id)
            ->with('role.permissions')
            ->get()
            ->pluck('role.permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->sort()
            ->values();

        return inertia('Dashboard/CA', [
            'business'    => $business,
            'stats'       => [
                'members'     => User::whereHas('businesses', fn($q) => $q->where('businesses.id', $business->id))->count(),
                'permissions' => $permissions->count(),
            ],
            'permissions' => $permissions,
        ]);
    }

    private function userView(Business $business)
    {
        $permissions = auth()->user()
            ->businessRoles()
            ->where('business_id', $business->id)
            ->with('role.permissions')
            ->get()
            ->pluck('role.permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->sort()
            ->values();

        return inertia('Dashboard/User', [
            'business'    => $business,
            'permissions' => $permissions,
        ]);
    }
}
