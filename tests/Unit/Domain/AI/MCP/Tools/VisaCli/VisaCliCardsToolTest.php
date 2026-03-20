<?php

declare(strict_types=1);

use App\Domain\AI\MCP\Tools\VisaCli\VisaCliCardsTool;
use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\DataObjects\VisaCliCard;
use App\Domain\VisaCli\Enums\VisaCliCardStatus;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    /** @var VisaCliClientInterface&Mockery\MockInterface $client */
    $this->client = Mockery::mock(VisaCliClientInterface::class);
    $this->tool = new VisaCliCardsTool($this->client);
});

it('has correct name and category', function (): void {
    expect($this->tool->getName())->toBe('visacli.cards')
        ->and($this->tool->getCategory())->toBe('visacli')
        ->and($this->tool->isCacheable())->toBeTrue()
        ->and($this->tool->getCacheTtl())->toBe(300);
});

it('validates any input as valid', function (): void {
    expect($this->tool->validateInput([]))->toBeTrue()
        ->and($this->tool->validateInput(['user_id' => '123']))->toBeTrue();
});

it('returns cards list', function (): void {
    $this->client->shouldReceive('listCards')
        ->once()
        ->with(null)
        ->andReturn([
            new VisaCliCard(
                cardIdentifier: 'card_001',
                last4: '1234',
                network: 'visa',
                status: VisaCliCardStatus::ENROLLED,
            ),
            new VisaCliCard(
                cardIdentifier: 'card_002',
                last4: '5678',
                network: 'visa',
                status: VisaCliCardStatus::ACTIVE,
            ),
        ]);

    $result = $this->tool->execute([]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2)
        ->and($result->getData()['cards'])->toHaveCount(2)
        ->and($result->getData()['cards'][0]['card_identifier'])->toBe('card_001');
});

it('filters cards by user_id', function (): void {
    $this->client->shouldReceive('listCards')
        ->once()
        ->with('user-123')
        ->andReturn([]);

    $result = $this->tool->execute(['user_id' => 'user-123']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(0);
});

it('returns failure on exception', function (): void {
    $this->client->shouldReceive('listCards')
        ->once()
        ->andThrow(new RuntimeException('Connection failed'));

    $result = $this->tool->execute([]);

    expect($result->isFailure())->toBeTrue()
        ->and($result->getError())->toContain('Connection failed');
});

it('has read-only capabilities', function (): void {
    $caps = $this->tool->getCapabilities();

    expect($caps)->toContain('read')
        ->and($caps)->toContain('visa-cli')
        ->and($caps)->not->toContain('write');
});
