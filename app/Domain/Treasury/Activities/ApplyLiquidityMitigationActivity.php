<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

/**
 * Activity for applying automatic liquidity mitigation.
 */
#[ActivityInterface]
class ApplyLiquidityMitigationActivity
{
    #[ActivityMethod]
    public function execute(
        string $treasuryId,
        array $forecast,
        array $alerts
    ): array {
        $mitigationActions = [];

        foreach ($alerts as $alert) {
            switch ($alert['type']) {
                case 'negative_balance':
                    $mitigationActions[] = [
                        'action'          => 'accelerate_collections',
                        'description'     => 'Initiate accelerated receivables collection',
                        'expected_impact' => 'Improve cash position by 10-15%',
                    ];
                    break;

                case 'lcr_breach':
                    $mitigationActions[] = [
                        'action'          => 'liquidate_investments',
                        'description'     => 'Liquidate non-essential short-term investments',
                        'expected_impact' => 'Increase HQLA by 20-30%',
                    ];
                    break;

                case 'stress_resilience':
                    $mitigationActions[] = [
                        'action'          => 'secure_credit_facility',
                        'description'     => 'Activate standby credit facilities',
                        'expected_impact' => 'Extend survival period by 30-45 days',
                    ];
                    break;
            }
        }

        return [
            'treasury_id'        => $treasuryId,
            'actions_taken'      => count($mitigationActions),
            'mitigation_details' => $mitigationActions,
            'executed_at'        => now()->toIso8601String(),
        ];
    }
}
