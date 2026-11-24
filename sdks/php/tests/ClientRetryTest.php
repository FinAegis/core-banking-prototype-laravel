<?php

namespace FinAegis\Tests;

use FinAegis\Client;
use FinAegis\Exceptions\FinAegisException;
use FinAegis\Exceptions\NetworkException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * The client sends a failed request again after a connection error, a 5xx
 * or a 429. A POST may only be sent again with the same Idempotency-Key, so
 * the API can answer the repeat with the first result instead of running it
 * a second time.
 *
 * @covers \FinAegis\Client
 * @covers \FinAegis\Resources\BaseResource
 * @covers \FinAegis\Resources\Transfers
 * @covers \FinAegis\Resources\Accounts
 * @covers \FinAegis\Models\BaseModel
 * @covers \FinAegis\Models\Transfer
 * @covers \FinAegis\Models\Transaction
 */
final class ClientRetryTest extends TestCase
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /** @var list<RequestInterface> */
    private array $sent = [];

    /**
     * @param  list<Response|\Throwable>  $outcomes
     */
    private function client(array $outcomes, int $maxRetries): Client
    {
        $this->sent = [];
        $mock = new MockHandler($outcomes);

        $handler = function (RequestInterface $request, array $options) use ($mock) {
            $this->sent[] = $request;
            // Retry back-off is not under test; do not sleep for it.
            unset($options['delay']);

            return $mock($request, $options);
        };

        return new Client('test-key', [
            'base_url' => 'https://api.example.test/v2',
            'max_retries' => $maxRetries,
            'handler' => $handler,
        ]);
    }

    private static function created(): Response
    {
        return new Response(201, ['Content-Type' => 'application/json'], (string) json_encode([
            'data' => ['uuid' => 't-1', 'status' => 'pending'],
        ]));
    }

    /**
     * @return list<string>
     */
    private function keysSent(): array
    {
        return array_map(
            static fn (RequestInterface $request): string => $request->getHeaderLine('Idempotency-Key'),
            $this->sent
        );
    }

    public function testRetriesATransferWithTheSameIdempotencyKeyAfterA5xx(): void
    {
        $client = $this->client([new Response(503), new Response(502), self::created()], 2);

        $transfer = $client->transfers->create('acc-1', 'acc-2', 25.0, 'USD');

        $this->assertSame('t-1', $transfer->uuid);
        $this->assertCount(3, $this->sent);
        $keys = $this->keysSent();
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $keys[0]);
        $this->assertSame([$keys[0], $keys[0], $keys[0]], $keys);
    }

    public function testRetriesWithTheSameKeyAfterTheConnectionDrops(): void
    {
        $dropped = new RequestException('Connection reset by peer', new Request('POST', 'transfers'));
        $client = $this->client([$dropped, self::created()], 1);

        $client->transfers->create('acc-1', 'acc-2', 25.0, 'USD');

        $keys = $this->keysSent();
        $this->assertCount(2, $keys);
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $keys[0]);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function testRetriesWithTheSameKeyAfterATimeoutAndReportsANetworkError(): void
    {
        $timeout = new ConnectException('Operation timed out', new Request('POST', 'transfers'));
        $client = $this->client([$timeout, $timeout], 1);

        try {
            $client->transfers->create('acc-1', 'acc-2', 25.0, 'USD');
            $this->fail('Expected the timeout to be raised');
        } catch (NetworkException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }

        $keys = $this->keysSent();
        $this->assertCount(2, $keys);
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $keys[0]);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function testUsesANewKeyForEachTransfer(): void
    {
        $client = $this->client([self::created(), self::created()], 0);

        $client->transfers->create('acc-1', 'acc-2', 25.0, 'USD');
        $client->transfers->create('acc-1', 'acc-2', 25.0, 'USD');

        $keys = $this->keysSent();
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $keys[0]);
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $keys[1]);
        $this->assertNotSame($keys[0], $keys[1]);
    }

    public function testUsesTheCallersKeyAndRepeatsItOnRetry(): void
    {
        $client = $this->client([new Response(500), self::created()], 1);

        $client->transfers->create('acc-1', 'acc-2', 25.0, 'USD', 'invoice-42', 'order-2026-11-24-0001');

        $this->assertSame(['order-2026-11-24-0001', 'order-2026-11-24-0001'], $this->keysSent());
    }

    public function testMakesExactlyOneAttemptWhenMaxRetriesIsZero(): void
    {
        $client = $this->client([new Response(503), self::created()], 0);

        try {
            $client->transfers->create('acc-1', 'acc-2', 25.0, 'USD');
            $this->fail('Expected the 503 to be raised');
        } catch (FinAegisException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertCount(1, $this->sent);
    }

    public function testDoesNotRetryAPostThatHasNoIdempotencyKey(): void
    {
        $client = $this->client([new Response(503), self::created()], 3);

        try {
            // A caller that removes the key on purpose: the request must not
            // be sent again, because the API could not tell it is a repeat.
            $client->request('POST', 'transfers', ['json' => [], 'idempotency_key' => false]);
            $this->fail('Expected the 503 to be raised');
        } catch (FinAegisException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertCount(1, $this->sent);
        $this->assertSame([''], $this->keysSent());
    }

    public function testAddsAKeyToDepositsAndWithdrawals(): void
    {
        $transaction = new Response(201, [], (string) json_encode(['data' => ['id' => 'tx-1']]));
        $client = $this->client([$transaction, $transaction], 0);

        $client->accounts->deposit('acc-1', 10.0, 'USD', null, 'deposit-key-0000000001');
        $client->accounts->withdraw('acc-1', 10.0, 'USD');

        $keys = $this->keysSent();
        $this->assertSame('deposit-key-0000000001', $keys[0]);
        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $keys[1]);
    }

    public function testGetRequestsCarryNoKeyAndAreStillRetried(): void
    {
        $found = new Response(200, [], (string) json_encode(['data' => ['uuid' => 't-1']]));
        $client = $this->client([new Response(503), $found], 1);

        $client->transfers->get('t-1');

        $this->assertCount(2, $this->sent);
        $this->assertSame(['', ''], $this->keysSent());
    }
}
