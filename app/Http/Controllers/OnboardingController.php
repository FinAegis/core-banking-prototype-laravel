<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    /**
     * Mark onboarding as completed for the authenticated user.
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->completeOnboarding();
        
        return response()->json([
            'message' => 'Onboarding completed successfully',
            'redirect' => route('dashboard')
        ]);
    }
    
    /**
     * Skip onboarding for now.
     */
    public function skip(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->completeOnboarding();
        
        return response()->json([
            'message' => 'Onboarding skipped',
            'redirect' => route('dashboard')
        ]);
    }
}
