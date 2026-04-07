<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * POST /admin/impersonate/{user}
     * Super Admin starts impersonating a user.
     */
    public function start(Request $request, User $user)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        abort_if(auth()->id() === $user->id, 400, 'Cannot impersonate yourself.');
        abort_if(session()->has('impersonator_id'), 400, 'Already impersonating.');

        $impersonator = auth()->user();

        AuditEvent::log('impersonation.started', [
            'target_user_id'    => $user->id,
            'target_user_email' => $user->email,
        ], actorUserId: $impersonator->id);

        // Store impersonator identity before switching
        session(['impersonator_id' => $impersonator->id]);

        Auth::login($user);

        return redirect(route('profile.edit'))
            ->with('status', 'You are now impersonating ' . $user->name);
    }

    /**
     * POST /impersonate/stop
     * Return to the original admin identity.
     */
    public function stop(Request $request)
    {
        $impersonatorId = session('impersonator_id');

        abort_unless($impersonatorId, 403, 'Not impersonating anyone.');

        $impersonator = User::findOrFail($impersonatorId);

        AuditEvent::log('impersonation.ended', [
            'was_user_id'    => auth()->id(),
            'was_user_email' => auth()->user()?->email,
        ], actorUserId: $impersonatorId);

        session()->forget('impersonator_id');

        Auth::login($impersonator);

        return redirect(route('admin.dashboard'))
            ->with('status', 'Impersonation ended. You are back as ' . $impersonator->name);
    }
}
