<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MobileProfileController extends Controller
{
    /**
     * GET /api/mobile/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'phone'             => $user->phone,
                'pan'               => $user->pan,
                'type'              => $user->type,
                'status'            => $user->status,
                'email_verified_at' => $user->email_verified_at,
                'created_at'        => $user->created_at,
            ],
        ]);
    }

    /**
     * PATCH /api/mobile/profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'pan'   => ['nullable', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        AuditEvent::log('profile.updated');

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'phone'             => $user->phone,
                'pan'               => $user->pan,
                'type'              => $user->type,
                'status'            => $user->status,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    /**
     * POST /api/mobile/profile/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        // Load fresh from DB — Sanctum may cache the user without the password column
        $user = User::find($request->user()->id);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
                'errors'  => ['current_password' => ['The provided password is incorrect.']],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        AuditEvent::log('profile.password_changed');

        return response()->json(['message' => 'Password changed.']);
    }

    /**
     * DELETE /api/mobile/profile
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = User::find($request->user()->id);

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
                'errors'  => ['password' => ['The provided password is incorrect.']],
            ], 422);
        }

        AuditEvent::log('profile.deleted', ['user_id' => $user->id, 'email' => $user->email]);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }
}
