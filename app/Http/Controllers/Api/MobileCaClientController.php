<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CaClientInvitationMail;
use App\Mail\CaClientInviteRevokedMail;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CaClientLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MobileCaClientController extends Controller
{
    /**
     * GET /api/mobile/tenants/{tenant}/ca-businesses
     */
    public function index(Tenant $tenant): JsonResponse
    {
        abort_if($tenant->type !== 'ca_firm', 403, 'Only CA firms can access this endpoint.');

        $linkedBusinesses = Tenant::whereHas('users', function ($q) use ($tenant) {
            $q->where('tenant_user.member_type', 'external')
              ->where('tenant_user.source_tenant_id', $tenant->id);
        })
        ->orderBy('name')
        ->get(['id', 'name', 'status', 'gstin', 'city', 'state', 'created_at'])
        ->map(fn($t) => [
            'id'         => $t->id,
            'name'       => $t->name,
            'status'     => $t->status,
            'gstin'      => $t->gstin,
            'city'       => $t->city,
            'state'      => $t->state,
            'created_at' => $t->created_at,
        ]);

        $pendingInvites = Invitation::where('tenant_id', $tenant->id)
            ->where('invitation_type', 'ca_client')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'expires_at', 'created_at'])
            ->map(fn($i) => [
                'id'         => $i->id,
                'email'      => $i->email,
                'expires_at' => $i->expires_at,
                'created_at' => $i->created_at,
            ]);

        return response()->json([
            'linked_businesses' => $linkedBusinesses,
            'pending_invites'   => $pendingInvites,
        ]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/ca-businesses
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        abort_if($tenant->type !== 'ca_firm', 403, 'Only CA firms can access this endpoint.');

        $request->validate([
            'email'         => 'required|email|max:255',
            'business_name' => 'nullable|string|max:255',
        ]);

        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            $alreadyLinked = DB::table('tenant_user')
                ->where('user_id', $existingUser->id)
                ->where('member_type', 'external')
                ->where('source_tenant_id', $tenant->id)
                ->exists();

            if ($alreadyLinked) {
                return response()->json(['message' => 'This client is already connected to your firm.'], 422);
            }

            $businessTenant = $existingUser->tenants()
                ->where('tenants.type', 'business')
                ->where('tenant_user.member_type', 'internal')
                ->first();

            if ($businessTenant) {
                (new CaClientLinkService())->link($tenant, auth()->user(), $businessTenant);
                AuditEvent::log('ca.client.linked_direct', ['invited_email' => $request->email]);
                return response()->json(['message' => $existingUser->name . ' has been connected as a client.']);
            }
        }

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

        return response()->json(['message' => 'Invitation sent to ' . $request->email], 201);
    }

    /**
     * DELETE /api/mobile/tenants/{tenant}/ca-businesses/{businessTenant}
     */
    public function destroy(Tenant $tenant, Tenant $businessTenant): JsonResponse
    {
        abort_if($tenant->type !== 'ca_firm', 403);

        $isLinked = DB::table('tenant_user')
            ->where('tenant_id', $businessTenant->id)
            ->where('member_type', 'external')
            ->where('source_tenant_id', $tenant->id)
            ->exists();

        abort_if(! $isLinked, 404);

        (new CaClientLinkService())->unlink($tenant, $businessTenant);

        return response()->json(['message' => $businessTenant->name . ' has been disconnected.']);
    }

    /**
     * DELETE /api/mobile/tenants/{tenant}/ca-businesses/invites/{invitation}
     */
    public function revokeInvite(Tenant $tenant, Invitation $invitation): JsonResponse
    {
        abort_if($tenant->type !== 'ca_firm', 403);
        abort_if($invitation->tenant_id !== $tenant->id, 403);
        abort_if($invitation->invitation_type !== 'ca_client', 403);

        $invitation->load('tenant');
        $email = $invitation->email;

        $invitation->update(['status' => 'revoked']);

        Mail::to($email)->send(new CaClientInviteRevokedMail($invitation));

        AuditEvent::log('ca.client.invite_revoked', ['email' => $email]);

        return response()->json(['message' => 'Invitation to ' . $email . ' has been revoked.']);
    }
}
