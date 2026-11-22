<?php

declare(strict_types=1);

use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url' => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'  => 'test-key',
    ]);
});

it('sends the api key header and decodes the body', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['id' => 'u1', 'email' => 'a@example.com'])]);

    $user = LepoClient::fromConfig()->getUser('u1');

    expect($user)->toBe(['id' => 'u1', 'email' => 'a@example.com']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://lepo.test/api/v2/users/u1'
        && $request->hasHeader('x-api-key', 'test-key'));
});

it('raises a transport error on 5xx', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['message' => 'down'], 503)]);

    try {
        LepoClient::fromConfig()->createUser(['email' => 'a@example.com']);
        $this->fail('Expected a LepoApiException.');
    } catch (LepoApiException $e) {
        expect($e->httpStatus())->toBe(503);
    }
});

it('raises a business error with the lepo code on 4xx', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['message' => 'bad', 'code' => 'VALIDATION'], 400)]);

    try {
        LepoClient::fromConfig()->createUser(['email' => 'secret-person@example.com', 'firstName' => 'Secret']);
        $this->fail('Expected a LepoApiException.');
    } catch (LepoApiException $e) {
        expect($e->lepoCode())->toBe('VALIDATION')
            ->and($e->httpStatus())->toBe(400)
            ->and($e->getMessage())->not->toContain('secret-person@example.com')
            ->and($e->getMessage())->not->toContain('Secret');
    }
});

it('raises on a 200 response that reports failure', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['success' => false, 'errorCode' => 'CARD_LOCKED', 'errorMessage' => 'x'])]);

    try {
        LepoClient::fromConfig()->getCardWidget('c1', 'u1');
        $this->fail('Expected a LepoApiException.');
    } catch (LepoApiException $e) {
        expect($e->lepoCode())->toBe('CARD_LOCKED');
    }
});

it('strips card secrets from card responses', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['id' => 'c1', 'cardNo' => '4111111111111111', 'cvv' => '123', 'expMonth' => '12'])]);

    $card = LepoClient::fromConfig()->getCard('c1', 'u1');

    expect($card)->not->toHaveKey('cvv')
        ->and($card['cardNo'])->toBe('************1111')
        ->and($card['expMonth'])->toBe('12');
});

it('strips card secrets from every card in a list', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['cards' => [['id' => 'c1', 'cardNo' => '5555444433332222', 'cvv' => '999']], 'total' => 1])]);

    $list = LepoClient::fromConfig()->listCards('u1');

    expect($list['cards'][0])->not->toHaveKey('cvv')
        ->and($list['cards'][0]['cardNo'])->toBe('************2222')
        ->and($list['total'])->toBe(1);
});

it('retries idempotent reads but not writes', function (): void {
    $reads = 0;
    $writes = 0;
    Http::fake(function (Request $request) use (&$reads, &$writes) {
        if ($request->method() === 'GET') {
            if (++$reads === 1) {
                throw new ConnectionException('reset');
            }

            return Http::response(['userId' => 'u1', 'addresses' => []]);
        }

        $writes++;

        throw new ConnectionException('reset');
    });

    expect(LepoClient::fromConfig()->getCryptoAddresses('u1'))->toBe(['userId' => 'u1', 'addresses' => []])
        ->and($reads)->toBe(2);

    expect(fn () => LepoClient::fromConfig()->topUpCard('c1', '10.00'))->toThrow(LepoApiException::class);
    expect($writes)->toBe(1);
});

it('refuses to call out without an api key', function (): void {
    config(['cardissuance.issuers.lepo.api_key' => '']);
    Http::fake();

    expect(fn () => LepoClient::fromConfig()->getUser('u1'))->toThrow(LepoApiException::class);
    Http::assertNothingSent();
});

it('raises when a successful response is not json', function (): void {
    Http::fake(['lepo.test/*' => Http::response('<html>proxy page</html>', 200, ['Content-Type' => 'text/html'])]);

    expect(fn () => LepoClient::fromConfig()->topUpCard('c1', '10.00'))->toThrow(LepoApiException::class);
});

it('accepts an empty successful response', function (): void {
    Http::fake(['lepo.test/*' => Http::response('', 204)]);

    expect(LepoClient::fromConfig()->deleteCard('c1', 'u1'))->toBe([]);
});

it('masks card numbers whatever their type, case or depth', function (): void {
    Http::fake(['lepo.test/*' => Http::response([
        'id'         => 'c1',
        'CardNumber' => 4111111111111111,
        'CVV'        => '123',
        'details'    => ['pan' => '5555444433332222', 'cvv2' => '999'],
    ])]);

    $card = LepoClient::fromConfig()->getCard('c1', 'u1');

    expect($card)->not->toHaveKey('CVV')
        ->and($card['CardNumber'])->toBe('************1111')
        ->and($card['details'])->not->toHaveKey('cvv2')
        ->and($card['details']['pan'])->toBe('************2222');
});

it('asks for the first page of card transactions as page zero', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['items' => []])]);

    LepoClient::fromConfig()->getCardTransactions('c1', 'u1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=0'));
});

it('finds a user by our external id', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['users' => [['id' => 7, 'externalUserId' => 'ext-1']], 'total' => 1])]);

    expect(LepoClient::fromConfig()->findUserByExternalId('ext-1'))->toBe(['id' => 7, 'externalUserId' => 'ext-1']);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'externalUserId=ext-1'));
});

it('returns null when no user has our external id', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['users' => [], 'total' => 0])]);

    expect(LepoClient::fromConfig()->findUserByExternalId('ext-2'))->toBeNull();
});
