<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class InvitationController extends Controller
{
    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'email'   => 'required|email|max:255',
            'role_id' => 'required|exists:roles,id',
        ]);

        $alreadyMember = User::where('email', $request->email)
            ->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenant->id))
            ->exists();

        if ($alreadyMember) {
            return back()->withErrors(['email' => 'This person is already a member of this tenant.']);
        }

        // Revoke any existing pending invite
        Invitation::where('email', $request->email)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->update(['status' => 'revoked']);

        [$rawToken, $tokenHash] = Invitation::generateToken();

        $invitation = Invitation::create([
            'tenant_id'  => $tenant->id,
            'role_id'    => $request->role_id,
            'invited_by' => auth()->id(),
            'email'      => $request->email,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(7),
            'status'     => 'pending',
        ]);

        // Pass raw token to the mailer (never stored)
        Mail::to($request->email)->send(new InvitationMail($invitation, $rawToken));

        AuditEvent::log('member.invited', [
            'invited_email' => $request->email,
            'role_id'       => $request->role_id,
        ]);

        return back()->with('success', 'Invitation sent to ' . $request->email);
    }

    public function show(string $rawToken)
    {
        $invitation = Invitation::findByRawToken($rawToken);

        if (! $invitation) {
            return inertia('Invitations/Accept', ['invitation' => null, 'error' => 'Invalid invitation link.']);
        }

        $invitation->load(['tenant', 'role', 'invitedBy']);

        if ($invitation->status === 'accepted') {
            return inertia('Invitations/Accept', ['invitation' => null, 'error' => 'This invitation has already been accepted.']);
        }

        if (! $invitation->isPending()) {
            return inertia('Invitations/Accept', ['invitation' => null, 'error' => 'This invitation has expired or been revoked.']);
        }

        $accountExists = User::where('email', $invitation->email)->exists();

        if ($accountExists && ! auth()->check()) {
            return redirect()->guest(route('invitation.show', $rawToken));
        }

        return inertia('Invitations/Accept', [
            'invitation' => [
                'token'           => $rawToken,
                'email'           => $invitation->email,
                'tenant_name'     => $invitation->tenant->name,
                'role_name'       => $invitation->role->name,
                'invited_by'      => $invitation->invitedBy->name,
                'expires_at'      => $invitation->expires_at->toDateString(),
                'requires_signup' => ! $accountExists,
            ],
        ]);
    }

    public function accept(Request $request, string $rawToken)
    {
        $invitation = Invitation::findByRawToken($rawToken);

        abort_if(! $invitation || ! $invitation->isPending(), 404);

        $accountExists = User::where('email', $invitation->email)->exists();

        if (! $accountExists) {
            $request->validate([
                'name'     => 'required|string|max:255',
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            $user = User::create([
                'name'     => $request->name,
                'email'    => $invitation->email,
                'password' => Hash::make($request->password),
                'type'     => 'human',
                'status'   => 'active',
            ]);

            event(new Registered($user));
            Auth::login($user);
        }

        $user = auth()->user();

        abort_if($user->email !== $invitation->email, 403, 'This invitation was sent to a different email address.');

        if (! $user->tenants()->where('tenants.id', $invitation->tenant_id)->exists()) {
            $user->tenants()->attach($invitation->tenant_id, [
                'status'             => 'active',
                'member_type'        => 'internal',
                'role_name'          => Role::find($invitation->role_id)?->name,
                'invited_by_user_id' => $invitation->invited_by,
                'joined_at'          => now(),
            ]);

            TenantUserRole::create([
                'user_id'   => $user->id,
                'tenant_id' => $invitation->tenant_id,
                'role_id'   => $invitation->role_id,
            ]);
        }

        $invitation->update(['accepted_at' => now(), 'status' => 'accepted']);

        AuditEvent::log('member.invite.accepted', [
            'invitation_id' => $invitation->id,
        ], tenantId: $invitation->tenant_id);

        return redirect(route('dashboard', ['tenant' => $invitation->tenant_id]))
            ->with('success', 'Welcome to ' . $invitation->tenant->name . '!');
    }

    public function decline(string $rawToken)
    {
        $invitation = Invitation::findByRawToken($rawToken);

        abort_if(
            ! $invitation || $invitation->email !== auth()->user()->email || ! $invitation->isPending(),
            404
        );

        $invitation->update(['status' => 'revoked']);

        return back();
    }

    // ── Bell-dropdown: accept/decline by ID (requires auth, email must match) ──

    public function acceptById(Invitation $invitation)
    {
        abort_if(! $invitation->isPending(), 404);
        abort_if(auth()->user()->email !== $invitation->email, 403);

        if (! auth()->user()->tenants()->where('tenants.id', $invitation->tenant_id)->exists()) {
            auth()->user()->tenants()->attach($invitation->tenant_id, [
                'status'             => 'active',
                'member_type'        => 'internal',
                'role_name'          => Role::find($invitation->role_id)?->name,
                'invited_by_user_id' => $invitation->invited_by,
                'joined_at'          => now(),
            ]);

            TenantUserRole::create([
                'user_id'   => auth()->id(),
                'tenant_id' => $invitation->tenant_id,
                'role_id'   => $invitation->role_id,
            ]);
        }

        $invitation->update(['accepted_at' => now(), 'status' => 'accepted']);

        AuditEvent::log('member.invite.accepted', [
            'invitation_id' => $invitation->id,
        ], tenantId: $invitation->tenant_id);

        return redirect(route('dashboard', ['tenant' => $invitation->tenant_id]));
    }

    public function declineById(Invitation $invitation)
    {
        abort_if(auth()->user()->email !== $invitation->email, 403);
        abort_if(! $invitation->isPending(), 404);

        $invitation->update(['status' => 'revoked']);

        return back();
    }

    public function destroy(Request $request, Tenant $tenant, Invitation $invitation)
    {
        abort_if($invitation->tenant_id !== $tenant->id, 403);

        $invitation->update(['status' => 'revoked']);

        return back();
    }
}
