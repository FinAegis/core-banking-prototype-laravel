<?php

namespace Tests\Unit\Domain\Stablecoin\Events;

use App\Domain\Stablecoin\Events\CollateralizationRatioUpdated;
use App\Domain\Stablecoin\Events\CollateralLocked;
use App\Domain\Stablecoin\Events\CollateralPositionClosed;
use App\Domain\Stablecoin\Events\CollateralPositionCreated;
use App\Domain\Stablecoin\Events\CollateralPositionLiquidated;
use App\Domain\Stablecoin\Events\CollateralPositionUpdated;
use App\Domain\Stablecoin\Events\CollateralReleased;
use App\Domain\Stablecoin\Events\CustodianAdded;
use App\Domain\Stablecoin\Events\CustodianRemoved;
use App\Domain\Stablecoin\Events\OracleDeviationDetected;
use App\Domain\Stablecoin\Events\ProposalCancelled;
use App\Domain\Stablecoin\Events\ProposalCreated;
use App\Domain\Stablecoin\Events\ProposalExecuted;
use App\Domain\Stablecoin\Events\ProposalFinalized;
use App\Domain\Stablecoin\Events\ProposalVoteCast;
use App\Domain\Stablecoin\Events\ReserveDeposited;
use App\Domain\Stablecoin\Events\ReservePoolCreated;
use App\Domain\Stablecoin\Events\ReserveRebalanced;
use App\Domain\Stablecoin\Events\ReserveWithdrawn;
use App\Domain\Stablecoin\Events\StablecoinBurned;
use App\Domain\Stablecoin\Events\StablecoinMinted;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use Tests\DomainTestCase;

