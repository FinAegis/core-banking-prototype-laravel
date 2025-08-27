<?php

namespace App\Domain\Compliance\Workflows;

use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class KycSubmissionWorkflow extends Workflow
{
    public function execute(array $input): Generator
    {
        try {
            $result = yield ActivityStub::make(
                'App\Domain\Compliance\Activities\KycSubmissionActivity',
                $input
            );

            // Add compensation to revert KYC submission by rejecting it
            // This restores the user to a state where they can resubmit
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

            return $result;
        } catch (Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
