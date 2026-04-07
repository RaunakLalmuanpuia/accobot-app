<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    /**
     * POST /api/mobile/login
     * Returns a bearer token (shown once). Store in Keychain/Keystore.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            AuditEvent::log('auth.login.failure', [
                'email'  => $request->email,
                'reason' => 'invalid_credentials',
            ], actorType: 'human');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended.'],
            ]);
        }

        $token = $user->createToken($request->device_name, ['*']);

        AuditEvent::log('auth.token.created', [
            'token_id'    => $token->accessToken->id,
            'device_name' => $request->device_name,
        ]);

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'type'  => $user->type,
            ],
        ]);
    }

    /**
     * POST /api/mobile/logout
     * Revokes the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        AuditEvent::log('auth.token.revoked', [
            'token_id' => $token->id,
        ]);

        $token->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * GET /api/mobile/tokens
     * List all active tokens (device sessions) for the user.
     */
    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->select('id', 'name', 'last_used_at', 'created_at')
            ->orderByDesc('last_used_at')
            ->get();

        return response()->json(['tokens' => $tokens]);
    }

    /**
     * DELETE /api/mobile/tokens/{tokenId}
     * Revoke a specific token by ID.
     */
    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        abort_if(! $deleted, 404, 'Token not found.');

        AuditEvent::log('auth.token.revoked', ['token_id' => $tokenId]);

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * DELETE /api/mobile/tokens
     * Revoke all tokens (sign out all devices).
     */
    public function revokeAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        AuditEvent::log('auth.token.revoked', ['scope' => 'all_devices']);

        return response()->json(['message' => 'All tokens revoked.']);
    }

    /**
     * GET /api/mobile/me
     * Return the authenticated user with their tenants.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenants');

        return response()->json([
            'user'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'type'   => $user->type,
                'status' => $user->status,
            ],
            'tenants' => $user->tenants->map(fn($t) => [
                'id'     => $t->id,
                'name'   => $t->name,
                'type'   => $t->type,
                'status' => $t->status,
            ]),
        ]);
    }
}
