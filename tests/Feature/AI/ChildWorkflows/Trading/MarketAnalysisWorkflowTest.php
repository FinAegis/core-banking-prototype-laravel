<?php

declare(strict_types=1);

namespace Tests\Feature\AI\ChildWorkflows\Trading;

use App\Domain\AI\Activities\Trading\CalculateMACDActivity;
use App\Domain\AI\Activities\Trading\CalculateRSIActivity;
use App\Domain\AI\Activities\Trading\IdentifyPatternsActivity;
use App\Domain\AI\ChildWorkflows\Trading\MarketAnalysisWorkflow;
use App\Domain\AI\Events\Trading\MarketAnalyzedEvent;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MarketAnalysisWorkflowTest extends TestCase
{
    private MarketAnalysisWorkflow $workflow;

    /** @var MockInterface */
    private MockInterface $mockRSIActivity;

    /** @var MockInterface */
    private MockInterface $mockMACDActivity;

    /** @var MockInterface */
    private MockInterface $mockPatternsActivity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = new MarketAnalysisWorkflow();

        // Create mocks for activities
        $this->mockRSIActivity = Mockery::mock(CalculateRSIActivity::class);
        $this->mockMACDActivity = Mockery::mock(CalculateMACDActivity::class);
        $this->mockPatternsActivity = Mockery::mock(IdentifyPatternsActivity::class);

        // Bind mocks to container
        $this->app->instance(CalculateRSIActivity::class, $this->mockRSIActivity);
        $this->app->instance(CalculateMACDActivity::class, $this->mockMACDActivity);
        $this->app->instance(IdentifyPatternsActivity::class, $this->mockPatternsActivity);
    }

    /** @test */
    public function it_executes_market_analysis_workflow(): void
    {
        // Arrange
        Event::fake();

        $conversationId = 'test-conversation-123';
        $symbol = 'BTC/USD';
        $marketData = [
            'prices'    => [50000, 51000, 52000],
            'volumes'   => [1000000, 1100000, 1200000],
            'timeframe' => '1h',
        ];

        $rsiResult = [
            'value'    => 65.5,
            'signal'   => 'bullish',
            'strength' => 0.7,
        ];

        $macdResult = [
            'macd'      => 150.5,
            'signal'    => 145.2,
            'histogram' => 5.3,
            'trend'     => 'bullish',
        ];

        $patternsResult = [
            'patterns'       => ['ascending_triangle'],
            'strength'       => 0.8,
            'recommendation' => 'buy',
        ];

        // Set up mock expectations
        $this->mockRSIActivity
            ->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['prices']) && isset($args['period']);
            }))
            ->andReturn($rsiResult);

        $this->mockMACDActivity
            ->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['prices']);
            }))
            ->andReturn($macdResult);

        $this->mockPatternsActivity
            ->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['prices']) && isset($args['volumes']);
            }))
            ->andReturn($patternsResult);

        // Act
        $generator = $this->workflow->execute($conversationId, $symbol, $marketData);
        $result = iterator_to_array($generator, false);
        $finalResult = end($result);

        // Assert
        $this->assertIsArray($finalResult);
        $this->assertEquals($symbol, $finalResult['symbol']);
        $this->assertArrayHasKey('indicators', $finalResult);
        $this->assertArrayHasKey('patterns', $finalResult);
        $this->assertArrayHasKey('sentiment', $finalResult);
        $this->assertArrayHasKey('timestamp', $finalResult);

        // Assert sentiment calculation
        $this->assertEquals('bullish', $finalResult['sentiment']['overall']);
        $this->assertGreaterThan(0, $finalResult['sentiment']['confidence']);

        // Assert event was dispatched
        Event::assertDispatched(MarketAnalyzedEvent::class, function ($event) use ($conversationId, $symbol) {
            return $event->conversationId === $conversationId
                && $event->symbol === $symbol;
        });
    }

    /** @test */
    public function it_calculates_bearish_sentiment(): void
    {
        // Arrange
        Event::fake();

        $conversationId = 'test-conversation-456';
        $symbol = 'ETH/USD';
        $marketData = [
            'prices'    => [3000, 2900, 2800],
            'volumes'   => [500000, 450000, 400000],
            'timeframe' => '1h',
        ];

        // Setup bearish indicators
        $this->mockRSIActivity
            ->shouldReceive('execute')
            ->andReturn([
                'value'    => 25,
                'signal'   => 'oversold',
                'strength' => 0.8,
            ]);

        $this->mockMACDActivity
            ->shouldReceive('execute')
            ->andReturn([
                'macd'      => -50,
                'signal'    => -45,
                'histogram' => -5,
                'trend'     => 'strong_bearish',
            ]);

        $this->mockPatternsActivity
            ->shouldReceive('execute')
            ->andReturn([
                'patterns'       => ['descending_triangle', 'bearish_flag'],
                'strength'       => 0.9,
                'recommendation' => 'strong_sell',
            ]);

        // Act
        $generator = $this->workflow->execute($conversationId, $symbol, $marketData);
        $result = iterator_to_array($generator, false);
        $finalResult = end($result);

        // Assert
        $sentiment = $finalResult['sentiment'];
        $this->assertContains($sentiment['overall'], ['bearish', 'very_bearish']);
        $this->assertGreaterThan($sentiment['bullish_score'], $sentiment['bearish_score']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}