<?php

namespace Tests\Unit\Domain\Compliance\Aggregates;

use App\Domain\Compliance\Aggregates\AmlScreeningAggregate;
use App\Domain\Compliance\Events\AmlScreeningCompleted;
use App\Domain\Compliance\Events\AmlScreeningMatchStatusUpdated;
use App\Domain\Compliance\Events\AmlScreeningResultsRecorded;
use App\Domain\Compliance\Events\AmlScreeningReviewed;
use App\Domain\Compliance\Events\AmlScreeningStarted;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class AmlScreeningAggregateTest extends DomainTestCase
{
    #[Test]
    public function can_start_screening()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $aggregate->startScreening(
            'entity-123',
            'user',
            'AML-2025-00001',
            'comprehensive',
            'provider-name',
            ['name' => 'John Doe', 'dob' => '1990-01-01'],
            'provider-ref-123'
        );

        $aggregate->assertRecorded([
            new AmlScreeningStarted(
                'entity-123',
                'user',
                'AML-2025-00001',
                'comprehensive',
                'provider-name',
                ['name' => 'John Doe', 'dob' => '1990-01-01'],
                'provider-ref-123'
            ),
        ]);
    }

    #[Test]
    public function can_record_screening_results()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $aggregate->recordResults(
            ['matches' => 0],
            ['is_pep'   => false],
            ['articles' => []],
            [],
            0,
            'low',
            ['OFAC', 'EU', 'UN'],
            ['raw' => 'response']
        );

        $aggregate->assertRecorded([
            new AmlScreeningResultsRecorded(
                ['matches' => 0],
                ['is_pep'   => false],
                ['articles' => []],
                [],
                0,
                'low',
                ['OFAC', 'EU', 'UN'],
                ['raw' => 'response']
            ),
        ]);
    }

    #[Test]
    public function can_update_match_status()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $aggregate->updateMatchStatus(
            'match-123',
            'confirm',
            ['confirmed_by' => 'user-456'],
            'Confirmed match'
        );

        $aggregate->assertRecorded([
            new AmlScreeningMatchStatusUpdated(
                'match-123',
                'confirm',
                ['confirmed_by' => 'user-456'],
                'Confirmed match'
            ),
        ]);
    }

    #[Test]
    public function throws_exception_for_invalid_match_action()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action. Must be confirm, dismiss, or investigate.');

        $aggregate->updateMatchStatus(
            'match-123',
            'invalid-action',
            [],
            null
        );
    }

    #[Test]
    public function can_complete_screening()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $aggregate->completeScreening('completed', 12.5);

        $aggregate->assertRecorded([
            new AmlScreeningCompleted('completed', 12.5),
        ]);
    }

    #[Test]
    public function throws_exception_for_invalid_completion_status()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status. Must be completed or failed.');

        $aggregate->completeScreening('invalid-status', null);
    }

    #[Test]
    public function can_review_screening()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $aggregate->reviewScreening(
            'reviewer-123',
            'clear',
            'No matches found'
        );

        $aggregate->assertRecorded([
            new AmlScreeningReviewed(
                'reviewer-123',
                'clear',
                'No matches found'
            ),
        ]);
    }

    #[Test]
    public function throws_exception_for_invalid_review_decision()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::fake($uuid);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid decision. Must be clear, escalate, or block.');

        $aggregate->reviewScreening(
            'reviewer-123',
            'invalid-decision',
            'Some notes'
        );
    }

    #[Test]
    public function tracks_state_correctly()
    {
        $uuid = 'test-uuid';
        $aggregate = AmlScreeningAggregate::retrieve($uuid);

        // Start screening
        $aggregate->startScreening(
            'entity-123',
            'user',
            'AML-2025-00001',
            'comprehensive',
            'provider-name',
            ['name' => 'John Doe'],
            null
        );

        // Record results
        $aggregate->recordResults(
            ['matches' => 2],
            ['is_pep'   => true],
            ['articles' => []],
            [],
            2,
            'high',
            ['OFAC', 'EU'],
            null
        );

        // Update match statuses
        $aggregate->updateMatchStatus('match-1', 'confirm', [], 'Confirmed');
        $aggregate->updateMatchStatus('match-2', 'dismiss', [], 'False positive');

        // Complete and review
        $aggregate->completeScreening('completed', 5.2);
        $aggregate->reviewScreening('reviewer-123', 'escalate', 'PEP match confirmed');

        // Persist and retrieve to test state
        $aggregate->persist();

        $retrievedAggregate = AmlScreeningAggregate::retrieve($uuid);

        $this->assertEquals('completed', $retrievedAggregate->getStatus());
        $this->assertEquals(2, $retrievedAggregate->getTotalMatches());
        $this->assertEquals(1, $retrievedAggregate->getConfirmedMatches());
        $this->assertEquals(1, $retrievedAggregate->getFalsePositives());
        $this->assertEquals('high', $retrievedAggregate->getOverallRisk());
        $this->assertTrue($retrievedAggregate->isReviewed());
        $this->assertFalse($retrievedAggregate->requiresReview());
    }
}
