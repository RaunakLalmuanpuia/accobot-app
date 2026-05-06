<?php

namespace App\Http\Controllers;

use App\Mail\CaClientInvitationMail;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CaClientLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ClientController extends Controller
{
    public function index(Tenant $tenant)
    {
        $clients = Client::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'linked_tenant_id', 'name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes']);

        // For CA firms, attach pending invite status per email
        $pendingEmails = [];
        if ($tenant->type === 'ca_firm') {
            $pendingEmails = Invitation::where('tenant_id', $tenant->id)
                ->where('invitation_type', 'ca_client')
                ->where('status', 'pending')
                ->pluck('status', 'email')
                ->toArray();
        }

        return inertia('Clients/Index', [
            'tenant'        => $tenant,
            'clients'       => $clients->map(fn($c) => array_merge($c->toArray(), [
                'invite_pending' => isset($pendingEmails[$c->email]),
            ])),
            'isCaFirm'      => $tenant->type === 'ca_firm',
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'tax_id'  => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $client = $tenant->clients()->create($request->only('name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes'));

        AuditEvent::log('client.created', ['id' => $client->id, 'name' => $client->name]);

        return back();
    }

    public function update(Request $request, Tenant $tenant, Client $client)
    {
        abort_if($client->tenant_id !== $tenant->id, 403);

        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'tax_id'  => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $client->update($request->only('name', 'email', 'phone', 'company', 'tax_id', 'address', 'notes'));

        AuditEvent::log('client.updated', ['id' => $client->id, 'name' => $client->name]);

        return back();
    }

    public function invite(Request $request, Tenant $tenant, Client $client)
    {
        abort_if($tenant->type !== 'ca_firm', 403);
        abort_if($client->tenant_id !== $tenant->id, 403);
        abort_if(! $client->email, 422, 'This client has no email address.');
        abort_if($client->linked_tenant_id, 422, 'This client is already connected to Accobot.');

        // If the user exists and has a business tenant, link directly
        $existingUser = User::where('email', $client->email)->first();
        if ($existingUser) {
            $alreadyLinked = DB::table('tenant_user')
                ->where('user_id', $existingUser->id)
                ->where('member_type', 'external')
                ->where('source_tenant_id', $tenant->id)
                ->exists();

            if ($alreadyLinked) {
                return back()->withErrors(['invite' => 'This client is already connected to your firm.']);
            }

            $businessTenant = $existingUser->tenants()
                ->where('tenants.type', 'business')
                ->where('tenant_user.member_type', 'internal')
                ->first();

            if ($businessTenant) {
                (new CaClientLinkService())->link($tenant, auth()->user(), $businessTenant);
                AuditEvent::log('ca.client.linked_from_client_record', [
                    'client_id'  => $client->id,
                    'client_name' => $client->name,
                ]);
                return back()->with('success', $client->name . ' has been connected to Accobot.');
            }
        }

        // Revoke any existing pending invite for this email
        Invitation::where('email', $client->email)
            ->where('tenant_id', $tenant->id)
            ->where('invitation_type', 'ca_client')
            ->where('status', 'pending')
            ->update(['status' => 'revoked']);

        [$rawToken, $tokenHash] = Invitation::generateToken();

        $invitation = Invitation::create([
            'tenant_id'       => $tenant->id,
            'invited_by'      => auth()->id(),
            'email'           => $client->email,
            'token_hash'      => $tokenHash,
            'expires_at'      => now()->addDays(14),
            'status'          => 'pending',
            'invitation_type' => 'ca_client',
            'meta'            => ['business_name' => $client->company ?? $client->name],
        ]);

        Mail::to($client->email)->send(new CaClientInvitationMail($invitation, $rawToken));

        AuditEvent::log('ca.client.invited_from_client_record', [
            'client_id'   => $client->id,
            'client_name' => $client->name,
        ]);

        return back()->with('success', 'Invitation sent to ' . $client->email);
    }

    public function destroy(Tenant $tenant, Client $client)
    {
        abort_if($client->tenant_id !== $tenant->id, 403);

        AuditEvent::log('client.deleted', ['id' => $client->id, 'name' => $client->name]);

        $client->delete();

        return back();
    }
}
