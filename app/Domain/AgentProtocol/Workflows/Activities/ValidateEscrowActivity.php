<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Validates escrow requirements and conditions.
 */
class ValidateEscrowActivity extends Activity
{
    /**
     * Execute escrow validation.
     *
     * @param AgentPaymentRequest $request The payment request
     * @param stdClass $paymentResult The payment result
     * @return stdClass Validation result
     */
    public function execute(AgentPaymentRequest $request, stdClass $paymentResult): stdClass
    {
        $result = new stdClass();
        $result->isValid = true;
        $result->errorMessage = null;
        $result->warnings = [];

        try {
            // Validate escrow is required
            if (! $request->requiresEscrow()) {
                $result->isValid = false;
                $result->errorMessage = 'Escrow not required for this payment';

                return $result;
            }

            // Validate escrow conditions structure
            if (empty($request->escrowConditions)) {
                $result->isValid = false;
                $result->errorMessage = 'Escrow conditions are required but not provided';

                return $result;
            }

            // Validate each condition
            foreach ($request->escrowConditions as $condition) {
                $validationResult = $this->validateCondition($condition);
                if (! $validationResult->isValid) {
                    $result->isValid = false;
                    $result->errorMessage = $validationResult->errorMessage;

                    return $result;
                }
                if ($validationResult->hasWarning) {
                    $result->warnings[] = $validationResult->warning;
                }
            }

            // Validate payment amount is sufficient for escrow
            if ($request->amount < 10.00) {
                $result->warnings[] = 'Small amount for escrow - consider direct payment';
            }

            // Validate payment was successful
            if (! isset($paymentResult->success) || ! $paymentResult->success) {
                $result->isValid = false;
                $result->errorMessage = 'Cannot create escrow for failed payment';

                return $result;
            }

            // Check for conflicting conditions
            $conditionTypes = array_column($request->escrowConditions, 'type');
            if (count($conditionTypes) !== count(array_unique($conditionTypes))) {
                $result->warnings[] = 'Multiple conditions of the same type detected';
            }

            // Validate timeout constraints
            $maxTimeout = $this->getMaxTimeout($request->escrowConditions);
            if ($maxTimeout > 604800) { // 7 days
                $result->warnings[] = 'Long escrow timeout may lock funds unnecessarily';
            }
        } catch (Exception $e) {
            $result->isValid = false;
            $result->errorMessage = 'Escrow validation failed: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Validate a single escrow condition.
     */
    private function validateCondition(array $condition): stdClass
    {
        $result = new stdClass();
        $result->isValid = true;
        $result->hasWarning = false;
        $result->warning = null;
        $result->errorMessage = null;

        if (! isset($condition['type'])) {
            $result->isValid = false;
            $result->errorMessage = 'Condition type is required';

            return $result;
        }

        switch ($condition['type']) {
            case 'time_based':
                if (! isset($condition['target_time']) && ! isset($condition['timeout'])) {
                    $result->isValid = false;
                    $result->errorMessage = 'Time-based condition requires target_time or timeout';
                } elseif (isset($condition['target_time'])) {
                    $targetTime = strtotime($condition['target_time']);
                    if ($targetTime === false || $targetTime < time()) {
                        $result->isValid = false;
                        $result->errorMessage = 'Invalid or past target_time';
                    }
                }
                break;

            case 'confirmation':
                if (! isset($condition['party'])) {
                    $result->isValid = false;
                    $result->errorMessage = 'Confirmation condition requires party';
                } elseif (! in_array($condition['party'], ['sender', 'receiver', 'both', 'arbiter'])) {
                    $result->isValid = false;
                    $result->errorMessage = 'Invalid confirmation party';
                }
                break;

            case 'external_verification':
                if (! isset($condition['verification_type'])) {
                    $result->isValid = false;
                    $result->errorMessage = 'External verification requires verification_type';
                }
                if (! isset($condition['verification_endpoint'])) {
                    $result->hasWarning = true;
                    $result->warning = 'No verification endpoint specified for external verification';
                }
                break;

            case 'delivery_confirmation':
                if (! isset($condition['delivery_method'])) {
                    $result->isValid = false;
                    $result->errorMessage = 'Delivery confirmation requires delivery_method';
                }
                break;

            case 'milestone':
                if (! isset($condition['milestone_id'])) {
                    $result->isValid = false;
                    $result->errorMessage = 'Milestone condition requires milestone_id';
                }
                break;

            default:
                $result->hasWarning = true;
                $result->warning = 'Unknown condition type: ' . $condition['type'];
                break;
        }

        return $result;
    }

    /**
     * Get the maximum timeout from conditions.
     */
    private function getMaxTimeout(array $conditions): int
    {
        $maxTimeout = 3600; // Default 1 hour

        foreach ($conditions as $condition) {
            if ($condition['type'] === 'time_based') {
                if (isset($condition['timeout'])) {
                    $maxTimeout = max($maxTimeout, (int) $condition['timeout']);
                } elseif (isset($condition['target_time'])) {
                    $targetTime = strtotime($condition['target_time']);
                    if ($targetTime !== false) {
                        $timeout = $targetTime - time();
                        $maxTimeout = max($maxTimeout, $timeout);
                    }
                }
            }
        }

        return $maxTimeout;
    }
}
