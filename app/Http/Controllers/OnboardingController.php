<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Subscription\Events\OnboardingCompleted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Onboarding',
    description: 'User onboarding flow management'
)]
class OnboardingController extends Controller
{
        #[OA\Post(
            path: '/onboarding/complete',
            operationId: 'onboardingComplete',
            tags: ['Onboarding'],
            summary: 'Complete onboarding',
            description: 'Marks the user onboarding as complete',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->completeOnboarding();

        // Dispatch OnboardingCompleted for Plan B cue dispatch (Slice 4).
        // EnqueueProTrialReminderD1 listens and fires after 24h for eligible free-tier users.
        if ($user !== null) {
            Event::dispatch(new OnboardingCompleted((int) $user->getKey()));
        }

        return response()->json(
            [
                'message'  => 'Onboarding completed successfully',
                'redirect' => route('dashboard'),
            ]
        );
    }

        #[OA\Post(
            path: '/onboarding/skip',
            operationId: 'onboardingSkip',
            tags: ['Onboarding'],
            summary: 'Skip onboarding',
            description: 'Skips the user onboarding flow',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function skip(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->completeOnboarding();

        // Treat skip as onboarding completion for cue dispatch purposes.
        if ($user !== null) {
            Event::dispatch(new OnboardingCompleted((int) $user->getKey()));
        }

        return response()->json(
            [
                'message'  => 'Onboarding skipped',
                'redirect' => route('dashboard'),
            ]
        );
    }
}
