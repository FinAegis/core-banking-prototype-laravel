<?php

namespace App\Domain\Compliance\Activities;

use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use Workflow\Activity;

class KycVerificationActivity extends Activity
{
    public function __construct(
        private KycService $kycService
    ) {
    }

    /**
     * Execute KYC verification activity.
     *
     * @param  array  $input  Expected format: [
     *                        'user_uuid' => string,
     *                        'action' => string ('approve' or 'reject'),
     *                        'verified_by' => string,
     *                        'reason' => string (required for reject),
     *                        'options' => array (optional for approve)
     *                        ]
     */
    public function execute(array $input): array
    {
        $userUuid = $input['user_uuid'] ?? null;
        $action = $input['action'] ?? null;
        $verifiedBy = $input['verified_by'] ?? null;

        if (! $userUuid || ! $action || ! $verifiedBy) {
            throw new \InvalidArgumentException('Missing required parameters: user_uuid, action, verified_by');
        }

        if (! in_array($action, ['approve', 'reject'])) {
            throw new \InvalidArgumentException('Action must be either "approve" or "reject"');
        }

        if ($action === 'reject' && empty($input['reason'])) {
            throw new \InvalidArgumentException('Reason is required for rejection');
        }

        /** @var User $user */
        $user = User::where('uuid', $userUuid)->firstOrFail();

        if ($action === 'approve') {
            $options = $input['options'] ?? [];
            $this->kycService->verifyKyc($user, $verifiedBy, $options);

            return [
                'user_uuid'   => $userUuid,
                'status'      => 'approved',
                'verified_by' => $verifiedBy,
                'verified_at' => now()->toISOString(),
                'options'     => $options,
            ];
        } else {
            $reason = $input['reason'] ?? null;
            if (! $reason) {
                throw new \InvalidArgumentException('Reason is required for rejection');
            }

            $this->kycService->rejectKyc($user, $reason, $verifiedBy);

            return [
                'user_uuid'   => $userUuid,
                'status'      => 'rejected',
                'rejected_by' => $verifiedBy,
                'rejected_at' => now()->toISOString(),
                'reason'      => $reason,
            ];
        }
    }
}