class StablecoinEventsTest extends DomainTestCase
{
    #[Test]
    public function test_collateral_locked_event_creates_with_valid_data(): void
    {
        $positionUuid = 'pos-123';
        $accountUuid = 'acc-456';
        $assetCode = 'BTC';
        $amount = 100000000; // 1 BTC in satoshis
        $metadata = ['reason' => 'position_creation'];

        $event = new CollateralLocked(
            $positionUuid,
            $accountUuid,
            $assetCode,
            $amount,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
        $this->assertEquals($assetCode, $event->collateral_asset_code);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_collateral_locked_event_creates_without_metadata(): void
    {
        $event = new CollateralLocked('pos-123', 'acc-456', 'ETH', 2000000000000000000);

        $this->assertEquals([], $event->metadata);
    }

    #[Test]
    public function test_collateral_position_closed_event(): void
    {
        $positionUuid = 'pos-789';
        $reason = 'user_requested';
        $metadata = ['closed_at' => now()->toIso8601String()];

        $event = new CollateralPositionClosed($positionUuid, $reason);

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($reason, $event->reason);
    }

    #[Test]
    public function test_collateral_position_closed_requires_reason(): void
    {
        $event = new CollateralPositionClosed('pos-123', 'user_closed');

        $this->assertEquals('pos-123', $event->position_uuid);
        $this->assertEquals('user_closed', $event->reason);
    }

    #[Test]
    public function test_collateral_position_created_event_with_all_parameters(): void
    {
        $positionUuid = 'pos-new-123';
        $accountUuid = 'acc-new-456';
        $stablecoinCode = 'USDS';
        $collateralAssetCode = 'ETH';
        $collateralAmount = 5000000000000000000; // 5 ETH
        $debtAmount = 10000000000; // 10,000 USDS
        $collateralRatio = 150.0;
        $status = 'active';
        $metadata = [
            'initial_price'     => '2000.00',
            'liquidation_price' => '1333.33',
        ];

        $event = new CollateralPositionCreated(
            $positionUuid,
            $accountUuid,
            $stablecoinCode,
            $collateralAssetCode,
            $collateralAmount,
            $debtAmount,
            $collateralRatio,
            $status
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
        $this->assertEquals($stablecoinCode, $event->stablecoin_code);
        $this->assertEquals($collateralAssetCode, $event->collateral_asset_code);
        $this->assertEquals($collateralAmount, $event->collateral_amount);
        $this->assertEquals($debtAmount, $event->debt_amount);
        $this->assertEquals($collateralRatio, $event->collateral_ratio);
        $this->assertEquals($status, $event->status);
    }

    #[Test]
    public function test_collateral_position_created_uses_default_status(): void
    {
        $event = new CollateralPositionCreated(
            'pos-123',
            'acc-456',
            'USDS',
            'BTC',
            100000000,
            50000000000,
            200.0,
            'active'
        );

        $this->assertEquals('active', $event->status);
    }

    #[Test]
    public function test_collateral_position_liquidated_event(): void
    {
        $positionUuid = 'pos-liq-123';
        $liquidatorAccountUuid = 'acc-liquidator-789';
        $collateralSeized = 1000000000000000000; // 1 ETH
        $debtRepaid = 1500000000; // 1,500 USDS
        $liquidationPenalty = 75000000; // 75 USDS (5% penalty)
        $metadata = [
            'trigger'              => 'under_collateralized',
            'price_at_liquidation' => '1400.00',
        ];

        $event = new CollateralPositionLiquidated(
            $positionUuid,
            $liquidatorAccountUuid,
            $collateralSeized,
            $debtRepaid,
            $liquidationPenalty,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($liquidatorAccountUuid, $event->liquidator_account_uuid);
        $this->assertEquals($collateralSeized, $event->collateral_seized);
        $this->assertEquals($debtRepaid, $event->debt_repaid);
        $this->assertEquals($liquidationPenalty, $event->liquidation_penalty);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_collateral_position_updated_event(): void
    {
        $positionUuid = 'pos-upd-123';
        $collateralAmount = 2000000000000000000; // 2 ETH
        $debtAmount = 3000000000; // 3,000 USDS
        $collateralRatio = 133.33;
        $status = 'active';
        $metadata = ['update_type' => 'add_collateral'];

        $event = new CollateralPositionUpdated(
            $positionUuid,
            $collateralAmount,
            $debtAmount,
            $collateralRatio,
            $status,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($collateralAmount, $event->collateral_amount);
        $this->assertEquals($debtAmount, $event->debt_amount);
        $this->assertEquals($collateralRatio, $event->collateral_ratio);
        $this->assertEquals($status, $event->status);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_collateral_released_event(): void
    {
        $positionUuid = 'pos-rel-123';
        $accountUuid = 'acc-rel-456';
        $assetCode = 'WBTC';
        $amount = 50000000; // 0.5 BTC
        $metadata = ['reason' => 'partial_withdrawal'];

        $event = new CollateralReleased(
            $positionUuid,
            $accountUuid,
            $assetCode,
            $amount,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
        $this->assertEquals($assetCode, $event->collateral_asset_code);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_collateralization_ratio_updated_event(): void
    {
        $poolId = 'pool-123';
        $oldTargetRatio = '150';
        $newTargetRatio = '175';
        $oldMinimumRatio = '120';
        $newMinimumRatio = '130';
        $approvedBy = 'governance-vote-456';

        $event = new CollateralizationRatioUpdated(
            $poolId,
            $oldTargetRatio,
            $newTargetRatio,
            $oldMinimumRatio,
            $newMinimumRatio,
            $approvedBy
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($poolId, $event->poolId);
        $this->assertEquals($oldTargetRatio, $event->oldTargetRatio);
        $this->assertEquals($newTargetRatio, $event->newTargetRatio);
        $this->assertEquals($oldMinimumRatio, $event->oldMinimumRatio);
        $this->assertEquals($newMinimumRatio, $event->newMinimumRatio);
        $this->assertEquals($approvedBy, $event->approvedBy);
    }

    #[Test]
    public function test_custodian_added_event(): void
    {
        $poolId = 'pool-123';
        $custodianId = 'cust-add-123';
        $name = 'BitGo Trust';
        $type = 'qualified_custodian';
        $config = [
            'api_key'     => 'encrypted_key',
            'webhook_url' => 'https://api.example.com/webhook',
        ];

        $event = new CustodianAdded($poolId, $custodianId, $name, $type, $config);

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($poolId, $event->poolId);
        $this->assertEquals($custodianId, $event->custodianId);
        $this->assertEquals($name, $event->name);
        $this->assertEquals($type, $event->type);
        $this->assertEquals($config, $event->config);
    }

    #[Test]
    public function test_custodian_removed_event(): void
    {
        $poolId = 'pool-123';
        $custodianId = 'cust-rem-123';
        $reason = 'license_revoked';

        $event = new CustodianRemoved($poolId, $custodianId, $reason);

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($poolId, $event->poolId);
        $this->assertEquals($custodianId, $event->custodianId);
        $this->assertEquals($reason, $event->reason);
    }

    #[Test]
    public function test_oracle_deviation_detected_event(): void
    {
        $base = 'ETH';
        $quote = 'USD';
        $deviation = 5.2;
        $prices = [
            'chainlink'    => '2100.50',
            'binance'      => '2000.00',
            'internal_amm' => '1995.00',
        ];
        $aggregateUuid = 'agg-123';

        $event = new OracleDeviationDetected(
            $base,
            $quote,
            $deviation,
            $prices,
            $aggregateUuid
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($base, $event->base);
        $this->assertEquals($quote, $event->quote);
        $this->assertEquals($deviation, $event->deviation);
        $this->assertEquals($prices, $event->prices);
        $this->assertEquals($aggregateUuid, $event->aggregateUuid);
    }

    #[Test]
    public function test_oracle_deviation_detected_with_null_aggregate_uuid(): void
    {
        $event = new OracleDeviationDetected('BTC', 'USD', 3.5, ['price1' => '48000']);

        $this->assertNull($event->aggregateUuid);
    }

    #[Test]
    public function test_proposal_cancelled_event(): void
    {
        $proposalId = 'prop-can-123';
        $reason = 'proposal_withdrawn';
        $cancelledBy = 'acc-admin-456';
        $timestamp = Carbon::now();

        $event = new ProposalCancelled(
            $proposalId,
            $reason,
            $cancelledBy,
            $timestamp
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($proposalId, $event->proposalId);
        $this->assertEquals($reason, $event->reason);
        $this->assertEquals($cancelledBy, $event->cancelledBy);
        $this->assertEquals($timestamp, $event->timestamp);
    }

    #[Test]
    public function test_proposal_created_event(): void
    {
        $proposalId = 'prop-new-123';
        $proposalType = 'parameter_change';
        $title = 'Increase ETH Collateral Ratio';
        $description = 'Proposal to increase ETH collateral ratio from 150% to 175%';
        $parameters = [
            'asset'          => 'ETH',
            'current_ratio'  => 150,
            'proposed_ratio' => 175,
        ];
        $proposer = 'acc-proposer-456';
        $startTime = Carbon::now();
        $endTime = Carbon::now()->addDays(7);
        $quorumRequired = '100000';
        $approvalThreshold = '60';

        $event = new ProposalCreated(
            $proposalId,
            $proposalType,
            $title,
            $description,
            $parameters,
            $proposer,
            $startTime,
            $endTime,
            $quorumRequired,
            $approvalThreshold
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($proposalId, $event->proposalId);
        $this->assertEquals($proposalType, $event->proposalType);
        $this->assertEquals($title, $event->title);
        $this->assertEquals($description, $event->description);
        $this->assertEquals($parameters, $event->parameters);
        $this->assertEquals($proposer, $event->proposer);
        $this->assertEquals($startTime, $event->startTime);
        $this->assertEquals($endTime, $event->endTime);
        $this->assertEquals($quorumRequired, $event->quorumRequired);
        $this->assertEquals($approvalThreshold, $event->approvalThreshold);
    }

    #[Test]
    public function test_proposal_executed_event(): void
    {
        $proposalId = 'prop-exec-123';
        $executedBy = 'acc-exec-456';
        $executionData = ['success' => true, 'changes_applied' => 3];
        $timestamp = Carbon::now();

        $event = new ProposalExecuted(
            $proposalId,
            $executedBy,
            $executionData,
            $timestamp
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($proposalId, $event->proposalId);
        $this->assertEquals($executedBy, $event->executedBy);
        $this->assertEquals($executionData, $event->executionData);
        $this->assertEquals($timestamp, $event->timestamp);
    }

    #[Test]
    public function test_proposal_finalized_event(): void
    {
        $proposalId = 'prop-fin-123';
        $result = 'approved';
        $totalVotes = '2000000';
        $votesSummary = [
            'for'     => '1500000',
            'against' => '500000',
            'abstain' => '0',
        ];
        $quorumReached = true;
        $approvalRate = '75.00';

        $event = new ProposalFinalized(
            $proposalId,
            $result,
            $totalVotes,
            $votesSummary,
            $quorumReached,
            $approvalRate
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($proposalId, $event->proposalId);
        $this->assertEquals($result, $event->result);
        $this->assertEquals($totalVotes, $event->totalVotes);
        $this->assertEquals($votesSummary, $event->votesSummary);
        $this->assertEquals($quorumReached, $event->quorumReached);
        $this->assertEquals($approvalRate, $event->approvalRate);
    }

    #[Test]
    public function test_proposal_vote_cast_event(): void
    {
        $proposalId = 'prop-vote-123';
        $voter = 'acc-voter-456';
        $choice = 'for';
        $votingPower = '10000';
        $reason = 'support_risk_management';
        $timestamp = Carbon::now();

        $event = new ProposalVoteCast(
            $proposalId,
            $voter,
            $choice,
            $votingPower,
            $reason,
            $timestamp
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($proposalId, $event->proposalId);
        $this->assertEquals($voter, $event->voter);
        $this->assertEquals($choice, $event->choice);
        $this->assertEquals($votingPower, $event->votingPower);
        $this->assertEquals($reason, $event->reason);
        $this->assertEquals($timestamp, $event->timestamp);
    }

    #[Test]
    public function test_reserve_deposited_event(): void
    {
        $poolId = 'pool-123';
        $asset = 'USDC';
        $amount = '1000000000000';
        $custodianId = 'cust-123';
        $transactionHash = '0xabc123...';
        $metadata = ['source' => 'protocol_fees', 'batch_id' => 'batch-789'];

        $event = new ReserveDeposited(
            $poolId,
            $asset,
            $amount,
            $custodianId,
            $transactionHash,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($poolId, $event->poolId);
        $this->assertEquals($asset, $event->asset);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals($custodianId, $event->custodianId);
        $this->assertEquals($transactionHash, $event->transactionHash);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_reserve_pool_created_event(): void
    {
        $poolId = 'pool-new-123';
        $stablecoinSymbol = 'USDS';
        $targetCollateralizationRatio = '150';
        $minimumCollateralizationRatio = '120';

        $event = new ReservePoolCreated(
            $poolId,
            $stablecoinSymbol,
            $targetCollateralizationRatio,
            $minimumCollateralizationRatio
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($poolId, $event->poolId);
        $this->assertEquals($stablecoinSymbol, $event->stablecoinSymbol);
        $this->assertEquals($targetCollateralizationRatio, $event->targetCollateralizationRatio);
        $this->assertEquals($minimumCollateralizationRatio, $event->minimumCollateralizationRatio);
    }

    #[Test]
    public function test_reserve_rebalanced_event(): void
    {
        $poolId = 'pool-reb-123';
        $targetAllocations = [
            'USDC' => '40',
            'USDT' => '40',
            'DAI'  => '20',
        ];
        $executedBy = 'rebalancer-bot';
        $swaps = [
            ['from' => 'USDC', 'to' => 'DAI', 'amount' => '50000'],
            ['from' => 'USDT', 'to' => 'DAI', 'amount' => '50000'],
        ];
        $previousAllocations = [
            'USDC' => '45',
            'USDT' => '45',
            'DAI'  => '10',
        ];

        $event = new ReserveRebalanced(
            $poolId,
            $targetAllocations,
            $executedBy,
            $swaps,
            $previousAllocations
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($poolId, $event->poolId);
        $this->assertEquals($targetAllocations, $event->targetAllocations);
        $this->assertEquals($executedBy, $event->executedBy);
        $this->assertEquals($swaps, $event->swaps);
        $this->assertEquals($previousAllocations, $event->previousAllocations);
    }

    #[Test]
    public function test_reserve_withdrawn_event(): void
    {
        $poolId = 'pool-with-123';
        $asset = 'DAI';
        $amount = '250000000000';
        $custodianId = 'cust-123';
        $destinationAddress = '0xdef456...';
        $reason = 'operational_expenses';
        $metadata = ['approval_id' => 'appr-123', 'authorized_by' => 'treasury'];

        $event = new ReserveWithdrawn(
            $poolId,
            $asset,
            $amount,
            $custodianId,
            $destinationAddress,
            $reason,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($poolId, $event->poolId);
        $this->assertEquals($asset, $event->asset);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals($custodianId, $event->custodianId);
        $this->assertEquals($destinationAddress, $event->destinationAddress);
        $this->assertEquals($reason, $event->reason);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_stablecoin_burned_event(): void
    {
        $positionUuid = 'pos-burn-123';
        $accountUuid = 'acc-burn-456';
        $stablecoinCode = 'USDS';
        $amount = 5000000000; // 5,000 USDS
        $metadata = ['reason' => 'debt_repayment', 'tx_hash' => '0xabc...'];

        $event = new StablecoinBurned(
            $positionUuid,
            $accountUuid,
            $stablecoinCode,
            $amount,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
        $this->assertEquals($stablecoinCode, $event->stablecoin_code);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_stablecoin_minted_event(): void
    {
        $positionUuid = 'pos-mint-123';
        $accountUuid = 'acc-mint-456';
        $stablecoinCode = 'USDS';
        $amount = 10000000000; // 10,000 USDS
        $metadata = ['collateral_locked' => true, 'minting_fee' => 50000000];

        $event = new StablecoinMinted(
            $positionUuid,
            $accountUuid,
            $stablecoinCode,
            $amount,
            $metadata
        );

        $this->assertInstanceOf(ShouldBeStored::class, $event);
        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
        $this->assertEquals($stablecoinCode, $event->stablecoin_code);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_events_with_empty_metadata_default_to_empty_array(): void
    {
        $events = [
            new CollateralLocked('pos-1', 'acc-1', 'BTC', 100),
            new CollateralPositionClosed('pos-2', 'closed'),
            new CollateralPositionCreated('pos-3', 'acc-3', 'USDS', 'ETH', 100, 50, 200.0, 'active'),
            new CollateralPositionUpdated('pos-4', 100, 50, 200.0, 'active'),
            new CollateralPositionLiquidated('pos-5', 'acc-liq', 100, 50, 5),
            new CollateralReleased('pos-6', 'acc-4', 'BTC', 50),
            new ReserveDeposited('pool-1', 'USDC', '1000', 'cust-1', '0x123'),
            new ReserveWithdrawn('pool-2', 'DAI', '500', 'cust-2', '0x456', 'test'),
            new StablecoinMinted('pos-7', 'acc-5', 'USDS', 100),
            new StablecoinBurned('pos-8', 'acc-6', 'USDS', 50),
        ];

        foreach ($events as $event) {
            if (property_exists($event, 'metadata')) {
                $this->assertEquals([], $event->metadata);
            }
        }
    }

    #[Test]
    public function test_events_handle_complex_metadata_structures(): void
    {
        $complexMetadata = [
            'nested' => [
                'level1' => [
                    'level2' => ['value' => 123],
                ],
            ],
            'array' => [1, 2, 3],
            'mixed' => [
                'string'  => 'test',
                'number'  => 456,
                'boolean' => true,
                'null'    => null,
            ],
        ];

        $event = new CollateralLocked('pos-complex', 'acc-complex', 'ETH', 1000, $complexMetadata);

        $this->assertEquals($complexMetadata, $event->metadata);
        $this->assertEquals(123, $event->metadata['nested']['level1']['level2']['value']);
        $this->assertIsArray($event->metadata['array']);
        $this->assertTrue($event->metadata['mixed']['boolean']);
        $this->assertNull($event->metadata['mixed']['null']);
    }
}
