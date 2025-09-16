<?php

namespace Tests\Unit\Domain\Compliance\Events;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Events\SuspiciousActivityDetected;
use App\Models\User;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class SuspiciousActivityDetectedTest extends DomainTestCase
{
    use RefreshDatabase;

    private function createTransaction(array $eventProperties = []): Transaction
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        return Transaction::factory()->forAccount($account)->create([
            'event_properties' => array_merge([
                'amount'    => 10000,
                'assetCode' => 'USD',
                'metadata'  => [],
            ], $eventProperties),
        ]);
    }

    #[Test]
    public function test_creates_event_with_transaction_and_alerts(): void
    {
        $transaction = $this->createTransaction([
            'amount'    => 100000,
            'assetCode' => 'USD',
            'metadata'  => ['type' => 'wire_transfer'],
        ]);

        $alerts = [
            [
                'type'      => 'large_transaction',
                'severity'  => 'high',
                'message'   => 'Transaction amount exceeds threshold',
                'threshold' => 50000,
                'actual'    => 100000,
            ],
            [
                'type'     => 'velocity_check',
                'severity' => 'medium',
                'message'  => 'Multiple transactions in short period',
                'count'    => 5,
                'period'   => '1 hour',
            ],
        ];

        $event = new SuspiciousActivityDetected($transaction, $alerts);

        $this->assertSame($transaction->id, $event->transaction->id);
        $this->assertEquals($alerts, $event->alerts);
        $this->assertCount(2, $event->alerts);
    }

    #[Test]
    public function test_event_uses_required_traits(): void
    {
        $transaction = $this->createTransaction();
        $event = new SuspiciousActivityDetected($transaction, []);

        $traits = class_uses($event);

        $this->assertArrayHasKey('Illuminate\Foundation\Events\Dispatchable', $traits);
        $this->assertArrayHasKey('Illuminate\Broadcasting\InteractsWithSockets', $traits);
        $this->assertArrayHasKey('Illuminate\Queue\SerializesModels', $traits);
    }

    #[Test]
    public function test_event_properties_are_readonly(): void
    {
        $transaction = $this->createTransaction();
        $alerts = [['type' => 'test']];

        $event = new SuspiciousActivityDetected($transaction, $alerts);

        // Properties are readonly, attempting to modify should cause error
        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/Cannot modify readonly property/');
        /** @phpstan-ignore-next-line */
        $event->transaction = $this->createTransaction();
    }

    #[Test]
    public function test_event_serializes_correctly(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        $transaction = Transaction::factory()->forAccount($account)->create([
            'event_properties' => [
                'amount'    => 10000,
                'assetCode' => 'USD',
                'metadata'  => [],
            ],
            'meta_data' => [
                'type'        => 'transfer',
                'reference'   => 'SUSP-123',
                'description' => null,
            ],
        ]);

        $alerts = [
            ['type' => 'pattern_match', 'pattern' => 'unusual_destination'],
        ];

        $event = new SuspiciousActivityDetected($transaction, $alerts);

        // Serialize and unserialize
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertEquals($transaction->id, $unserialized->transaction->id);
        $this->assertEquals('SUSP-123', $unserialized->transaction->meta_data['reference']);
        $this->assertEquals($alerts, $unserialized->alerts);
    }

    #[Test]
    public function test_handles_empty_alerts_array(): void
    {
        $transaction = $this->createTransaction();

        $event = new SuspiciousActivityDetected($transaction, []);

        $this->assertEmpty($event->alerts);
        $this->assertIsArray($event->alerts);
    }

    #[Test]
    public function test_handles_complex_alert_structures(): void
    {
        $transaction = $this->createTransaction();

        $complexAlerts = [
            [
                'type'       => 'ml_detection',
                'model'      => 'fraud_detector_v2',
                'confidence' => 0.89,
                'features'   => [
                    'amount_zscore'    => 3.2,
                    'time_since_last'  => 120,
                    'destination_risk' => 'high',
                ],
                'metadata' => [
                    'model_version' => '2.1.0',
                    'training_date' => '2024-01-01',
                ],
            ],
            [
                'type'            => 'rule_based',
                'rules_triggered' => ['R001', 'R045', 'R102'],
                'combined_score'  => 85,
                'action_required' => 'manual_review',
            ],
        ];

        $event = new SuspiciousActivityDetected($transaction, $complexAlerts);

        $this->assertEquals($complexAlerts, $event->alerts);
        $this->assertEquals(0.89, $event->alerts[0]['confidence']);
        $this->assertCount(3, $event->alerts[1]['rules_triggered']);
    }

    #[Test]
    public function test_can_be_dispatched_as_event(): void
    {
        $transaction = $this->createTransaction();
        $alerts = [['type' => 'test_alert']];

        Event::fake();

        event(new SuspiciousActivityDetected($transaction, $alerts));

        Event::assertDispatched(SuspiciousActivityDetected::class, function ($event) use ($transaction, $alerts) {
            return $event->transaction->id === $transaction->id && $event->alerts === $alerts;
        });
    }
}
