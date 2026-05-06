<?php

namespace App\Http\Controllers;

use App\Mail\CaClientInvitationMail;
use App\Mail\CaClientInviteRevokedMail;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\TallyConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CaClientLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CaClientController extends Controller
{
    public function index(Tenant $tenant)
    {
        abort_if($tenant->type !== 'ca_firm', 403);

        $linkedBusinesses = Tenant::whereHas('users', function ($q) use ($tenant) {
            $q->where('tenant_user.member_type', 'external')
              ->where('tenant_user.source_tenant_id', $tenant->id);
        })
        ->orderBy('name')
        ->get(['id', 'name', 'status', 'gstin', 'city', 'state', 'created_at']);

        $tallyConnections = TallyConnection::withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $linkedBusinesses->pluck('id'))
            ->get(['tenant_id', 'inbound_token', 'is_active', 'inbound_token_last_used_at'])
            ->keyBy('tenant_id');

        $linkedBusinesses = $linkedBusinesses->map(function ($biz) use ($tallyConnections) {
            $conn = $tallyConnections->get($biz->id);
            return array_merge($biz->toArray(), [
                'tally_token'        => $conn?->inbound_token,
                'tally_active'       => $conn?->is_active ?? false,
                'tally_last_used_at' => $conn?->inbound_token_last_used_at,
            ]);
        });

        $pendingInvites = Invitation::where('tenant_id', $tenant->id)
            ->where('invitation_type', 'ca_client')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'expires_at', 'created_at']);

        return inertia('CaClients/Index', [
            'tenant'           => $tenant,
            'linkedBusinesses' => $linkedBusinesses,
            'pendingInvites'   => $pendingInvites,
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        abort_if($tenant->type !== 'ca_firm', 403);

        $request->validate([
            'email'         => 'required|email|max:255',
            'business_name' => 'nullable|string|max:255',
        ]);

        // If the user already exists and has a business tenant, link directly
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            $alreadyLinked = DB::table('tenant_user')
                ->where('user_id', $existingUser->id)
                ->where('member_type', 'external')
                ->where('source_tenant_id', $tenant->id)
                ->exists();

            if ($alreadyLinked) {
                return back()->withErrors(['email' => 'This client is already connected to your firm.']);
            }

            $businessTenant = $existingUser->tenants()
                ->where('tenants.type', 'business')
                ->where('tenant_user.member_type', 'internal')
                ->first();

            if ($businessTenant) {
                (new CaClientLinkService())->link($tenant, auth()->user(), $businessTenant);
                AuditEvent::log('ca.client.linked_direct', ['invited_email' => $request->email]);
                return back()->with('success', $existingUser->name . ' has been connected as a client.');
            }
        }

        // Revoke any existing pending invite for this email
        Invitation::where('email', $request->email)
            ->where('tenant_id', $tenant->id)
            ->where('invitation_type', 'ca_client')
            ->where('status', 'pending')
            ->update(['status' => 'revoked']);

        [$rawToken, $tokenHash] = Invitation::generateToken();

        $invitation = Invitation::create([
            'tenant_id'       => $tenant->id,
            'invited_by'      => auth()->id(),
            'email'           => $request->email,
            'token_hash'      => $tokenHash,
            'expires_at'      => now()->addDays(14),
            'status'          => 'pending',
            'invitation_type' => 'ca_client',
            'meta'            => ['business_name' => $request->business_name],
        ]);

        Mail::to($request->email)->send(new CaClientInvitationMail($invitation, $rawToken));

        AuditEvent::log('ca.client.invited', ['invited_email' => $request->email]);

        return back()->with('success', 'Invitation sent to ' . $request->email);
    }

    public function destroy(Tenant $tenant, Tenant $businessTenant)
    {
        abort_if($tenant->type !== 'ca_firm', 403);

        $isLinked = DB::table('tenant_user')
            ->where('tenant_id', $businessTenant->id)
            ->where('member_type', 'external')
            ->where('source_tenant_id', $tenant->id)
            ->exists();

        abort_if(! $isLinked, 404);

        (new CaClientLinkService())->unlink($tenant, $businessTenant);

        return back()->with('success', $businessTenant->name . ' has been disconnected.');
    }

    public function revokeInvite(Tenant $tenant, Invitation $invitation)
    {
        abort_if($tenant->type !== 'ca_firm', 403);
        abort_if($invitation->tenant_id !== $tenant->id, 403);
        abort_if($invitation->invitation_type !== 'ca_client', 403);

        $invitation->load('tenant');
        $email = $invitation->email;

        $invitation->update(['status' => 'revoked']);

        Mail::to($email)->send(new CaClientInviteRevokedMail($invitation));

        AuditEvent::log('ca.client.invite_revoked', ['email' => $email]);

        return back()->with('success', 'Invitation to ' . $email . ' has been revoked.');
    }
}
