<?php

namespace Tests\Unit\Domain\Lending\ValueObjects;

use App\Domain\Lending\ValueObjects\CreditScore;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreditScoreTest extends TestCase
{
    #[Test]
    public function test_creates_valid_credit_score(): void
    {
        $score = 720;
        $bureau = 'Experian';
        $report = [
            'accounts'      => 5,
            'inquiries'     => 2,
            'delinquencies' => 0,
        ];

        $creditScore = new CreditScore($score, $bureau, $report);

        $this->assertEquals($score, $creditScore->score);
        $this->assertEquals($bureau, $creditScore->bureau);
        $this->assertEquals($report, $creditScore->creditReport);
    }

    #[Test]
    public function test_throws_exception_for_score_below_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit score must be between 300 and 850');

        new CreditScore(299, 'Equifax', []);
    }

    #[Test]
    public function test_throws_exception_for_score_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit score must be between 300 and 850');

        new CreditScore(851, 'TransUnion', []);
    }

    #[Test]
    public function test_accepts_boundary_values(): void
    {
        $minScore = new CreditScore(300, 'Bureau', []);
        $maxScore = new CreditScore(850, 'Bureau', []);

        $this->assertEquals(300, $minScore->score);
        $this->assertEquals(850, $maxScore->score);
    }

    #[Test]
    public function test_is_excellent_returns_true_for_scores_800_and_above(): void
    {
        $excellentScores = [800, 825, 850];

        foreach ($excellentScores as $score) {
            $creditScore = new CreditScore($score, 'Bureau', []);
            $this->assertTrue($creditScore->isExcellent(), "Score {$score} should be excellent");
        }
    }

    #[Test]
    public function test_is_excellent_returns_false_for_scores_below_800(): void
    {
        $nonExcellentScores = [799, 750, 700, 600, 500];

        foreach ($nonExcellentScores as $score) {
            $creditScore = new CreditScore($score, 'Bureau', []);
            $this->assertFalse($creditScore->isExcellent(), "Score {$score} should not be excellent");
        }
    }

    #[Test]
    public function test_is_good_returns_true_for_scores_700_to_799(): void
    {
        $goodScores = [700, 750, 799];

        foreach ($goodScores as $score) {
            $creditScore = new CreditScore($score, 'Bureau', []);
            $this->assertTrue($creditScore->isGood(), "Score {$score} should be good");
        }
    }

    #[Test]
    public function test_is_good_returns_false_for_scores_outside_range(): void
    {
        $notGoodScores = [699, 800, 850, 600, 500];

        foreach ($notGoodScores as $score) {
            $creditScore = new CreditScore($score, 'Bureau', []);
            $this->assertFalse($creditScore->isGood(), "Score {$score} should not be good");
        }
    }

    #[Test]
    public function test_is_fair_returns_true_for_scores_600_to_699(): void
    {
        $fairScores = [600, 650, 699];

        foreach ($fairScores as $score) {
            $creditScore = new CreditScore($score, 'Bureau', []);
            $this->assertTrue($creditScore->isFair(), "Score {$score} should be fair");
        }
    }

    #[Test]
    public function test_is_poor_returns_true_for_scores_below_600(): void
    {
        $poorScores = [300, 400, 500, 599];

        foreach ($poorScores as $score) {
            $creditScore = new CreditScore($score, 'Bureau', []);
            $this->assertTrue($creditScore->isPoor(), "Score {$score} should be poor");
        }
    }

    #[Test]
    public function test_is_poor_returns_false_for_scores_600_and_above(): void
    {
        $notPoorScores = [600, 700, 800];

        foreach ($notPoorScores as $score) {
            $creditScore = new CreditScore($score, 'Bureau', []);
            $this->assertFalse($creditScore->isPoor(), "Score {$score} should not be poor");
        }
    }

    #[Test]
    public function test_to_array_returns_correct_structure(): void
    {
        $score = 725;
        $bureau = 'Experian';
        $report = [
            'total_accounts'     => 8,
            'open_accounts'      => 5,
            'credit_utilization' => 0.25,
            'payment_history'    => 'excellent',
        ];

        $creditScore = new CreditScore($score, $bureau, $report);
        $array = $creditScore->toArray();

        $this->assertEquals([
            'score'         => 725,
            'bureau'        => 'Experian',
            'credit_report' => $report,
        ], $array);
    }

    #[Test]
    public function test_handles_different_bureau_names(): void
    {
        $bureaus = ['Experian', 'Equifax', 'TransUnion', 'Custom Bureau'];

        foreach ($bureaus as $bureau) {
            $creditScore = new CreditScore(700, $bureau, []);
            $this->assertEquals($bureau, $creditScore->bureau);
        }
    }

    #[Test]
    public function test_handles_complex_credit_report_data(): void
    {
        $complexReport = [
            'accounts' => [
                'credit_cards'  => 3,
                'auto_loans'    => 1,
                'mortgages'     => 1,
                'student_loans' => 2,
            ],
            'payment_history' => [
                'on_time_payments' => 156,
                'late_payments'    => 2,
                'missed_payments'  => 0,
            ],
            'credit_inquiries' => [
                'hard_inquiries' => 3,
                'soft_inquiries' => 5,
            ],
            'negative_items' => [],
            'public_records' => [],
        ];

        $creditScore = new CreditScore(760, 'Equifax', $complexReport);

        $this->assertEquals($complexReport, $creditScore->creditReport);
        $this->assertIsArray($creditScore->creditReport['accounts']);
        $this->assertEquals(3, $creditScore->creditReport['accounts']['credit_cards']);
    }

    #[Test]
    public function test_properties_are_readonly(): void
    {
        $creditScore = new CreditScore(700, 'Bureau', []);

        // PHP 8.1+ readonly properties throw Error when attempting to modify
        $this->expectException(Error::class);
        /** @phpstan-ignore-next-line */
        $creditScore->score = 750;
    }
}
