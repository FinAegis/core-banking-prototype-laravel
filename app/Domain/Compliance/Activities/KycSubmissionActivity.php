<?php

namespace App\Domain\Compliance\Activities;

use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use InvalidArgumentException;
use Workflow\Activity;

class KycSubmissionActivity extends Activity
{
    public function __construct(
        private KycService $kycService
    ) {
    }

    /**
     * Execute KYC submission activity.
     *
     * @param  array  $input  Expected format: [
     *                        'user_uuid' => string,
     *                        'documents' => array
     *                        ]
     */
    public function execute(array $input): array
    {
        /** @var mixed|null $user */
        $user = null;
        $userUuid = $input['user_uuid'] ?? null;
        $documents = $input['documents'] ?? [];

        if (! $userUuid || empty($documents)) {
            throw new InvalidArgumentException('Missing required parameters: user_uuid, documents');
        }

        /** @var User $user */
        $user = User::where('uuid', $userUuid)->firstOrFail();

        $this->kycService->submitKyc($user, $documents);

        return [
            'user_uuid'      => $userUuid,
            'status'         => 'submitted',
            'document_count' => count($documents),
            'submitted_at'   => now()->toISOString(),
        ];
    }
}
