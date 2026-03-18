<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\EventSourcing;

use App\Domain\Shared\EventSourcing\DeadLetterQueueService;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class DeadLetterQueueServiceTest extends TestCase
{
    private DeadLetterQueueService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('event-streaming.prefix', 'test:events');
        config()->set('event-streaming.dead_letter.max_retries', 3);
        config()->set('event-streaming.dead_letter.max_length', 10000);
        config()->set('event-streaming.streams', ['account' => 'account-events']);

        $this->service = new DeadLetterQueueService();
    }

    public function test_send_to_dead_letter_stores_message(): void
    {
        Redis::shouldReceive('xrange')
            ->once()
            ->andReturn([]);

        Redis::shouldReceive('xadd')
            ->once()
            ->withArgs(function (string $key, string $id, array $fields): bool {
                return $key === 'test:events:dlq:account'
                    && $id === '*'
                    && $fields['original_message_id'] === '1234-0'
                    && $fields['consumer'] === 'consumer-1'
                    && $fields['error_message'] === 'Processing failed'
                    && $fields['retry_count'] === '1'
                    && $fields['domain'] === 'account';
            })
            ->andReturn('5678-0');

        Redis::shouldReceive('xtrim')
            ->once()
            ->andReturn(0);

        $result = $this->service->sendToDeadLetter(
            'account',
            '1234-0',
            ['event_class' => 'AccountCreated', 'aggregate_uuid' => 'abc-123'],
            'consumer-1',
            'Processing failed',
        );

        $this->assertEquals('5678-0', $result);
    }

    public function test_send_to_dead_letter_returns_null_on_failure(): void
    {
        Redis::shouldReceive('xrange')
            ->once()
            ->andReturn([]);

        Redis::shouldReceive('xadd')
            ->once()
            ->andThrow(new RuntimeException('Redis connection lost'));

        $result = $this->service->sendToDeadLetter(
            'account',
            '1234-0',
            ['event_class' => 'AccountCreated'],
            'consumer-1',
            'Processing failed',
        );

        $this->assertNull($result);
    }

    public function test_retry_from_dead_letter_republishes_message(): void
    {
        $dlqMessage = [
            '9999-0' => [
                'original_stream'     => 'test:events:account-events',
                'original_message_id' => '1234-0',
                'consumer'            => 'consumer-1',
                'error_message'       => 'Processing failed',
                'retry_count'         => '1',
                'payload'             => json_encode(['domain' => 'account', 'event_class' => 'AccountCreated', 'aggregate_uuid' => 'abc-123']),
                'failed_at'           => '2026-01-01T00:00:00+00:00',
                'domain'              => 'account',
            ],
        ];

        Redis::shouldReceive('xrange')
            ->once()
            ->with('test:events:dlq:account', '9999-0', '9999-0')
            ->andReturn($dlqMessage);

        Redis::shouldReceive('xadd')
            ->once()
            ->withArgs(function (string $key, string $id, array $fields): bool {
                return $key === 'test:events:account-events'
                    && $id === '*'
                    && $fields['retry_count'] === '1'
                    && $fields['retried_from'] === '9999-0';
            })
            ->andReturn('new-msg-0');

        Redis::shouldReceive('xdel')
            ->once()
            ->with('test:events:dlq:account', ['9999-0'])
            ->andReturn(1);

        $result = $this->service->retryFromDeadLetter('account', '9999-0');

        $this->assertTrue($result);
    }

    public function test_retry_from_dead_letter_fails_when_max_retries_exceeded(): void
    {
        $dlqMessage = [
            '9999-0' => [
                'original_stream'     => 'test:events:account-events',
                'original_message_id' => '1234-0',
                'consumer'            => 'consumer-1',
                'error_message'       => 'Processing failed',
                'retry_count'         => '3',
                'payload'             => json_encode(['domain' => 'account']),
                'failed_at'           => '2026-01-01T00:00:00+00:00',
                'domain'              => 'account',
            ],
        ];

        Redis::shouldReceive('xrange')
            ->once()
            ->with('test:events:dlq:account', '9999-0', '9999-0')
            ->andReturn($dlqMessage);

        $result = $this->service->retryFromDeadLetter('account', '9999-0');

        $this->assertFalse($result);
    }

    public function test_retry_from_dead_letter_fails_when_message_not_found(): void
    {
        Redis::shouldReceive('xrange')
            ->once()
            ->andReturn([]);

        $result = $this->service->retryFromDeadLetter('account', 'nonexistent-0');

        $this->assertFalse($result);
    }

    public function test_list_dead_letters_returns_messages(): void
    {
        $messages = [
            '1-0' => ['domain' => 'account', 'error_message' => 'Error 1'],
            '2-0' => ['domain' => 'account', 'error_message' => 'Error 2'],
        ];

        Redis::shouldReceive('xrange')
            ->once()
            ->with('test:events:dlq:account', '-', '+', 100)
            ->andReturn($messages);

        $result = $this->service->listDeadLetters('account');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('1-0', $result);
        $this->assertArrayHasKey('2-0', $result);
    }

    public function test_list_dead_letters_returns_empty_on_failure(): void
    {
        Redis::shouldReceive('xrange')
            ->once()
            ->andThrow(new RuntimeException('Redis error'));

        $result = $this->service->listDeadLetters('account');

        $this->assertEmpty($result);
    }

    public function test_list_dead_letters_returns_empty_when_false(): void
    {
        Redis::shouldReceive('xrange')
            ->once()
            ->andReturn(false);

        $result = $this->service->listDeadLetters('account');

        $this->assertEmpty($result);
    }

    public function test_purge_dead_letters_deletes_stream(): void
    {
        Redis::shouldReceive('del')
            ->once()
            ->with(['test:events:dlq:account'])
            ->andReturn(1);

        $result = $this->service->purgeDeadLetters('account');

        $this->assertTrue($result);
    }

    public function test_purge_dead_letters_returns_false_on_failure(): void
    {
        Redis::shouldReceive('del')
            ->once()
            ->andThrow(new RuntimeException('Redis error'));

        $result = $this->service->purgeDeadLetters('account');

        $this->assertFalse($result);
    }

    public function test_get_dead_letter_count_returns_stream_length(): void
    {
        Redis::shouldReceive('xlen')
            ->once()
            ->with('test:events:dlq:account')
            ->andReturn(42);

        $result = $this->service->getDeadLetterCount('account');

        $this->assertEquals(42, $result);
    }

    public function test_get_dead_letter_count_returns_zero_on_failure(): void
    {
        Redis::shouldReceive('xlen')
            ->once()
            ->andThrow(new RuntimeException('Redis error'));

        $result = $this->service->getDeadLetterCount('account');

        $this->assertEquals(0, $result);
    }

    public function test_has_exceeded_max_retries(): void
    {
        // Message with retry_count = 3 (equals max)
        $messages = [
            '1-0' => [
                'original_message_id' => '1234-0',
                'retry_count'         => '3',
            ],
        ];

        Redis::shouldReceive('xrange')
            ->once()
            ->andReturn($messages);

        $result = $this->service->hasExceededMaxRetries('account', '1234-0');

        $this->assertTrue($result);
    }

    public function test_has_not_exceeded_max_retries(): void
    {
        Redis::shouldReceive('xrange')
            ->once()
            ->andReturn([]);

        $result = $this->service->hasExceededMaxRetries('account', 'new-msg-0');

        $this->assertFalse($result);
    }

    public function test_send_to_dead_letter_increments_retry_count(): void
    {
        // First call: getRetryCount finds existing message with retry_count 1
        $existingMessages = [
            '1-0' => [
                'original_message_id' => '1234-0',
                'retry_count'         => '1',
            ],
        ];

        Redis::shouldReceive('xrange')
            ->once()
            ->andReturn($existingMessages);

        Redis::shouldReceive('xadd')
            ->once()
            ->withArgs(function (string $key, string $id, array $fields): bool {
                return $fields['retry_count'] === '2'; // 1 + 1
            })
            ->andReturn('dlq-msg-0');

        Redis::shouldReceive('xtrim')
            ->once()
            ->andReturn(0);

        $result = $this->service->sendToDeadLetter(
            'account',
            '1234-0',
            ['event_class' => 'AccountCreated'],
            'consumer-1',
            'Processing failed again',
        );

        $this->assertEquals('dlq-msg-0', $result);
    }

    public function test_list_dead_letters_with_custom_count(): void
    {
        Redis::shouldReceive('xrange')
            ->once()
            ->with('test:events:dlq:account', '-', '+', 10)
            ->andReturn([]);

        $result = $this->service->listDeadLetters('account', 10);

        $this->assertEmpty($result);
    }
}
