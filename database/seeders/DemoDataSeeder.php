<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Account;
use App\Models\UserBankPreference;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Services\VotingTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->info('Creating demo users and accounts...');
        
        // Ensure GCU asset exists
        $this->ensureGCUAssetExists();
        
        // Create demo users with different scenarios
        $this->createDemoUsers();
        
        // Create GCU voting polls
        $this->createVotingPolls();
        
        // Populate some transaction history
        $this->createTransactionHistory();
        
        $this->info('Demo data seeding completed!');
    }
    
    /**
     * Helper method to output info messages
     */
    private function info(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }
    
    /**
     * Ensure GCU asset exists in the database
     */
    private function ensureGCUAssetExists(): void
    {
        // Check if GCU already exists from GCUBasketSeeder
        $gcu = \App\Domain\Asset\Models\Asset::find('GCU');
        if (!$gcu) {
            // Create GCU asset
            \App\Domain\Asset\Models\Asset::create([
                'code' => 'GCU',
                'name' => 'Global Currency Unit',
                'type' => 'basket',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => true,
                'metadata' => [
                    'symbol' => 'Ǥ',
                    'description' => 'Democratic global currency unit backed by basket of currencies',
                ],
            ]);
            $this->info('Created GCU asset');
        }
    }
    
    /**
     * Create demo users representing different user personas
     */
    private function createDemoUsers(): void
    {
        // 1. High-inflation country user (Argentina)
        $argentina = User::create([
            'name' => 'Sofia Martinez',
            'email' => 'demo.argentina@gcu.global',
            'password' => Hash::make('demo123'),
            'email_verified_at' => now(),
        ]);
        
        $argAccount = Account::factory()->forUser($argentina)->create();
        $this->setupUserBanks($argentina, [
            'PAYSERA' => 40,
            'DEUTSCHE' => 30,
            'SANTANDER' => 30,
        ]);
        $this->fundAccount($argAccount, 'USD', 50000); // $500.00
        $this->fundAccount($argAccount, 'GCU', 45000); // 450 GCU
        
        // 2. Digital nomad user
        $nomad = User::create([
            'name' => 'Alex Chen',
            'email' => 'demo.nomad@gcu.global',
            'password' => Hash::make('demo123'),
            'email_verified_at' => now(),
        ]);
        
        $nomadAccount = Account::factory()->forUser($nomad)->create();
        $this->setupUserBanks($nomad, [
            'REVOLUT' => 50,
            'PAYSERA' => 30,
            'WISE' => 20,
        ]);
        $this->fundAccount($nomadAccount, 'USD', 200000); // $2,000.00
        $this->fundAccount($nomadAccount, 'EUR', 150000); // €1,500.00
        $this->fundAccount($nomadAccount, 'GCU', 180000); // 1,800 GCU
        
        // 3. Business user
        $business = User::create([
            'name' => 'TechCorp Ltd',
            'email' => 'demo.business@gcu.global',
            'password' => Hash::make('demo123'),
            'email_verified_at' => now(),
        ]);
        
        $bizAccount = Account::factory()->forUser($business)->create();
        $this->setupUserBanks($business, [
            'DEUTSCHE' => 60,
            'SANTANDER' => 40,
        ]);
        $this->fundAccount($bizAccount, 'USD', 1000000); // $10,000.00
        $this->fundAccount($bizAccount, 'EUR', 800000); // €8,000.00
        $this->fundAccount($bizAccount, 'GBP', 500000); // £5,000.00
        $this->fundAccount($bizAccount, 'GCU', 950000); // 9,500 GCU
        
        // 4. Investor user
        $investor = User::create([
            'name' => 'Emma Wilson',
            'email' => 'demo.investor@gcu.global',
            'password' => Hash::make('demo123'),
            'email_verified_at' => now(),
        ]);
        
        $invAccount = Account::factory()->forUser($investor)->create();
        $this->setupUserBanks($investor, [
            'SANTANDER' => 35,
            'DEUTSCHE' => 35,
            'PAYSERA' => 30,
        ]);
        $this->fundAccount($invAccount, 'USD', 5000000); // $50,000.00
        $this->fundAccount($invAccount, 'GCU', 4850000); // 48,500 GCU
        $this->fundAccount($invAccount, 'XAU', 100); // 1 oz gold
        
        // 5. Regular user
        $regular = User::create([
            'name' => 'John Smith',
            'email' => 'demo.user@gcu.global',
            'password' => Hash::make('demo123'),
            'email_verified_at' => now(),
        ]);
        
        $regAccount = Account::factory()->forUser($regular)->create();
        $this->setupUserBanks($regular, [
            'PAYSERA' => 100, // Simple single bank setup
        ]);
        $this->fundAccount($regAccount, 'USD', 100000); // $1,000.00
        $this->fundAccount($regAccount, 'GCU', 95000); // 950 GCU
        
        $this->info('Created 5 demo users:');
        $this->info('  - demo.argentina@gcu.global (High-inflation country user)');
        $this->info('  - demo.nomad@gcu.global (Digital nomad)');
        $this->info('  - demo.business@gcu.global (Business user)');
        $this->info('  - demo.investor@gcu.global (Investor)');
        $this->info('  - demo.user@gcu.global (Regular user)');
        $this->info('  Password for all: demo123');
    }
    
    /**
     * Set up user bank preferences
     */
    private function setupUserBanks(User $user, array $allocations): void
    {
        $isPrimary = true;
        foreach ($allocations as $bankCode => $percentage) {
            $bankInfo = UserBankPreference::AVAILABLE_BANKS[$bankCode] ?? null;
            if (!$bankInfo) {
                continue;
            }
            
            UserBankPreference::create([
                'user_uuid' => $user->uuid,
                'bank_code' => $bankCode,
                'bank_name' => $bankInfo['name'],
                'allocation_percentage' => $percentage,
                'is_primary' => $isPrimary,
                'status' => 'active',
            ]);
            $isPrimary = false;
        }
    }
    
    /**
     * Fund an account with specific asset amount
     */
    private function fundAccount(Account $account, string $assetCode, int $amount): void
    {
        // For demo data, directly create balance records instead of using workflows
        // This avoids workflow dependencies in test environment
        if ($assetCode === 'USD') {
            // Update the main balance field for USD (backward compatibility)
            $account->update(['balance' => $amount]);
        }
        
        // Create or update the balance record for any asset
        $account->balances()->updateOrCreate(
            ['asset_code' => $assetCode],
            ['balance' => $amount]
        );
    }
    
    /**
     * Create voting polls for demo
     */
    private function createVotingPolls(): void
    {
        $votingService = app(VotingTemplateService::class);
        
        // Create current month's active poll
        $currentPoll = $votingService->createMonthlyBasketVotingPoll(now()->startOfMonth());
        $currentPoll->update(['status' => PollStatus::ACTIVE]);
        
        // Create some votes for the current poll
        $this->createDemoVotes($currentPoll);
        
        // Create next month's draft poll
        $votingService->createMonthlyBasketVotingPoll(now()->addMonth()->startOfMonth());
        
        // Create a completed poll from last month
        $lastMonthPoll = $votingService->createMonthlyBasketVotingPoll(now()->subMonth()->startOfMonth());
        $lastMonthPoll->update([
            'status' => PollStatus::EXECUTED,
            'metadata' => array_merge($lastMonthPoll->metadata, [
                'results' => [
                    'USD' => 35,
                    'EUR' => 25,
                    'GBP' => 20,
                    'CHF' => 10,
                    'JPY' => 5,
                    'XAU' => 5,
                ],
                'total_votes' => 127,
                'participation_rate' => 45.2,
            ]),
        ]);
        
        $this->info('Created voting polls for demo');
    }
    
    /**
     * Create demo votes for active poll
     */
    private function createDemoVotes(Poll $poll): void
    {
        $users = User::where('email', 'like', 'demo.%')->get();
        
        // Create votes from some demo users
        $votingScenarios = [
            'demo.argentina@gcu.global' => [
                'allocations' => ['USD' => 40, 'EUR' => 25, 'GBP' => 15, 'CHF' => 10, 'JPY' => 5, 'XAU' => 5],
                'voting_power' => 450, // Based on GCU holdings
            ],
            'demo.investor@gcu.global' => [
                'allocations' => ['USD' => 30, 'EUR' => 25, 'GBP' => 20, 'CHF' => 15, 'JPY' => 5, 'XAU' => 5],
                'voting_power' => 48500, // Based on GCU holdings
            ],
            'demo.business@gcu.global' => [
                'allocations' => ['USD' => 35, 'EUR' => 30, 'GBP' => 20, 'CHF' => 10, 'JPY' => 3, 'XAU' => 2],
                'voting_power' => 9500, // Based on GCU holdings
            ],
        ];
        
        foreach ($votingScenarios as $email => $scenario) {
            $user = User::where('email', $email)->first();
            if ($user) {
                Vote::create([
                    'poll_id' => $poll->id,
                    'user_uuid' => $user->uuid,
                    'selected_options' => [
                        'basket_weights' => $scenario['allocations'],
                    ],
                    'voting_power' => $scenario['voting_power'],
                    'signature' => hash('sha256', json_encode($scenario['allocations']) . $user->uuid),
                    'metadata' => [
                        'ip_address' => '192.168.1.' . rand(1, 255),
                        'user_agent' => 'Demo Browser',
                    ],
                ]);
            }
        }
    }
    
    /**
     * Create some transaction history for demo accounts
     */
    private function createTransactionHistory(): void
    {
        // For demo purposes, we'll skip creating actual transaction history
        // since it requires complex workflow execution
        // In a real demo environment, transactions would be created through the API
        
        $this->info('Skipped transaction history (use API for real transactions)');
    }
}