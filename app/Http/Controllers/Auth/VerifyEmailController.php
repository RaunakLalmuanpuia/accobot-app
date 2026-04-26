<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->homeUrl($user));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            AuditEvent::log('auth.email.verified');
        }

        return redirect()->intended($this->homeUrl($user));
    }
}
