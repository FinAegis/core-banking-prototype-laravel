<?php

namespace Tests\Feature\Exchange;

use Tests\TestCase;
use App\Domain\Account\Aggregates\Account;
use App\Domain\Account\DataTransferObjects\AccountData;
use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Enums\AccountType;
use App\Domain\Exchange\Aggregates\Order;
use App\Domain\Exchange\Aggregates\OrderBook;
use App\Domain\Exchange\DataTransferObjects\OrderData;
use App\Domain\Exchange\Enums\OrderSide;
use App\Domain\Exchange\Enums\OrderType;
use App\Domain\Exchange\Enums\OrderStatus;
use App\Domain\Exchange\Workflows\OrderMatchingWorkflow;
use App\Domain\Exchange\ValueObjects\OrderMatchingInput;
use App\Domain\Transaction\Aggregates\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ExchangeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected string $buyerAccountId;
    protected string $sellerAccountId;
    protected string $orderBookId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create buyer account with USD
        $this->buyerAccountId = (string) Str::uuid();
        Account::create(
            $this->buyerAccountId,
            new AccountData(
                userId: 'buyer-user-id',
                name: 'Buyer Account',
                type: AccountType::PERSONAL,
                status: AccountStatus::ACTIVE,
                metadata: []
            )
        )->deposit('1000000.00', 'USD', 'Initial deposit')->persist();

        // Create seller account with BTC
        $this->sellerAccountId = (string) Str::uuid();
        Account::create(
            $this->sellerAccountId,
            new AccountData(
                userId: 'seller-user-id',
                name: 'Seller Account',
                type: AccountType::PERSONAL,
                status: AccountStatus::ACTIVE,
                metadata: []
            )
        )->deposit('10.00', 'BTC', 'Initial deposit')->persist();

        // Initialize order book
        $this->orderBookId = OrderBook::generateId('BTC', 'USD');
        OrderBook::retrieve($this->orderBookId)
            ->initialize($this->orderBookId, 'BTC', 'USD')
            ->persist();
    }

    public function test_can_match_buy_and_sell_orders(): void
    {
        // Create sell order
        $sellOrderId = (string) Str::uuid();
        $sellOrderData = new OrderData(
            accountId: $this->sellerAccountId,
            side: OrderSide::SELL,
            type: OrderType::LIMIT,
            baseAsset: 'BTC',
            quoteAsset: 'USD',
            amount: '1.00',
            price: '50000.00',
            status: OrderStatus::PENDING,
            metadata: []
        );
        
        Order::create($sellOrderId, $sellOrderData)->persist();
        
        // Create buy order
        $buyOrderId = (string) Str::uuid();
        $buyOrderData = new OrderData(
            accountId: $this->buyerAccountId,
            side: OrderSide::BUY,
            type: OrderType::LIMIT,
            baseAsset: 'BTC',
            quoteAsset: 'USD',
            amount: '1.00',
            price: '50000.00',
            status: OrderStatus::PENDING,
            metadata: []
        );
        
        Order::create($buyOrderId, $buyOrderData)->persist();

        // Execute workflow for sell order
        $sellWorkflow = new OrderMatchingWorkflow();
        $sellInput = new OrderMatchingInput(
            orderId: $sellOrderId,
            maxIterations: 10
        );
        
        $sellResult = iterator_to_array($sellWorkflow->execute($sellInput));
        $this->assertTrue($sellResult[count($sellResult) - 1]->success);
        
        // Execute workflow for buy order
        $buyWorkflow = new OrderMatchingWorkflow();
        $buyInput = new OrderMatchingInput(
            orderId: $buyOrderId,
            maxIterations: 10
        );
        
        $buyResult = iterator_to_array($buyWorkflow->execute($buyInput));
        $this->assertTrue($buyResult[count($buyResult) - 1]->success);

        // Verify accounts have been updated
        $buyerAccount = Account::retrieve($this->buyerAccountId);
        $sellerAccount = Account::retrieve($this->sellerAccountId);

        // Buyer should have BTC and less USD
        $this->assertEquals('1.00', $buyerAccount->getBalance('BTC'));
        $this->assertEquals('950000.00', $buyerAccount->getBalance('USD')); // 1000000 - 50000

        // Seller should have USD and less BTC
        $this->assertEquals('50000.00', $sellerAccount->getBalance('USD'));
        $this->assertEquals('9.00', $sellerAccount->getBalance('BTC')); // 10 - 1
    }

    public function test_insufficient_balance_fails_order(): void
    {
        // Create buy order with insufficient balance
        $buyOrderId = (string) Str::uuid();
        $buyOrderData = new OrderData(
            accountId: $this->buyerAccountId,
            side: OrderSide::BUY,
            type: OrderType::LIMIT,
            baseAsset: 'BTC',
            quoteAsset: 'USD',
            amount: '100.00', // Trying to buy 100 BTC
            price: '50000.00', // Would need 5,000,000 USD
            status: OrderStatus::PENDING,
            metadata: []
        );
        
        Order::create($buyOrderId, $buyOrderData)->persist();

        // Execute workflow
        $workflow = new OrderMatchingWorkflow();
        $input = new OrderMatchingInput(
            orderId: $buyOrderId,
            maxIterations: 10
        );
        
        $result = iterator_to_array($workflow->execute($input));
        $finalResult = $result[count($result) - 1];
        
        $this->assertFalse($finalResult->success);
        $this->assertStringContainsString('Insufficient balance', $finalResult->message);
    }

    public function test_partial_order_fill(): void
    {
        // Create large sell order
        $sellOrderId = (string) Str::uuid();
        $sellOrderData = new OrderData(
            accountId: $this->sellerAccountId,
            side: OrderSide::SELL,
            type: OrderType::LIMIT,
            baseAsset: 'BTC',
            quoteAsset: 'USD',
            amount: '5.00',
            price: '50000.00',
            status: OrderStatus::PENDING,
            metadata: []
        );
        
        Order::create($sellOrderId, $sellOrderData)->persist();
        
        // Create smaller buy order
        $buyOrderId = (string) Str::uuid();
        $buyOrderData = new OrderData(
            accountId: $this->buyerAccountId,
            side: OrderSide::BUY,
            type: OrderType::LIMIT,
            baseAsset: 'BTC',
            quoteAsset: 'USD',
            amount: '2.00', // Only buying 2 BTC
            price: '50000.00',
            status: OrderStatus::PENDING,
            metadata: []
        );
        
        Order::create($buyOrderId, $buyOrderData)->persist();

        // Execute workflows
        $sellWorkflow = new OrderMatchingWorkflow();
        $sellInput = new OrderMatchingInput(orderId: $sellOrderId);
        iterator_to_array($sellWorkflow->execute($sellInput));
        
        $buyWorkflow = new OrderMatchingWorkflow();
        $buyInput = new OrderMatchingInput(orderId: $buyOrderId);
        $buyResult = iterator_to_array($buyWorkflow->execute($buyInput));
        
        $finalResult = $buyResult[count($buyResult) - 1];
        $this->assertTrue($finalResult->success);
        $this->assertEquals('filled', $finalResult->status);
        
        // Check sell order is partially filled
        $sellOrder = Order::retrieve($sellOrderId);
        $this->assertEquals('3.00', $sellOrder->getRemainingAmount()->getValue()); // 5 - 2 = 3
    }
}