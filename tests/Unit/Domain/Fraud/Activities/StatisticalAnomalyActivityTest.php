<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fraud\Activities;

use App\Domain\Fraud\Activities\StatisticalAnomalyActivity;
use App\Domain\Fraud\Enums\AnomalyType;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class StatisticalAnomalyActivityTest extends TestCase
{
    #[Test]
    public function test_activity_execute_returns_structured_result(): void
    {
        $input = [
            'context' => [
                'amount'                  => 1000,
                'transaction_history'     => array_map(fn ($i) => ['amount' => 500 + $i * 10], range(0, 29)),
                'daily_transaction_count' => 3,
                'hour_of_day'             => 14,
            ],
            'profile_id' => null,
        ];

        // Test the execute logic directly via reflection (Activity requires workflow context)
        $activity = $this->createPartialMock(StatisticalAnomalyActivity::class, []);
        $method = new ReflectionMethod(StatisticalAnomalyActivity::class, 'execute');
        $result = $method->invoke($activity, $input);

        $this->assertEquals(AnomalyType::Statistical->value, $result['anomaly_type']);
        $this->assertIsBool($result['detected']);
        $this->assertIsFloat($result['score']);
        $this->assertIsFloat($result['confidence']);
        $this->assertIsArray($result['results']);
    }
}
