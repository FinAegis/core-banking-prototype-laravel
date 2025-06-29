<?php

namespace Database\Seeders;

use App\Models\GcuVotingProposal;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class GcuVotingProposalSeeder extends Seeder
{
    public function run()
    {
        // Active proposal
        GcuVotingProposal::create([
            'title' => 'Q1 2025 Rebalancing - Increase USD Allocation',
            'description' => 'Proposal to increase USD allocation from 30% to 35% in response to strengthening dollar and Federal Reserve policy changes.',
            'rationale' => 'The US Dollar has shown significant strength against other major currencies. Economic indicators suggest this trend will continue through Q1 2025. Increasing USD allocation will provide better stability for GCU holders.',
            'proposed_composition' => [
                'USD' => 35,
                'EUR' => 25,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 10,
                'XAU' => 5,
            ],
            'current_composition' => [
                'USD' => 30,
                'EUR' => 25,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 15,
                'XAU' => 5,
            ],
            'status' => 'active',
            'voting_starts_at' => now()->subDays(2),
            'voting_ends_at' => now()->addDays(5),
            'minimum_participation' => 10,
            'minimum_approval' => 50,
            'total_gcu_supply' => 1000000,
            'total_votes_cast' => 150000,
            'votes_for' => 90000,
            'votes_against' => 60000,
        ]);
        
        // Upcoming proposal
        GcuVotingProposal::create([
            'title' => 'Increase Gold Allocation for Inflation Hedge',
            'description' => 'Proposal to double gold (XAU) allocation from 5% to 10% as an inflation hedge.',
            'rationale' => 'With global inflation concerns rising, increasing our gold allocation will provide better protection against currency devaluation and maintain purchasing power for GCU holders.',
            'proposed_composition' => [
                'USD' => 28,
                'EUR' => 23,
                'GBP' => 14,
                'CHF' => 10,
                'JPY' => 15,
                'XAU' => 10,
            ],
            'current_composition' => [
                'USD' => 30,
                'EUR' => 25,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 15,
                'XAU' => 5,
            ],
            'status' => 'active',
            'voting_starts_at' => now()->addDays(7),
            'voting_ends_at' => now()->addDays(14),
            'minimum_participation' => 10,
            'minimum_approval' => 50,
            'total_gcu_supply' => 1000000,
        ]);
        
        // Past implemented proposal
        GcuVotingProposal::create([
            'title' => 'December 2024 Rebalancing',
            'description' => 'Monthly rebalancing to optimize currency weights based on economic conditions.',
            'rationale' => 'Regular monthly rebalancing to maintain optimal currency allocation.',
            'proposed_composition' => [
                'USD' => 30,
                'EUR' => 25,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 15,
                'XAU' => 5,
            ],
            'current_composition' => [
                'USD' => 32,
                'EUR' => 23,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 15,
                'XAU' => 5,
            ],
            'status' => 'implemented',
            'voting_starts_at' => now()->subDays(30),
            'voting_ends_at' => now()->subDays(23),
            'minimum_participation' => 10,
            'minimum_approval' => 50,
            'total_gcu_supply' => 950000,
            'total_votes_cast' => 280000,
            'votes_for' => 200000,
            'votes_against' => 80000,
            'implemented_at' => now()->subDays(22),
            'implementation_details' => [
                'execution_date' => now()->subDays(22)->format('Y-m-d'),
                'total_rebalanced' => 950000,
                'banks_updated' => 3,
            ],
        ]);
    }
}