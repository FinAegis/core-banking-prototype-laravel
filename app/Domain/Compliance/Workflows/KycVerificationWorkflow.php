<?php

namespace App\Domain\Compliance\Workflows;

use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class KycVerificationWorkflow extends Workflow
{
    public function execute(array $input): Generator
    {
        try {
            $result = yield ActivityStub::make(
                'App\Domain\Compliance\Activities\KycVerificationActivity',
                $input
            );

            // Add compensation based on the action taken
            if (($input['action'] ?? '') === 'approve') {
                // If we approved, compensation is to reject
                $this->addCompensation(
                    fn () => ActivityStub::make(
                        'App\Domain\Compliance\Activities\KycVerificationActivity',
                        [
                            'user_uuid'   => $input['user_uuid'],
                            'action'      => 'reject',
                            'verified_by' => 'system',
                            'reason'      => 'Compensation rollback due to workflow failure',
                        ]
                    )
                );
            } elseif (($input['action'] ?? '') === 'reject') {
                // If we rejected, compensation is to reset to pending for re-review
                // In practice, rejection might be final, but for compensation we allow re-review
                $this->addCompensation(
                    fn () => ActivityStub::make(
                        'App\Domain\Compliance\Activities\KycSubmissionActivity',
                        [
                            'user_uuid' => $input['user_uuid'],
                            'documents' => [], // Empty documents for status reset
                        ]
                    )
                );
            }

            return $result;
        } catch (Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
