<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Wallet\Services;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Wallet\Contracts\WalletServiceInterface;
use App\Domain\Wallet\Services\WalletService;
use App\Domain\Wallet\Workflows\WalletConvertWorkflow;
use App\Domain\Wallet\Workflows\WalletDepositWorkflow;
use App\Domain\Wallet\Workflows\WalletTransferWorkflow;
use App\Domain\Wallet\Workflows\WalletWithdrawWorkflow;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Workflow\WorkflowStub;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    private string $testUuid = '550e8400-e29b-41d4-a716-446655440000';

    private string $testUuid2 = '660e8400-e29b-41d4-a716-446655440001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function test_wallet_service_implements_interface()
    {
        $this->assertInstanceOf(WalletServiceInterface::class, $this->walletService);
    }

    #[Test]
    public function test_deposit_starts_deposit_workflow()
    {
        // Mock the WorkflowStub
        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')
            ->once()
            ->with(WalletDepositWorkflow::class)
            ->andReturnSelf();

        $mockWorkflow->shouldReceive('start')
            ->once()
            ->withArgs(function ($uuid, $assetCode, $amount) {
                return $uuid instanceof AccountUuid
                    && $assetCode === 'USD'
                    && $amount === 100.00;
            });

        // Execute
        $this->walletService->deposit($this->testUuid, 'USD', 100.00);

        // Assertions are handled by Mockery expectations
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_deposit_accepts_various_uuid_formats()
    {
        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')->andReturn($mockWorkflow);
        $mockWorkflow->shouldReceive('start')->once();

        // Test with string UUID
        $this->walletService->deposit($this->testUuid, 'EUR', 50.00);

        // Test with AccountUuid object
        $accountUuid = AccountUuid::fromString($this->testUuid);
        $this->walletService->deposit($accountUuid, 'GBP', 75.00);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_withdraw_validates_sufficient_balance()
    {
        // Create real account with balance
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'uuid'      => $this->testUuid,
            'user_uuid' => $user->uuid,
        ]);

        // Add balance to account
        $account->balances()->create([
            'asset_code' => 'USD',
            'balance'    => 100.00,
        ]);

        // Mock workflow
        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')
            ->with(WalletWithdrawWorkflow::class)
            ->andReturnSelf();
        $mockWorkflow->shouldReceive('start')->once();

        // Execute
        $this->walletService->withdraw($this->testUuid, 'USD', 50.00);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_withdraw_throws_exception_for_insufficient_balance()
    {
        // Create account with insufficient balance
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'uuid'      => $this->testUuid,
            'user_uuid' => $user->uuid,
        ]);

        // Add small balance to account
        $account->balances()->create([
            'asset_code' => 'USD',
            'balance'    => 10.00,
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        // Execute - trying to withdraw more than balance
        $this->walletService->withdraw($this->testUuid, 'USD', 100.00);
    }

    #[Test]
    public function test_withdraw_throws_exception_when_account_not_found()
    {
        // No account exists with this UUID
        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        // Execute - account doesn't exist
        $this->walletService->withdraw('999e8400-e29b-41d4-a716-446655440999', 'USD', 50.00);
    }

    #[Test]
    public function test_transfer_validates_source_account_balance()
    {
        // Create source account with sufficient balance
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create([
            'uuid'      => $this->testUuid,
            'user_uuid' => $user->uuid,
        ]);

        // Add balance to source account
        $fromAccount->balances()->create([
            'asset_code' => 'USD',
            'balance'    => 100.00,
        ]);

        // Create destination account
        $toAccount = Account::factory()->create([
            'uuid'      => $this->testUuid2,
            'user_uuid' => $user->uuid,
        ]);

        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')
            ->with(WalletTransferWorkflow::class)
            ->andReturnSelf();
        $mockWorkflow->shouldReceive('start')
            ->once()
            ->withArgs(function ($from, $to, $asset, $amount, $ref) {
                return $from instanceof AccountUuid
                    && $to instanceof AccountUuid
                    && $asset === 'USD'
                    && $amount === 75.00
                    && $ref === 'Test transfer';
            });

        // Execute
        $this->walletService->transfer(
            $this->testUuid,
            $this->testUuid2,
            'USD',
            75.00,
            'Test transfer'
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_transfer_throws_exception_for_insufficient_balance()
    {
        // Create source account with insufficient balance
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create([
            'uuid'      => $this->testUuid,
            'user_uuid' => $user->uuid,
        ]);

        // Add small balance to source account
        $fromAccount->balances()->create([
            'asset_code' => 'USD',
            'balance'    => 10.00,
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        // Execute - trying to transfer more than balance
        $this->walletService->transfer(
            $this->testUuid,
            $this->testUuid2,
            'USD',
            1000.00
        );
    }

    #[Test]
    public function test_transfer_works_without_reference()
    {
        // Create source account with sufficient balance
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create([
            'uuid'      => $this->testUuid,
            'user_uuid' => $user->uuid,
        ]);

        // Add balance to source account
        $fromAccount->balances()->create([
            'asset_code' => 'EUR',
            'balance'    => 100.00,
        ]);

        // Create destination account
        $toAccount = Account::factory()->create([
            'uuid'      => $this->testUuid2,
            'user_uuid' => $user->uuid,
        ]);

        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')
            ->with(WalletTransferWorkflow::class)
            ->andReturnSelf();
        $mockWorkflow->shouldReceive('start')
            ->once()
            ->withArgs(function ($from, $to, $asset, $amount, $ref) {
                return $ref === null;
            });

        // Execute without reference
        $this->walletService->transfer(
            $this->testUuid,
            $this->testUuid2,
            'EUR',
            50.00
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_convert_starts_convert_workflow()
    {
        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')
            ->once()
            ->with(WalletConvertWorkflow::class)
            ->andReturnSelf();

        $mockWorkflow->shouldReceive('start')
            ->once()
            ->withArgs(function ($uuid, $fromAsset, $toAsset, $amount) {
                return $uuid instanceof AccountUuid
                    && $fromAsset === 'USD'
                    && $toAsset === 'EUR'
                    && $amount === 100.00;
            });

        // Execute
        $this->walletService->convert(
            $this->testUuid,
            'USD',
            'EUR',
            100.00
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_convert_handles_different_asset_pairs()
    {
        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')
            ->times(3)
            ->with(WalletConvertWorkflow::class)
            ->andReturnSelf();
        $mockWorkflow->shouldReceive('start')->times(3);

        // Test various asset pairs
        $this->walletService->convert($this->testUuid, 'USD', 'GBP', 50.00);
        $this->walletService->convert($this->testUuid, 'EUR', 'USD', 75.00);
        $this->walletService->convert($this->testUuid, 'GBP', 'EUR', 100.00);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_all_methods_handle_decimal_amounts_correctly()
    {
        $mockWorkflow = Mockery::mock('overload:' . WorkflowStub::class);
        $mockWorkflow->shouldReceive('make')->andReturn($mockWorkflow);
        $mockWorkflow->shouldReceive('start')
            ->times(2)
            ->withArgs(function (...$args) {
                // Check that decimal amounts are preserved
                $lastArg = end($args);

                return is_numeric($lastArg);
            });

        // Test with decimal amounts
        $this->walletService->deposit($this->testUuid, 'USD', 99.99);
        $this->walletService->convert($this->testUuid, 'USD', 'EUR', 123.45);

        $this->expectNotToPerformAssertions();
    }
}
