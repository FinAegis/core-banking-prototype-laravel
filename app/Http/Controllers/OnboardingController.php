<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Onboarding",
 *     description="User onboarding flow management"
 * )
 */
class OnboardingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/onboarding/complete",
     *     operationId="onboardingComplete",
     *     tags={"Onboarding"},
     *     summary="Complete onboarding",
     *     description="Marks the user onboarding as complete",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->completeOnboarding();

        return response()->json(
            [
                'message'  => 'Onboarding completed successfully',
                'redirect' => route('dashboard'),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/onboarding/skip",
     *     operationId="onboardingSkip",
     *     tags={"Onboarding"},
     *     summary="Skip onboarding",
     *     description="Skips the user onboarding flow",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function skip(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->completeOnboarding();

        return response()->json(
            [
                'message'  => 'Onboarding skipped',
                'redirect' => route('dashboard'),
            ]
        );
    }
}
