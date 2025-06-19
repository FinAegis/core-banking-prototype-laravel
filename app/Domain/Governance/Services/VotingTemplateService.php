<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Strategies\AssetWeightedVotingStrategy;
use App\Domain\Governance\Workflows\UpdateBasketCompositionWorkflow;
use App\Models\User;
use Carbon\Carbon;

class VotingTemplateService
{
    private ?string $systemUserUuid = null;
    
    /**
     * Get or create system user for polls
     */
    private function getSystemUserUuid(): string
    {
        if (!$this->systemUserUuid) {
            $systemUser = User::firstOrCreate(
                ['email' => 'system@platform'],
                ['name' => 'System', 'password' => bcrypt(uniqid())]
            );
            $this->systemUserUuid = $systemUser->uuid;
        }
        
        return $this->systemUserUuid;
    }
    
    /**
     * Create a monthly currency basket voting poll
     */
    public function createMonthlyBasketVotingPoll(Carbon $votingMonth = null): Poll
    {
        $votingMonth = $votingMonth ?? now()->addMonth()->startOfMonth();
        
        $poll = Poll::create([
            'title' => "Currency Basket Composition - {$votingMonth->format('F Y')}",
            'description' => $this->getBasketVotingDescription($votingMonth),
            'type' => PollType::WEIGHTED_CHOICE,
            'options' => $this->getBasketVotingOptions(),
            'start_date' => $votingMonth->copy()->subDays(7), // Voting starts 7 days before month
            'end_date' => $votingMonth->copy()->subDays(1)->endOfDay(), // Ends last day of previous month
            'status' => PollStatus::DRAFT,
            'required_participation' => 10, // Minimum 10% participation
            'voting_power_strategy' => AssetWeightedVotingStrategy::class,
            'execution_workflow' => UpdateBasketCompositionWorkflow::class,
            'created_by' => $this->getSystemUserUuid(),
            'metadata' => [
                'template' => 'monthly_basket',
                'voting_month' => $votingMonth->format('Y-m'),
                'basket_code' => config('baskets.primary', 'PRIMARY'),
                'auto_execute' => true,
            ],
        ]);
        
        return $poll;
    }
    
    /**
     * Create a poll for adding a new currency to the basket
     */
    public function createAddCurrencyPoll(string $currencyCode, string $currencyName): Poll
    {
        $poll = Poll::create([
            'title' => "Add {$currencyName} ({$currencyCode}) to Currency Basket?",
            'description' => "Vote on whether to add {$currencyName} to the currency basket. This would require rebalancing existing allocations.",
            'type' => PollType::SINGLE_CHOICE,
            'options' => [
                ['id' => 'yes', 'label' => "Yes, add {$currencyCode} to the basket"],
                ['id' => 'no', 'label' => "No, keep current basket composition"],
            ],
            'start_date' => now(),
            'end_date' => now()->addDays(14),
            'status' => PollStatus::DRAFT,
            'required_participation' => 25, // Higher threshold for structural changes
            'voting_power_strategy' => AssetWeightedVotingStrategy::class,
            'execution_workflow' => null, // Manual execution required
            'created_by' => $this->getSystemUserUuid(),
            'metadata' => [
                'template' => 'add_currency',
                'currency_code' => $currencyCode,
                'currency_name' => $currencyName,
            ],
        ]);
        
        return $poll;
    }
    
    /**
     * Create a poll for emergency rebalancing
     */
    public function createEmergencyRebalancingPoll(string $reason): Poll
    {
        $poll = Poll::create([
            'title' => 'Emergency Basket Rebalancing Required',
            'description' => "An emergency rebalancing of the currency basket is proposed due to: {$reason}",
            'type' => PollType::SINGLE_CHOICE,
            'options' => [
                ['id' => 'approve', 'label' => 'Approve emergency rebalancing'],
                ['id' => 'reject', 'label' => 'Reject - maintain current composition'],
            ],
            'start_date' => now(),
            'end_date' => now()->addDays(3), // Shorter voting period for emergencies
            'status' => PollStatus::DRAFT,
            'required_participation' => 30,
            'voting_power_strategy' => AssetWeightedVotingStrategy::class,
            'execution_workflow' => UpdateBasketCompositionWorkflow::class,
            'created_by' => $this->getSystemUserUuid(),
            'metadata' => [
                'template' => 'emergency_rebalancing',
                'reason' => $reason,
                'urgent' => true,
            ],
        ]);
        
        return $poll;
    }
    
    /**
     * Get description for basket voting poll
     */
    private function getBasketVotingDescription(Carbon $votingMonth): string
    {
        return "Vote on the currency basket composition for {$votingMonth->format('F Y')}. " .
               "Allocate percentages to each currency/asset in the basket. Your voting power is based on your asset holdings. " .
               "The final composition will be the weighted average of all votes.";
    }
    
    /**
     * Get voting options for basket composition
     */
    private function getBasketVotingOptions(): array
    {
        return [
            [
                'id' => 'basket_weights',
                'label' => 'Currency Basket Weights',
                'type' => 'allocation',
                'currencies' => [
                    ['code' => 'USD', 'name' => 'US Dollar', 'min' => 20, 'max' => 50, 'default' => 40],
                    ['code' => 'EUR', 'name' => 'Euro', 'min' => 20, 'max' => 40, 'default' => 30],
                    ['code' => 'GBP', 'name' => 'British Pound', 'min' => 5, 'max' => 25, 'default' => 15],
                    ['code' => 'CHF', 'name' => 'Swiss Franc', 'min' => 5, 'max' => 20, 'default' => 10],
                    ['code' => 'JPY', 'name' => 'Japanese Yen', 'min' => 0, 'max' => 10, 'default' => 3],
                    ['code' => 'XAU', 'name' => 'Gold', 'min' => 0, 'max' => 10, 'default' => 2],
                ],
                'constraint' => 'must_sum_to_100',
            ],
        ];
    }
    
    /**
     * Schedule monthly voting polls for the year
     */
    public function scheduleYearlyVotingPolls(int $year): array
    {
        $polls = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $votingMonth = Carbon::create($year, $month, 1);
            $polls[] = $this->createMonthlyBasketVotingPoll($votingMonth);
        }
        
        return $polls;
    }
}