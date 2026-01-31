<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Services;

use App\Domain\AI\Services\LLMOrchestrationService;
use App\Domain\AI\Services\NaturalLanguageProcessorService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NaturalLanguageProcessorServiceTest extends TestCase
{
    private NaturalLanguageProcessorService $service;

    private LLMOrchestrationService $mockLlmService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLlmService = Mockery::mock(LLMOrchestrationService::class);
        $this->service = new NaturalLanguageProcessorService($this->mockLlmService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    #[DataProvider('balanceQueryProvider')]
    public function it_detects_balance_query_intent(string $query): void
    {
        $result = $this->service->processQuery($query);

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_BALANCE_QUERY);
        expect($result['confidence'])->toBeGreaterThan(0.5);
    }

    public static function balanceQueryProvider(): array
    {
        return [
            ['What is my balance?'],
            ['How much money do I have?'],
            ['Show my current balance'],
            ['What\'s in my savings?'],
            ['Check my available funds'],
        ];
    }

    #[Test]
    #[DataProvider('transactionQueryProvider')]
    public function it_detects_transaction_query_intent(string $query): void
    {
        $result = $this->service->processQuery($query);

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_TRANSACTION_QUERY);
        expect($result['confidence'])->toBeGreaterThan(0.5);
    }

    public static function transactionQueryProvider(): array
    {
        return [
            ['Show my recent transactions'],
            ['What did I spent last week?'],
            ['Show my purchase history'],
            ['List my transactions'],
            ['Show my expenses'],
        ];
    }

    #[Test]
    #[DataProvider('transferRequestProvider')]
    public function it_detects_transfer_request_intent(string $query): void
    {
        $result = $this->service->processQuery($query);

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_TRANSFER_REQUEST);
        expect($result['confidence'])->toBeGreaterThan(0.5);
    }

    public static function transferRequestProvider(): array
    {
        return [
            ['Transfer $100 to John'],
            ['Send money to my friend'],
            ['I want to wire $500'],
            ['Pay my rent'],
            ['Send funds to account'],
        ];
    }

    #[Test]
    #[DataProvider('loanInquiryProvider')]
    public function it_detects_loan_inquiry_intent(string $query): void
    {
        $result = $this->service->processQuery($query);

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_LOAN_INQUIRY);
        expect($result['confidence'])->toBeGreaterThan(0.5);
    }

    public static function loanInquiryProvider(): array
    {
        return [
            ['I want to apply for a loan'],
            ['What are your interest rates?'],
            ['Can I borrow money?'],
            ['Tell me about credit options'],
            ['I need financing'],
        ];
    }

    #[Test]
    #[DataProvider('investmentQueryProvider')]
    public function it_detects_investment_query_intent(string $query): void
    {
        $result = $this->service->processQuery($query);

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_INVESTMENT_QUERY);
        expect($result['confidence'])->toBeGreaterThan(0.5);
    }

    public static function investmentQueryProvider(): array
    {
        return [
            ['Show me my investment portfolio'],
            ['Show me my investments'],
            ['What is my GCU value?'],
            ['Tell me about yield options'],
            ['What are my asset holdings?'],
        ];
    }

    #[Test]
    public function it_extracts_amount_entities(): void
    {
        $result = $this->service->processQuery('Transfer $500 to John');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_AMOUNT);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_AMOUNT]['value'])->toBe(500.0);
    }

    #[Test]
    public function it_extracts_currency_entities(): void
    {
        $result = $this->service->processQuery('Show my balance in EUR');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_CURRENCY);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_CURRENCY])->toBe('EUR');
    }

    #[Test]
    public function it_extracts_date_range_entities(): void
    {
        $result = $this->service->processQuery('Show transactions from last week');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_DATE_RANGE);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_DATE_RANGE]['raw'])->toBe('last week');
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_DATE_RANGE])->toHaveKeys(['start', 'end']);
    }

    #[Test]
    public function it_extracts_recipient_for_transfers(): void
    {
        $result = $this->service->processQuery('Transfer $200 to Alice');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_RECIPIENT);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_RECIPIENT])->toBe('Alice');
    }

    #[Test]
    public function it_extracts_account_type(): void
    {
        $result = $this->service->processQuery('What is my savings account balance?');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_ACCOUNT);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_ACCOUNT])->toBe('savings');
    }

    #[Test]
    public function it_extracts_spending_category(): void
    {
        $result = $this->service->processQuery('Show my groceries transactions');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_CATEGORY);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_CATEGORY])->toBe('groceries');
    }

    #[Test]
    public function it_handles_unknown_intents(): void
    {
        $result = $this->service->processQuery('Hello there');

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_UNKNOWN);
        expect($result['confidence'])->toBeLessThan(0.5);
    }

    #[Test]
    public function it_generates_explanation(): void
    {
        $result = $this->service->processQuery('Transfer $500 to John last week');

        expect($result['explanation'])->toBeString();
        expect($result['explanation'])->toContain('initiating a money transfer');
    }

    #[Test]
    public function it_extracts_relative_date_ago_pattern(): void
    {
        $result = $this->service->processQuery('Show transactions from 5 days ago');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_DATE);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_DATE]['type'])->toBe('relative_past');
    }

    #[Test]
    public function it_handles_multiple_entities(): void
    {
        $result = $this->service->processQuery('Transfer $1,500 USD to John from my checking account');

        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_AMOUNT);
        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_CURRENCY);
        expect($result['entities'])->toHaveKey(NaturalLanguageProcessorService::ENTITY_ACCOUNT);
        expect($result['entities'][NaturalLanguageProcessorService::ENTITY_AMOUNT]['value'])->toBe(1500.0);
    }

    #[Test]
    public function it_calculates_confidence_based_on_entities(): void
    {
        $simpleQuery = $this->service->processQuery('balance');
        $detailedQuery = $this->service->processQuery('What is my checking account balance in USD?');

        expect($detailedQuery['confidence'])->toBeGreaterThanOrEqual($simpleQuery['confidence']);
    }

    #[Test]
    public function it_detects_compliance_intent(): void
    {
        $result = $this->service->processQuery('What is my KYC status?');

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_COMPLIANCE_QUERY);
    }

    #[Test]
    public function it_detects_general_query_intent(): void
    {
        $result = $this->service->processQuery('Help me understand the platform');

        expect($result['intent'])->toBe(NaturalLanguageProcessorService::INTENT_GENERAL_QUERY);
    }
}
