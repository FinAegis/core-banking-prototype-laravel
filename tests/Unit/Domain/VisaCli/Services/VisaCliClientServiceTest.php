<?php

declare(strict_types=1);

use App\Domain\VisaCli\Enums\VisaCliCardStatus;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Services\DemoVisaCliClient;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Cache::flush();
    $this->client = new DemoVisaCliClient();
});

it('reports initialized status', function (): void {
    $status = $this->client->getStatus();

    expect($status->initialized)->toBeTrue()
        ->and($status->version)->toBe('0.1.0-beta')
        ->and($status->githubUsername)->toBe('demo-user');
});

it('is always initialized in demo mode', function (): void {
    expect($this->client->isInitialized())->toBeTrue()
        ->and($this->client->initialize())->toBeTrue();
});

it('enrolls a card with correct data', function (): void {
    $card = $this->client->enrollCard('user-1');

    expect($card->cardIdentifier)->toStartWith('visa_demo_')
        ->and($card->network)->toBe('visa')
        ->and($card->status)->toBe(VisaCliCardStatus::ENROLLED)
        ->and($card->githubUsername)->toBe('demo-user')
        ->and($card->last4)->toHaveLength(4);
});

it('lists cards for a specific user', function (): void {
    $this->client->enrollCard('user-1');
    $this->client->enrollCard('user-1');
    $this->client->enrollCard('user-2');

    $user1Cards = $this->client->listCards('user-1');
    $user2Cards = $this->client->listCards('user-2');

    expect($user1Cards)->toHaveCount(2)
        ->and($user2Cards)->toHaveCount(1);
});

it('lists all cards when no user specified', function (): void {
    $this->client->enrollCard('user-1');
    $this->client->enrollCard('user-2');

    $allCards = $this->client->listCards();

    expect($allCards)->toHaveCount(2);
});

it('executes a payment and returns completed result', function (): void {
    $result = $this->client->pay('https://api.example.com/data', 500);

    expect($result->paymentReference)->toStartWith('visa_pay_demo_')
        ->and($result->status)->toBe(VisaCliPaymentStatus::COMPLETED)
        ->and($result->amountCents)->toBe(500)
        ->and($result->currency)->toBe('USD')
        ->and($result->url)->toBe('https://api.example.com/data')
        ->and($result->cardLast4)->toBe('4242');
});

it('executes payment with specific card', function (): void {
    $card = $this->client->enrollCard('user-1');
    $result = $this->client->pay('https://api.example.com/data', 100, $card->cardIdentifier);

    expect($result->status)->toBe(VisaCliPaymentStatus::COMPLETED)
        ->and($result->amountCents)->toBe(100);
});

it('returns enrolled card count in status', function (): void {
    $this->client->enrollCard('user-1');
    $this->client->enrollCard('user-2');

    $status = $this->client->getStatus();

    expect($status->enrolledCards)->toBe(2);
});
