<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function loginForm()
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status'           => session('status'),
        ]);
    }

    public function login(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        AuditEvent::log('auth.login.success');

        if ($user->hasRole('admin')) {
            return redirect(route('admin.dashboard'));
        }

        $tenant = $user->lastTenant ?? $user->tenants()->first();

        return redirect(
            $tenant?->id ? route('dashboard', ['tenant' => $tenant->id]) : route('profile.edit')
        );
    }

    public function registerForm()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password'    => ['required', 'confirmed', Password::defaults()],
            'role'        => 'required|in:owner,ca',
            'tenant_name' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'type'     => 'human',
            'status'   => 'active',
        ]);

        event(new Registered($user));
        Auth::login($user);

        $tenantType = $request->role === 'ca' ? 'ca_firm' : 'business';
        $roleName   = $request->role === 'ca' ? 'OwnerPartner' : 'owner';
        $tenant     = $user->createPersonalTenant($roleName, $request->tenant_name, $tenantType);

        return redirect(route('dashboard', ['tenant' => $tenant->id]));
    }

    public function logout(Request $request)
    {
        AuditEvent::log('auth.logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
