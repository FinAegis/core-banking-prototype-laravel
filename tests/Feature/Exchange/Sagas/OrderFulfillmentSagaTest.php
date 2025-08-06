<?php

declare(strict_types=1);

namespace Tests\Feature\Exchange\Sagas;

use App\Domain\Account\Models\Account;
use App\Domain\Exchange\Sagas\OrderFulfillmentSaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Workflow\WorkflowStub;

class OrderFulfillmentSagaTest extends TestCase
{
    use RefreshDatabase;

    protected Account $buyerAccount;

    protected Account $sellerAccount;

    protected User $buyer;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users and accounts
        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->create();

        $this->buyerAccount = Account::factory()->create([
            'user_uuid' => $this->buyer->uuid,
            'uuid'      => \Str::uuid()->toString(),
        ]);

        $this->sellerAccount = Account::factory()->create([
            'user_uuid' => $this->seller->uuid,
            'uuid'      => \Str::uuid()->toString(),
        ]);

        // Add balances to accounts
        $this->buyerAccount->balances()->create([
            'asset_code' => 'USD',
            'balance'    => 10000.00,
        ]);

        $this->sellerAccount->balances()->create([
            'asset_code' => 'BTC',
            'balance'    => 1.0,
        ]);
    }

    #[Test]
    public function it_successfully_completes_order_fulfillment_saga()
    {
        $orderId = \Str::uuid()->toString();

        $input = [
            'order_id'          => $orderId,
            'buyer_account_id'  => $this->buyerAccount->uuid,
            'seller_account_id' => $this->sellerAccount->uuid,
            'base_currency'     => 'BTC',
            'quote_currency'    => 'USD',
            'amount'            => 0.1,
            'price'             => 50000.0,
            'type'              => 'buy',
        ];

        $workflow = WorkflowStub::make(OrderFulfillmentSaga::class);
        $result = $workflow->execute($input)->wait();

        $this->assertTrue($result['success']);
        $this->assertEquals($orderId, $result['order_id']);
        $this->assertArrayHasKey('saga_id', $result);
        $this->assertContains('lock_buyer_funds', $result['completed_steps']);
        $this->assertContains('match_order', $result['completed_steps']);
        $this->assertContains('transfer_assets', $result['completed_steps']);
        $this->assertContains('transfer_payment', $result['completed_steps']);
        $this->assertContains('update_order_status', $result['completed_steps']);
    }

    #[Test]
    public function it_compensates_when_order_matching_fails()
    {
        $orderId = \Str::uuid()->toString();

        $input = [
            'order_id'          => 'invalid-order-id', // This will cause matching to fail
            'buyer_account_id'  => $this->buyerAccount->uuid,
            'seller_account_id' => $this->sellerAccount->uuid,
            'base_currency'     => 'BTC',
            'quote_currency'    => 'USD',
            'amount'            => 0.1,
            'price'             => 50000.0,
            'type'              => 'buy',
        ];

        $workflow = WorkflowStub::make(OrderFulfillmentSaga::class);

        // Mock the order matching to fail
        $this->mock(\App\Domain\Exchange\Workflows\OrderMatchingWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andThrow(new \Exception('Order not found'));
        });

        $result = $workflow->execute($input)->wait();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('compensated_steps', $result);
        $this->assertContains('lock_buyer_funds', $result['compensated_steps']);
    }

    #[Test]
    public function it_compensates_when_asset_transfer_fails()
    {
        $orderId = \Str::uuid()->toString();

        $input = [
            'order_id'          => $orderId,
            'buyer_account_id'  => $this->buyerAccount->uuid,
            'seller_account_id' => $this->sellerAccount->uuid,
            'base_currency'     => 'BTC',
            'quote_currency'    => 'USD',
            'amount'            => 10.0, // More than seller has - will fail
            'price'             => 50000.0,
            'type'              => 'buy',
        ];

        $workflow = WorkflowStub::make(OrderFulfillmentSaga::class);

        // Mock the asset transfer to fail
        $this->mock(\App\Domain\Wallet\Workflows\WalletTransferWorkflow::class, function (MockInterface $mock) use ($input) {
            $mock->shouldReceive('execute')
                ->with(
                    $input['seller_account_id'],
                    $input['buyer_account_id'],
                    $input['base_currency'],
                    $input['amount']
                )
                ->andThrow(new \Exception('Insufficient balance'));
        });

        $result = $workflow->execute($input)->wait();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient balance', $result['error']);
        $this->assertArrayHasKey('compensated_steps', $result);
        $this->assertContains('lock_buyer_funds', $result['compensated_steps']);
        $this->assertContains('match_order', $result['compensated_steps']);
    }

    #[Test]
    public function it_handles_compensation_failures_gracefully()
    {
        $orderId = \Str::uuid()->toString();

        $input = [
            'order_id'          => $orderId,
            'buyer_account_id'  => $this->buyerAccount->uuid,
            'seller_account_id' => $this->sellerAccount->uuid,
            'base_currency'     => 'BTC',
            'quote_currency'    => 'USD',
            'amount'            => 0.1,
            'price'             => 50000.0,
            'type'              => 'buy',
        ];

        $workflow = WorkflowStub::make(OrderFulfillmentSaga::class);

        // Mock both the main operation and compensation to fail
        $this->mock(\App\Domain\Exchange\Workflows\OrderMatchingWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andThrow(new \Exception('Order matching failed'));
        });

        $this->mock(\App\Domain\Account\Workflows\DepositAccountWorkflow::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andThrow(new \Exception('Compensation failed'));
        });

        $result = $workflow->execute($input)->wait();

        // Even if compensation fails, the saga should return a result
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        // The compensation step should still be listed even if it failed
        $this->assertArrayHasKey('compensated_steps', $result);
    }

    #[Test]
    public function it_validates_input_parameters()
    {
        $this->expectException(\TypeError::class);

        $workflow = WorkflowStub::make(OrderFulfillmentSaga::class);

        // Missing required fields should cause an error
        $workflow->execute([])->wait();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
