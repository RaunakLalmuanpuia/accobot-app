<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Business;
use App\Models\BusinessUserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class InvitationController extends Controller
{
    public function store(Request $request, Business $business)
    {
        $request->validate([
            'email'   => 'required|email|max:255',
            'role_id' => 'required|exists:roles,id',
        ]);

        // Block inviting someone already in this business
        $alreadyMember = User::where('email', $request->email)
            ->whereHas('businesses', fn($q) => $q->where('businesses.id', $business->id))
            ->exists();

        if ($alreadyMember) {
            return back()->withErrors(['email' => 'This person is already a member of this business.']);
        }

        // Replace any existing pending invite for the same email + business
        Invitation::where('email', $request->email)
            ->where('business_id', $business->id)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = Invitation::create([
            'business_id' => $business->id,
            'role_id'     => $request->role_id,
            'invited_by'  => auth()->id(),
            'email'       => $request->email,
            'token'       => Str::uuid(),
            'expires_at'  => now()->addDays(7),
        ]);

        Mail::to($request->email)->send(new InvitationMail($invitation));

        return back()->with('success', 'Invitation sent to ' . $request->email);
    }

    public function show(string $token)
    {
        $invitation = Invitation::with(['business', 'role', 'invitedBy'])
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->accepted_at) {
            return inertia('Invitations/Accept', [
                'invitation' => null,
                'error'      => 'This invitation has already been accepted.',
            ]);
        }

        if ($invitation->expires_at->isPast()) {
            return inertia('Invitations/Accept', [
                'invitation' => null,
                'error'      => 'This invitation has expired.',
            ]);
        }

        $accountExists = User::where('email', $invitation->email)->exists();

        // Existing user who isn't logged in → send to login, then back here
        if ($accountExists && !auth()->check()) {
            return redirect()->guest(route('invitation.show', $token));
        }

        return inertia('Invitations/Accept', [
            'invitation' => [
                'token'            => $invitation->token,
                'email'            => $invitation->email,
                'business_name'    => $invitation->business->name,
                'role_name'        => $invitation->role->name,
                'invited_by'       => $invitation->invitedBy->name,
                'expires_at'       => $invitation->expires_at->toDateString(),
                'requires_signup'  => !$accountExists,
            ],
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::with('business')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $accountExists = User::where('email', $invitation->email)->exists();

        if (!$accountExists) {
            // New user — validate and create account
            $request->validate([
                'name'     => 'required|string|max:255',
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            $user = User::create([
                'name'     => $request->name,
                'email'    => $invitation->email,
                'password' => Hash::make($request->password),
            ]);

            event(new Registered($user));
            Auth::login($user);
        }

        $user = auth()->user();

        if (!$user->businesses()->where('businesses.id', $invitation->business_id)->exists()) {
            $user->businesses()->attach($invitation->business_id);
            BusinessUserRole::create([
                'user_id'     => $user->id,
                'business_id' => $invitation->business_id,
                'role_id'     => $invitation->role_id,
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        return redirect(route('dashboard', ['business' => $invitation->business_id]))
            ->with('success', 'Welcome to ' . $invitation->business->name . '!');
    }

    public function decline(string $token)
    {
        $invitation = Invitation::where('token', $token)
            ->where('email', auth()->user()->email)
            ->whereNull('accepted_at')
            ->firstOrFail();

        $invitation->delete();

        return back();
    }

    public function destroy(Request $request, Business $business, Invitation $invitation)
    {
        abort_if($invitation->business_id !== $business->id, 403);

        $invitation->delete();

        return back();
    }
}
