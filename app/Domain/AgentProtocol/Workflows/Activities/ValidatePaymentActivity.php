<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Validates a payment request before processing.
 */
class ValidatePaymentActivity extends Activity
{
    /**
     * Execute payment validation.
     *
     * @param AgentPaymentRequest $request The payment request to validate
     * @param array $options Additional validation options
     * @return stdClass Validation result with isValid and errorMessage
     */
    public function execute(AgentPaymentRequest $request, array $options = []): stdClass
    {
        $result = new stdClass();
        $result->isValid = true;
        $result->errorMessage = null;
        $result->warnings = [];

        try {
            // Step 1: Validate request structure
            $errors = $request->validate();
            if (! empty($errors)) {
                $result->isValid = false;
                $result->errorMessage = 'Invalid request: ' . implode(', ', $errors);

                return $result;
            }

            // Step 2: Validate sender exists and is active
            $senderIdentity = AgentIdentityAggregate::retrieve($request->fromAgentDid);
            if (! $this->isAgentActive($senderIdentity)) {
                $result->isValid = false;
                $result->errorMessage = 'Sender agent is not active';

                return $result;
            }

            // Step 3: Validate recipient exists and is active
            $recipientIdentity = AgentIdentityAggregate::retrieve($request->toAgentDid);
            if (! $this->isAgentActive($recipientIdentity)) {
                $result->isValid = false;
                $result->errorMessage = 'Recipient agent is not active';

                return $result;
            }

            // Step 4: Validate sender has sufficient balance
            $senderWallet = AgentWalletAggregate::retrieve($request->fromAgentDid);
            $availableBalance = $senderWallet->getAvailableBalance();

            $requiredAmount = $request->amount;
            if ($request->requiresFees()) {
                // Calculate fees (2.5% for standard transactions)
                $fees = $request->amount * 0.025;
                $requiredAmount += $fees;
            }

            if ($availableBalance < $requiredAmount) {
                $result->isValid = false;
                $result->errorMessage = sprintf(
                    'Insufficient balance. Required: %.2f, Available: %.2f',
                    $requiredAmount,
                    $availableBalance
                );

                return $result;
            }

            // Step 5: Validate transaction limits
            if (! $this->validateTransactionLimits($senderWallet, $request->amount)) {
                $result->isValid = false;
                $result->errorMessage = 'Transaction exceeds daily or per-transaction limits';

                return $result;
            }

            // Step 6: Validate splits if present
            if ($options['validateSplits'] ?? false) {
                if ($request->hasSplits()) {
                    $splitValidation = $this->validateSplits($request);
                    if (! $splitValidation->isValid) {
                        $result->isValid = false;
                        $result->errorMessage = $splitValidation->errorMessage;

                        return $result;
                    }
                }
            }

            // Step 7: Check for suspicious activity
            if ($this->isSuspiciousActivity($request)) {
                $result->warnings[] = 'Transaction flagged for review';
                // Don't block, but flag for monitoring
            }

            // Step 8: Validate escrow conditions if present
            if ($request->requiresEscrow()) {
                $escrowValidation = $this->validateEscrowConditions($request);
                if (! $escrowValidation->isValid) {
                    $result->isValid = false;
                    $result->errorMessage = $escrowValidation->errorMessage;

                    return $result;
                }
            }
        } catch (Exception $e) {
            $result->isValid = false;
            $result->errorMessage = 'Validation failed: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Check if an agent is active.
     */
    private function isAgentActive(AgentIdentityAggregate $agent): bool
    {
        // Use the isActive() method directly
        return $agent->isActive();
    }

    /**
     * Validate transaction limits.
     */
    private function validateTransactionLimits(AgentWalletAggregate $wallet, float $amount): bool
    {
        // Check per-transaction limit
        if (! $wallet->isWithinLimit('per_transaction', $amount)) {
            return false;
        }

        // Check daily limit
        if (! $wallet->isWithinLimit('daily', $amount)) {
            return false;
        }

        return true;
    }

    /**
     * Validate split payment configuration.
     */
    private function validateSplits(AgentPaymentRequest $request): stdClass
    {
        $result = new stdClass();
        $result->isValid = true;
        $result->errorMessage = null;

        $splits = $request->splits;
        $totalSplitAmount = 0;

        foreach ($splits as $split) {
            // Validate each split recipient
            if (! isset($split['agentDid']) || ! isset($split['amount'])) {
                $result->isValid = false;
                $result->errorMessage = 'Invalid split configuration';

                return $result;
            }

            // Validate recipient exists
            try {
                $recipientIdentity = AgentIdentityAggregate::retrieve($split['agentDid']);
                if (! $this->isAgentActive($recipientIdentity)) {
                    $result->isValid = false;
                    $result->errorMessage = 'Split recipient ' . $split['agentDid'] . ' is not active';

                    return $result;
                }
            } catch (Exception $e) {
                $result->isValid = false;
                $result->errorMessage = 'Invalid split recipient: ' . $split['agentDid'];

                return $result;
            }

            $totalSplitAmount += $split['amount'];
        }

        // Validate total matches
        if (abs($totalSplitAmount - $request->amount) > 0.01) {
            $result->isValid = false;
            $result->errorMessage = 'Split amounts do not match total';

            return $result;
        }

        return $result;
    }

    /**
     * Check for suspicious activity patterns.
     */
    private function isSuspiciousActivity(AgentPaymentRequest $request): bool
    {
        // Check for rapid consecutive transactions
        // Check for unusual amounts
        // Check for blacklisted agents
        // This would integrate with fraud detection service

        // For now, flag large transactions
        if ($request->amount > 50000) {
            return true;
        }

        return false;
    }

    /**
     * Validate escrow conditions.
     */
    private function validateEscrowConditions(AgentPaymentRequest $request): stdClass
    {
        $result = new stdClass();
        $result->isValid = true;
        $result->errorMessage = null;

        $conditions = $request->escrowConditions;

        // Validate condition structure
        foreach ($conditions as $condition) {
            if (! isset($condition['type'])) {
                $result->isValid = false;
                $result->errorMessage = 'Invalid escrow condition: missing type';

                return $result;
            }

            // Validate specific condition types
            switch ($condition['type']) {
                case 'time_based':
                    if (! isset($condition['target_time'])) {
                        $result->isValid = false;
                        $result->errorMessage = 'Time-based condition missing target_time';

                        return $result;
                    }
                    break;

                case 'confirmation':
                    if (! isset($condition['party'])) {
                        $result->isValid = false;
                        $result->errorMessage = 'Confirmation condition missing party';

                        return $result;
                    }
                    break;

                case 'external_verification':
                    if (! isset($condition['verification_type'])) {
                        $result->isValid = false;
                        $result->errorMessage = 'Verification condition missing type';

                        return $result;
                    }
                    break;
            }
        }

        return $result;
    }
}
