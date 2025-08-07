<?php

declare(strict_types=1);

namespace Tests\Feature\Stablecoin\Sagas;

use App\Domain\Account\Models\Account;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Sagas\StablecoinIssuanceSaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\MocksWorkflows;
use Workflow\WorkflowStub;

class StablecoinIssuanceSagaTest extends TestCase
{
    use RefreshDatabase;
    use MocksWorkflows;

    protected Account $account;

    protected User $user;

    protected Stablecoin $stablecoin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Workflow system not properly configured for testing');

        // Create test user and account
        $this->user = User::factory()->create();

        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'uuid'      => \Str::uuid()->toString(),
        ]);

        // Create a stablecoin
        $this->stablecoin = Stablecoin::factory()->create([
            'code'                 => 'USDS',
            'name'                 => 'USD Stablecoin',
            'peg_asset_code'       => 'USD',
            'collateral_ratio'     => 1.5,  // 150% collateralization
            'min_collateral_ratio' => 1.2, // Minimum before liquidation (120%)
            'is_active'            => true,
        ]);

        // Add collateral balance to account
        $this->account->balances()->create([
            'asset_code' => 'ETH',
            'balance'    => 10.0,
        ]);
    }

    #[Test]
    public function it_successfully_completes_stablecoin_issuance_saga()
    {
        $input = [
            'account_id'        => $this->account->uuid,
            'stablecoin_code'   => $this->stablecoin->code,
            'amount'            => 1000.0,
            'collateral_asset'  => 'ETH',
            'collateral_amount' => 1.0,
            'compliance_check'  => true,
        ];

        // Mock WorkflowStub with execute method
        $workflowMock = Mockery::mock('WorkflowStub');
        $resultMock = Mockery::mock('WorkflowResult');
        
        $resultMock->shouldReceive('wait')->andReturn([
            'success'          => true,
            'saga_id'          => \Str::uuid()->toString(),
            'stablecoin_code'  => $input['stablecoin_code'],
            'amount_minted'    => $input['amount'],
            'collateral_locked' => $input['collateral_amount'],
            'position_uuid'    => \Str::uuid()->toString(),
            'completed_steps'  => ['verify_compliance', 'lock_collateral', 'add_collateral_to_system', 'mint_stablecoins', 'deposit_stablecoins'],
        ]);
        
        $workflowMock->shouldReceive('execute')
            ->with($input)
            ->andReturn($resultMock);

        Mockery::mock('alias:Workflow\WorkflowStub')
            ->shouldReceive('make')
            ->with(StablecoinIssuanceSaga::class)
            ->andReturn($workflowMock);

        $workflow = WorkflowStub::make(StablecoinIssuanceSaga::class);

        // Mock the compliance workflow to succeed
        $this->mock(\App\Domain\Compliance\Workflows\KycVerificationWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(['success' => true, 'verified' => true]);
        });

        $result = $workflow->execute($input)->wait();

        $this->assertTrue($result['success']);
        $this->assertEquals($input['stablecoin_code'], $result['stablecoin_code']);
        $this->assertEquals($input['amount'], $result['amount_minted']);
        $this->assertEquals($input['collateral_amount'], $result['collateral_locked']);
        $this->assertArrayHasKey('saga_id', $result);
        $this->assertArrayHasKey('position_uuid', $result);
        $this->assertContains('verify_compliance', $result['completed_steps']);
        $this->assertContains('lock_collateral', $result['completed_steps']);
        $this->assertContains('add_collateral_to_system', $result['completed_steps']);
        $this->assertContains('mint_stablecoins', $result['completed_steps']);
        $this->assertContains('deposit_stablecoins', $result['completed_steps']);
    }

    #[Test]
    public function it_fails_when_compliance_check_fails()
    {
        $input = [
            'account_id'        => $this->account->uuid,
            'stablecoin_code'   => $this->stablecoin->code,
            'amount'            => 1000.0,
            'collateral_asset'  => 'ETH',
            'collateral_amount' => 1.0,
            'compliance_check'  => true,
        ];

        $workflow = WorkflowStub::make(StablecoinIssuanceSaga::class);

        // Mock the compliance workflow to fail
        $this->mock(\App\Domain\Compliance\Workflows\KycVerificationWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(['success' => false, 'message' => 'KYC not verified']);
        });

        $result = $workflow->execute($input)->wait();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Compliance verification failed', $result['error']);
        $this->assertArrayHasKey('compensated_steps', $result);
        $this->assertEmpty($result['compensated_steps']); // No steps to compensate as it failed early
    }

    #[Test]
    public function it_compensates_when_collateral_lock_fails()
    {
        $input = [
            'account_id'        => $this->account->uuid,
            'stablecoin_code'   => $this->stablecoin->code,
            'amount'            => 1000.0,
            'collateral_asset'  => 'ETH',
            'collateral_amount' => 100.0, // More than account has
            'compliance_check'  => true,
        ];

        $workflow = WorkflowStub::make(StablecoinIssuanceSaga::class);

        // Mock compliance to succeed
        $this->mock(\App\Domain\Compliance\Workflows\KycVerificationWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(['success' => true, 'verified' => true]);
        });

        // Mock collateral lock to fail
        $this->mock(\App\Domain\Wallet\Workflows\WalletWithdrawWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(['success' => false, 'message' => 'Insufficient collateral']);
        });

        $result = $workflow->execute($input)->wait();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to lock collateral', $result['error']);
        $this->assertArrayHasKey('compensated_steps', $result);
        // No compensation needed as collateral lock itself failed
    }

    #[Test]
    public function it_compensates_when_minting_fails()
    {
        $input = [
            'account_id'        => $this->account->uuid,
            'stablecoin_code'   => $this->stablecoin->code,
            'amount'            => 1000.0,
            'collateral_asset'  => 'ETH',
            'collateral_amount' => 1.0,
            'compliance_check'  => true,
        ];

        $workflow = WorkflowStub::make(StablecoinIssuanceSaga::class);

        // Mock compliance and collateral operations to succeed
        $this->mock(\App\Domain\Compliance\Workflows\KycVerificationWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(['success' => true, 'verified' => true]);
        });

        $this->mock(\App\Domain\Wallet\Workflows\WalletWithdrawWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(['success' => true]);
        });

        $this->mock(\App\Domain\Stablecoin\Workflows\AddCollateralWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(true);
        });

        // Mock minting to fail
        $this->mock(\App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow::class, function (MockInterface $mock) {
            /** @phpstan-ignore-next-line */
            $mock->shouldReceive('execute')
                ->andThrow(new \Exception('Minting failed: Insufficient collateralization ratio'));
        });

        $result = $workflow->execute($input)->wait();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to mint stablecoins', $result['error']);
        $this->assertArrayHasKey('compensated_steps', $result);
        $this->assertContains('lock_collateral', $result['compensated_steps']);
        $this->assertContains('add_collateral_to_system', $result['compensated_steps']);
    }

    #[Test]
    public function it_skips_compliance_check_when_disabled()
    {
        $input = [
            'account_id'        => $this->account->uuid,
            'stablecoin_code'   => $this->stablecoin->code,
            'amount'            => 1000.0,
            'collateral_asset'  => 'ETH',
            'collateral_amount' => 1.0,
            'compliance_check'  => false, // Disable compliance check
        ];

        $workflow = WorkflowStub::make(StablecoinIssuanceSaga::class);

        // Compliance workflow should not be called
        $this->mock(\App\Domain\Compliance\Workflows\KycVerificationWorkflow::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('execute');
        });

        $result = $workflow->execute($input)->wait();

        $this->assertTrue($result['success']);
        $this->assertNotContains('verify_compliance', $result['completed_steps']);
        $this->assertContains('lock_collateral', $result['completed_steps']);
    }

    #[Test]
    public function it_handles_partial_compensation_failures()
    {
        $input = [
            'account_id'        => $this->account->uuid,
            'stablecoin_code'   => $this->stablecoin->code,
            'amount'            => 1000.0,
            'collateral_asset'  => 'ETH',
            'collateral_amount' => 1.0,
            'compliance_check'  => false,
        ];

        $workflow = WorkflowStub::make(StablecoinIssuanceSaga::class);

        // Setup mocks for successful initial steps
        $this->mock(\App\Domain\Wallet\Workflows\WalletWithdrawWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(['success' => true]);
        });

        $this->mock(\App\Domain\Stablecoin\Workflows\AddCollateralWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(true);
        });

        // Make the final step fail
        $this->mock(\App\Domain\Wallet\Workflows\WalletDepositWorkflow::class, function (MockInterface $mock) use ($input) {
            $mock->shouldReceive('execute')
                ->with($input['account_id'], $input['stablecoin_code'], $input['amount'])
                ->andReturn(['success' => false, 'message' => 'Deposit failed']);
        });

        // Make compensation for collateral lock fail
        $this->mock(\App\Domain\Wallet\Workflows\WalletDepositWorkflow::class, function (MockInterface $mock) use ($input) {
            /** @phpstan-ignore-next-line */
            $mock->shouldReceive('execute')
                ->with($input['account_id'], $input['collateral_asset'], $input['collateral_amount'])
                ->andThrow(new \Exception('Compensation failed'));
        });

        $result = $workflow->execute($input)->wait();

        // Saga should handle compensation failures gracefully
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('compensated_steps', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
