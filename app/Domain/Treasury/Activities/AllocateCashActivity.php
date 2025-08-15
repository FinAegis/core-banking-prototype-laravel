<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use App\Domain\Treasury\ValueObjects\AllocationStrategy;
use Workflow\Activity;

class AllocateCashActivity extends Activity
{
    public function execute(array $input): array
    {
        $accountId = $input['account_id'];
        $strategy = $input['strategy'];
        $amount = $input['amount'];
        $constraints = $input['constraints'] ?? [];
        $reverse = $input['reverse'] ?? false;

        if ($reverse) {
            return $this->reverseAllocation($accountId, $input['allocations']);
        }

        // Get allocation percentages based on strategy
        $allocationStrategy = new AllocationStrategy($strategy);
        $allocations = $allocationStrategy->getDefaultAllocations();

        // Apply allocations
        $allocatedAmounts = [];
        foreach ($allocations as $allocation) {
            $allocatedAmount = $amount * ($allocation['percentage'] / 100);
            $allocatedAmounts[] = [
                'type'       => $allocation['type'],
                'percentage' => $allocation['percentage'],
                'amount'     => $allocatedAmount,
                'instrument' => $this->selectInstrument($allocation['type'], $allocatedAmount),
                'status'     => 'allocated',
            ];
        }

        return [
            'account_id'          => $accountId,
            'strategy'            => $strategy,
            'total_amount'        => $amount,
            'allocations'         => $allocatedAmounts,
            'allocated_at'        => now()->toIso8601String(),
            'constraints_applied' => $constraints,
        ];
    }

    private function reverseAllocation(string $accountId, array $allocations): array
    {
        $reversedAllocations = [];
        foreach ($allocations as $allocation) {
            $reversedAllocations[] = array_merge($allocation, [
                'status'      => 'reversed',
                'reversed_at' => now()->toIso8601String(),
            ]);
        }

        return [
            'account_id'  => $accountId,
            'strategy'    => 'reversed',
            'allocations' => $reversedAllocations,
            'reversed_at' => now()->toIso8601String(),
        ];
    }

    private function selectInstrument(string $type, float $amount): array
    {
        return match ($type) {
            'cash' => [
                'name'     => 'Money Market Fund',
                'yield'    => 2.5,
                'maturity' => 'overnight',
                'risk'     => 'low',
            ],
            'bonds' => [
                'name'     => 'US Treasury Bonds',
                'yield'    => 4.5,
                'maturity' => '2-year',
                'risk'     => 'low',
            ],
            'equities' => [
                'name'     => 'S&P 500 Index Fund',
                'yield'    => 8.0,
                'maturity' => 'none',
                'risk'     => 'medium',
            ],
            default => [
                'name'     => 'Mixed Fund',
                'yield'    => 5.0,
                'maturity' => 'various',
                'risk'     => 'medium',
            ],
        };
    }
}
