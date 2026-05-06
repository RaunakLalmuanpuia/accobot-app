<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OnboardingController;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class MobileOnboardingController extends Controller
{
    /**
     * GET /api/mobile/tenants/{tenant}/onboarding
     */
    public function status(Tenant $tenant): JsonResponse
    {
        $controller = new OnboardingController();

        return response()->json([
            'dismissed' => $tenant->onboarding_dismissed_at !== null,
            'checklist' => $controller->buildChecklist($tenant),
        ]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/onboarding/dismiss
     */
    public function dismiss(Tenant $tenant): JsonResponse
    {
        $tenant->update(['onboarding_dismissed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
