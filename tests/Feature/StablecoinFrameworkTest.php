<?php

namespace Tests\Feature;

use App\Domain\Stablecoin\Aggregates\ReservePool;
use App\Domain\Stablecoin\Aggregates\GovernanceProposal;
use App\Domain\Stablecoin\Services\OracleAggregator;
use App\Domain\Stablecoin\Oracles\ChainlinkOracle;
use App\Domain\Stablecoin\Oracles\BinanceOracle;
use App\Domain\Stablecoin\Oracles\InternalAMMOracle;
use App\Models\Asset;
use App\Domain\Exchange\Projections\LiquidityPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class StablecoinFrameworkTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test assets
        Asset::firstOrCreate(['code' => 'USDC'], ['symbol' => 'USDC', 'name' => 'USD Coin', 'type' => 'fiat']);
        Asset::firstOrCreate(['code' => 'BTC'], ['symbol' => 'BTC', 'name' => 'Bitcoin', 'type' => 'crypto']);
        Asset::firstOrCreate(['code' => 'ETH'], ['symbol' => 'ETH', 'name' => 'Ethereum', 'type' => 'crypto']);
        Asset::firstOrCreate(['code' => 'FGUSD'], ['symbol' => 'FGUSD', 'name' => 'Finaegis USD', 'type' => 'stablecoin']);
    }
    
    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset Carbon time
        parent::tearDown();
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_reserve_pool()
    {
        $poolId = 'pool-' . uniqid();
        
        $pool = ReservePool::create(
            poolId: $poolId,
            stablecoinSymbol: 'FGUSD',
            targetCollateralizationRatio: '1.5',
            minimumCollateralizationRatio: '1.2'
        );
        
        $pool->persist();
        
        $this->assertInstanceOf(ReservePool::class, $pool);
        $this->assertEquals('1.5', ReservePool::retrieve($poolId)->getTargetCollateralizationRatio());
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_add_custodian_to_reserve_pool()
    {
        $poolId = 'pool-' . uniqid();
        
        $pool = ReservePool::create(
            poolId: $poolId,
            stablecoinSymbol: 'FGUSD',
            targetCollateralizationRatio: '1.5',
            minimumCollateralizationRatio: '1.2'
        );
        
        $pool->addCustodian(
            custodianId: 'custodian-1',
            name: 'Test Custodian',
            type: 'multisig',
            config: ['signers' => 3, 'threshold' => 2]
        );
        
        $pool->persist();
        
        $retrieved = ReservePool::retrieve($poolId);
        $custodians = $retrieved->getCustodians();
        
        $this->assertArrayHasKey('custodian-1', $custodians);
        $this->assertEquals('Test Custodian', $custodians['custodian-1']['name']);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_deposit_to_reserve_pool()
    {
        $poolId = 'pool-' . uniqid();
        
        $pool = ReservePool::create(
            poolId: $poolId,
            stablecoinSymbol: 'FGUSD',
            targetCollateralizationRatio: '1.5',
            minimumCollateralizationRatio: '1.2'
        );
        
        $pool->addCustodian(
            custodianId: 'custodian-1',
            name: 'Test Custodian',
            type: 'multisig',
            config: []
        );
        
        $pool->depositReserve(
            asset: 'BTC',
            amount: '10',
            custodianId: 'custodian-1',
            transactionHash: '0xabc123',
            metadata: ['block' => 12345]
        );
        
        $pool->persist();
        
        $retrieved = ReservePool::retrieve($poolId);
        $reserves = $retrieved->getReserves();
        
        $this->assertEquals('10', $reserves['BTC']);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_aggregate_oracle_prices()
    {
        $aggregator = new OracleAggregator();
        
        // Register oracles
        $aggregator->registerOracle(new ChainlinkOracle());
        $aggregator->registerOracle(new BinanceOracle());
        
        // Get aggregated price
        $price = $aggregator->getAggregatedPrice('BTC', 'USD');
        
        $this->assertNotNull($price);
        $this->assertEquals('BTC', $price->base);
        $this->assertEquals('USD', $price->quote);
        $this->assertEquals('median', $price->aggregationMethod);
        $this->assertGreaterThanOrEqual(2, count($price->sources));
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_governance_proposal()
    {
        $proposalId = 'proposal-' . uniqid();
        
        $proposal = GovernanceProposal::create(
            proposalId: $proposalId,
            proposalType: 'parameter_change',
            title: 'Update Collateralization Ratio',
            description: 'Proposal to update the target collateralization ratio to 1.4',
            parameters: [
                'parameter' => 'target_collateralization_ratio',
                'current_value' => '1.5',
                'new_value' => '1.4'
            ],
            proposer: 'user-123',
            startTime: now(),
            endTime: now()->addDays(7),
            quorumRequired: '0.1',
            approvalThreshold: '0.5'
        );
        
        $proposal->persist();
        
        $this->assertInstanceOf(GovernanceProposal::class, $proposal);
        $this->assertEquals('active', $proposal->getStatus());
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_cast_vote_on_proposal()
    {
        $proposalId = 'proposal-' . uniqid();
        
        $proposal = GovernanceProposal::create(
            proposalId: $proposalId,
            proposalType: 'parameter_change',
            title: 'Test Proposal',
            description: 'Test',
            parameters: [],
            proposer: 'user-123',
            startTime: now()->subHour(),
            endTime: now()->addDays(7),
            quorumRequired: '0.1',
            approvalThreshold: '0.5'
        );
        
        $proposal->persist();
        
        // Retrieve and cast votes
        $proposal = GovernanceProposal::retrieve($proposalId);
        
        $proposal->castVote(
            voter: 'voter-1',
            choice: 'for',
            votingPower: '1000',
            reason: 'I support this change'
        );
        
        $proposal->castVote(
            voter: 'voter-2',
            choice: 'against',
            votingPower: '500',
            reason: 'Too risky'
        );
        
        $proposal->persist();
        
        $retrieved = GovernanceProposal::retrieve($proposalId);
        $votes = $retrieved->getVotesSummary();
        
        $this->assertEquals('1000', $votes['for']);
        $this->assertEquals('500', $votes['against']);
        $this->assertEquals('0', $votes['abstain']);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_double_voting()
    {
        $proposalId = 'proposal-' . uniqid();
        
        $proposal = GovernanceProposal::create(
            proposalId: $proposalId,
            proposalType: 'parameter_change',
            title: 'Test Proposal',
            description: 'Test',
            parameters: [],
            proposer: 'user-123',
            startTime: now()->subHour(),
            endTime: now()->addDays(7)
        );
        
        $proposal->castVote('voter-1', 'for', '1000');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voter has already cast a vote');
        
        $proposal->castVote('voter-1', 'against', '1000');
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_finalize_proposal()
    {
        $proposalId = 'proposal-' . uniqid();
        
        // Create proposal with past dates
        $startTime = now()->subDays(8);
        $endTime = now()->subDay();
        
        $proposal = GovernanceProposal::create(
            proposalId: $proposalId,
            proposalType: 'parameter_change',
            title: 'Test Proposal',
            description: 'Test',
            parameters: [],
            proposer: 'user-123',
            startTime: $startTime,
            endTime: $endTime,
            quorumRequired: '0.01', // 1% for testing
            approvalThreshold: '0.5'
        );
        
        // Persist the proposal first
        $proposal->persist();
        
        // Manually set voting times to be within the proposal period
        Carbon::setTestNow($startTime->addDay());
        
        // Retrieve and cast votes
        $proposal = GovernanceProposal::retrieve($proposalId);
        $proposal->castVote('voter-1', 'for', '60000');
        $proposal->castVote('voter-2', 'for', '40000');
        $proposal->castVote('voter-3', 'against', '30000');
        $proposal->persist();
        
        // Reset time to after voting period
        Carbon::setTestNow($endTime->addDay());
        
        // Finalize after voting period
        $proposal->finalize();
        
        $proposal->persist();
        
        $retrieved = GovernanceProposal::retrieve($proposalId);
        $this->assertEquals('passed', $retrieved->getStatus());
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_use_internal_amm_oracle()
    {
        // Create a liquidity pool
        LiquidityPool::create([
            'pool_id' => 'pool-test',
            'account_id' => 1,
            'base_currency' => 'ETH',
            'quote_currency' => 'USDC',
            'base_reserve' => '100',
            'quote_reserve' => '320000',
            'total_shares' => '1000',
            'is_active' => true,
            'fee_rate' => '0.003',
            'volume_24h' => '50000',
            'fees_collected_24h' => '150'
        ]);
        
        $oracle = new InternalAMMOracle();
        $price = $oracle->getPrice('ETH', 'USDC');
        
        $this->assertEquals('3200.00000000', $price->price);
        $this->assertEquals('internal_amm', $price->source);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_minimum_collateralization_on_withdrawal()
    {
        $poolId = 'pool-' . uniqid();
        
        $pool = ReservePool::create(
            poolId: $poolId,
            stablecoinSymbol: 'FGUSD',
            targetCollateralizationRatio: '1.5',
            minimumCollateralizationRatio: '1.2'
        );
        
        $pool->addCustodian('custodian-1', 'Test', 'multisig', []);
        $pool->depositReserve('BTC', '100', 'custodian-1', '0xabc', []);
        
        // This should succeed
        $pool->withdrawReserve(
            asset: 'BTC',
            amount: '10',
            custodianId: 'custodian-1',
            destinationAddress: '0xdef',
            reason: 'Test withdrawal'
        );
        
        $pool->persist();
        
        $retrieved = ReservePool::retrieve($poolId);
        $reserves = $retrieved->getReserves();
        
        $this->assertEquals('90', $reserves['BTC']);
    }
}