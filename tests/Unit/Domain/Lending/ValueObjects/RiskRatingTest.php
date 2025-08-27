<?php

namespace Tests\Unit\Domain\Lending\ValueObjects;

use App\Domain\Lending\ValueObjects\RiskRating;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskRatingTest extends TestCase
{
    #[Test]
    public function test_creates_valid_risk_rating(): void
    {
        $rating = 'B';
        $defaultProbability = 0.05;
        $riskFactors = [
            'credit_score'      => 'Good',
            'debt_to_income'    => 'Low',
            'employment_status' => 'Stable',
        ];

        $riskRating = new RiskRating($rating, $defaultProbability, $riskFactors);

        $this->assertEquals($rating, $riskRating->rating);
        $this->assertEquals($defaultProbability, $riskRating->defaultProbability);
        $this->assertEquals($riskFactors, $riskRating->riskFactors);
    }

    #[Test]
    public function test_throws_exception_for_invalid_rating(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid risk rating. Must be A-F');

        new RiskRating('G', 0.1, []);
    }

    #[Test]
    public function test_throws_exception_for_invalid_rating_lowercase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid risk rating. Must be A-F');

        new RiskRating('a', 0.1, []);
    }

    #[Test]
    public function test_throws_exception_for_numeric_rating(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid risk rating. Must be A-F');

        new RiskRating('1', 0.1, []);
    }

    #[Test]
    public function test_throws_exception_for_empty_rating(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid risk rating. Must be A-F');

        new RiskRating('', 0.1, []);
    }

    #[Test]
    public function test_throws_exception_for_negative_default_probability(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default probability must be between 0 and 1');

        new RiskRating('A', -0.1, []);
    }

    #[Test]
    public function test_throws_exception_for_default_probability_above_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default probability must be between 0 and 1');

        new RiskRating('A', 1.1, []);
    }

    #[Test]
    public function test_accepts_boundary_default_probability_values(): void
    {
        $minProbability = new RiskRating('A', 0, []);
        $maxProbability = new RiskRating('F', 1, []);

        $this->assertEquals(0, $minProbability->defaultProbability);
        $this->assertEquals(1, $maxProbability->defaultProbability);
    }

    #[Test]
    public function test_accepts_all_valid_ratings(): void
    {
        $validRatings = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ($validRatings as $rating) {
            $riskRating = new RiskRating($rating, 0.5, []);
            $this->assertEquals($rating, $riskRating->rating);
        }
    }

    #[Test]
    public function test_is_low_risk_returns_true_for_a_and_b_ratings(): void
    {
        $ratingA = new RiskRating('A', 0.01, []);
        $ratingB = new RiskRating('B', 0.02, []);

        $this->assertTrue($ratingA->isLowRisk());
        $this->assertTrue($ratingB->isLowRisk());
    }

    #[Test]
    public function test_is_low_risk_returns_false_for_other_ratings(): void
    {
        $ratings = ['C', 'D', 'E', 'F'];

        foreach ($ratings as $rating) {
            $riskRating = new RiskRating($rating, 0.5, []);
            $this->assertFalse($riskRating->isLowRisk(), "Rating {$rating} should not be low risk");
        }
    }

    #[Test]
    public function test_is_medium_risk_returns_true_for_c_and_d_ratings(): void
    {
        $ratingC = new RiskRating('C', 0.05, []);
        $ratingD = new RiskRating('D', 0.10, []);

        $this->assertTrue($ratingC->isMediumRisk());
        $this->assertTrue($ratingD->isMediumRisk());
    }

    #[Test]
    public function test_is_medium_risk_returns_false_for_other_ratings(): void
    {
        $ratings = ['A', 'B', 'E', 'F'];

        foreach ($ratings as $rating) {
            $riskRating = new RiskRating($rating, 0.5, []);
            $this->assertFalse($riskRating->isMediumRisk(), "Rating {$rating} should not be medium risk");
        }
    }

    #[Test]
    public function test_is_high_risk_returns_true_for_e_and_f_ratings(): void
    {
        $ratingE = new RiskRating('E', 0.20, []);
        $ratingF = new RiskRating('F', 0.35, []);

        $this->assertTrue($ratingE->isHighRisk());
        $this->assertTrue($ratingF->isHighRisk());
    }

    #[Test]
    public function test_is_high_risk_returns_false_for_other_ratings(): void
    {
        $ratings = ['A', 'B', 'C', 'D'];

        foreach ($ratings as $rating) {
            $riskRating = new RiskRating($rating, 0.5, []);
            $this->assertFalse($riskRating->isHighRisk(), "Rating {$rating} should not be high risk");
        }
    }

    #[Test]
    public function test_get_interest_rate_multiplier_returns_correct_values(): void
    {
        $expectedMultipliers = [
            'A' => 1.0,
            'B' => 1.2,
            'C' => 1.5,
            'D' => 2.0,
            'E' => 2.5,
            'F' => 3.0,
        ];

        foreach ($expectedMultipliers as $rating => $expectedMultiplier) {
            $riskRating = new RiskRating($rating, 0.5, []);
            $this->assertEquals(
                $expectedMultiplier,
                $riskRating->getInterestRateMultiplier(),
                "Rating {$rating} should have multiplier {$expectedMultiplier}"
            );
        }
    }

    #[Test]
    public function test_to_array_returns_correct_structure(): void
    {
        $rating = 'C';
        $defaultProbability = 0.08;
        $riskFactors = [
            'credit_history'    => 'Fair',
            'income_stability'  => 'Moderate',
            'collateral'        => 'Adequate',
            'market_conditions' => 'Stable',
        ];

        $riskRating = new RiskRating($rating, $defaultProbability, $riskFactors);
        $array = $riskRating->toArray();

        $this->assertEquals([
            'rating'              => 'C',
            'default_probability' => 0.08,
            'risk_factors'        => $riskFactors,
        ], $array);
    }

    #[Test]
    public function test_handles_empty_risk_factors(): void
    {
        $riskRating = new RiskRating('A', 0.01, []);

        $this->assertEmpty($riskRating->riskFactors);
        $this->assertEquals([], $riskRating->toArray()['risk_factors']);
    }

    #[Test]
    public function test_handles_complex_risk_factors(): void
    {
        $complexRiskFactors = [
            'financial_metrics' => [
                'debt_service_coverage_ratio' => 1.25,
                'loan_to_value'               => 0.80,
                'debt_to_equity'              => 0.65,
            ],
            'qualitative_factors' => [
                'management_quality'   => 'Good',
                'industry_outlook'     => 'Positive',
                'competitive_position' => 'Strong',
            ],
            'external_factors' => [
                'regulatory_risk'     => 'Low',
                'economic_conditions' => 'Favorable',
                'geopolitical_risk'   => 'Moderate',
            ],
            'historical_performance' => [
                'payment_history'      => 'Excellent',
                'default_events'       => 0,
                'restructuring_events' => 0,
            ],
        ];

        $riskRating = new RiskRating('B', 0.03, $complexRiskFactors);

        $this->assertEquals($complexRiskFactors, $riskRating->riskFactors);
        $this->assertIsArray($riskRating->riskFactors['financial_metrics']);
        $this->assertEquals(1.25, $riskRating->riskFactors['financial_metrics']['debt_service_coverage_ratio']);
    }

    #[Test]
    public function test_properties_are_readonly(): void
    {
        $riskRating = new RiskRating('C', 0.05, ['factor' => 'value']);

        // PHP 8.1+ readonly properties throw Error when attempting to modify
        $this->expectException(Error::class);
        $riskRating->rating = 'D';
    }

    #[Test]
    public function test_risk_rating_categorization_aligns_with_default_probability(): void
    {
        // Test that risk ratings align with typical default probability ranges
        $typicalRanges = [
            'A' => ['min' => 0.00, 'max' => 0.02, 'risk' => 'low'],
            'B' => ['min' => 0.02, 'max' => 0.05, 'risk' => 'low'],
            'C' => ['min' => 0.05, 'max' => 0.10, 'risk' => 'medium'],
            'D' => ['min' => 0.10, 'max' => 0.20, 'risk' => 'medium'],
            'E' => ['min' => 0.20, 'max' => 0.35, 'risk' => 'high'],
            'F' => ['min' => 0.35, 'max' => 1.00, 'risk' => 'high'],
        ];

        foreach ($typicalRanges as $rating => $range) {
            $midpoint = ($range['min'] + $range['max']) / 2;
            $riskRating = new RiskRating($rating, $midpoint, []);

            switch ($range['risk']) {
                case 'low':
                    $this->assertTrue($riskRating->isLowRisk(), "Rating {$rating} should be low risk");
                    break;
                case 'medium':
                    $this->assertTrue($riskRating->isMediumRisk(), "Rating {$rating} should be medium risk");
                    break;
                case 'high':
                    $this->assertTrue($riskRating->isHighRisk(), "Rating {$rating} should be high risk");
                    break;
            }
        }
    }

    #[Test]
    public function test_interest_rate_multiplier_increases_with_risk(): void
    {
        $ratings = ['A', 'B', 'C', 'D', 'E', 'F'];
        $previousMultiplier = 0;

        foreach ($ratings as $rating) {
            $riskRating = new RiskRating($rating, 0.5, []);
            $currentMultiplier = $riskRating->getInterestRateMultiplier();

            $this->assertGreaterThan(
                $previousMultiplier,
                $currentMultiplier,
                'Multiplier should increase with risk rating'
            );

            $previousMultiplier = $currentMultiplier;
        }
    }
}
