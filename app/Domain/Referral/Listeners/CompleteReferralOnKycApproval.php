<?php

declare(strict_types=1);

namespace App\Domain\Referral\Listeners;

use App\Domain\Compliance\Events\KycVerificationCompleted;
use App\Domain\Referral\Services\ReferralService;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class CompleteReferralOnKycApproval
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {
    }

    public function handle(KycVerificationCompleted $event): void
    {
        try {
            $user = User::where('uuid', $event->userUuid)->first();

            if (! $user) {
                Log::warning('CompleteReferralOnKycApproval: User not found', [
                    'user_uuid' => $event->userUuid,
                ]);

                return;
            }

            $this->referralService->completeReferral($user);
        } catch (Exception $e) {
            // Never throw from event listeners — log and continue
            Log::error('CompleteReferralOnKycApproval failed', [
                'user_uuid' => $event->userUuid,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
