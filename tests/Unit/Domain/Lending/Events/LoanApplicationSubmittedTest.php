<?php

namespace Tests\Unit\Domain\Lending\Events;

use App\Domain\Lending\Events\LoanApplicationSubmitted;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class LoanApplicationSubmittedTest extends DomainTestCase
{
    #[Test]
    public function test_creates_event_with_all_properties(): void
    {
        $applicationId = 'app-123-uuid';
        $borrowerId = 'borrower-456-uuid';
        $requestedAmount = '50000.00';
        $termMonths = 36;
        $purpose = 'home_improvement';
        $borrowerInfo = [
            'annual_income'     => '75000',
            'employment_status' => 'employed',
            'credit_score'      => 720,
            'debt_to_income'    => 0.35,
        ];
        $submittedAt = new DateTimeImmutable('2024-01-15 10:30:00');

        $event = new LoanApplicationSubmitted(
            $applicationId,
            $borrowerId,
            $requestedAmount,
            $termMonths,
            $purpose,
            $borrowerInfo,
            $submittedAt
        );

        $this->assertEquals($applicationId, $event->applicationId);
        $this->assertEquals($borrowerId, $event->borrowerId);
        $this->assertEquals($requestedAmount, $event->requestedAmount);
        $this->assertEquals($termMonths, $event->termMonths);
        $this->assertEquals($purpose, $event->purpose);
        $this->assertEquals($borrowerInfo, $event->borrowerInfo);
        $this->assertSame($submittedAt, $event->submittedAt);
    }

    #[Test]
    public function test_event_extends_should_be_stored(): void
    {
        $event = new LoanApplicationSubmitted(
            'app-id',
            'borrower-id',
            '10000',
            12,
            'personal',
            [],
            new DateTimeImmutable()
        );

        $this->assertInstanceOf(\Spatie\EventSourcing\StoredEvents\ShouldBeStored::class, $event);
    }

    #[Test]
    public function test_handles_different_loan_purposes(): void
    {
        $purposes = [
            'home_improvement',
            'debt_consolidation',
            'business',
            'personal',
            'education',
            'medical',
            'auto',
        ];

        foreach ($purposes as $purpose) {
            $event = new LoanApplicationSubmitted(
                'app-' . $purpose,
                'borrower-id',
                '25000',
                24,
                $purpose,
                [],
                new DateTimeImmutable()
            );

            $this->assertEquals($purpose, $event->purpose);
        }
    }

    #[Test]
    public function test_handles_various_loan_amounts_and_terms(): void
    {
        $testCases = [
            ['amount' => '5000.00', 'term' => 12],
            ['amount' => '25000.50', 'term' => 36],
            ['amount' => '100000.00', 'term' => 60],
            ['amount' => '500000.00', 'term' => 120],
        ];

        foreach ($testCases as $case) {
            $event = new LoanApplicationSubmitted(
                'app-test',
                'borrower-test',
                $case['amount'],
                $case['term'],
                'test',
                [],
                new DateTimeImmutable()
            );

            $this->assertEquals($case['amount'], $event->requestedAmount);
            $this->assertEquals($case['term'], $event->termMonths);
        }
    }

    #[Test]
    public function test_borrower_info_can_contain_complex_data(): void
    {
        $complexBorrowerInfo = [
            'personal' => [
                'first_name'    => 'John',
                'last_name'     => 'Doe',
                'date_of_birth' => '1985-06-15',
                'ssn_last4'     => '1234',
            ],
            'employment' => [
                'status'         => 'employed',
                'employer'       => 'Tech Corp',
                'position'       => 'Senior Developer',
                'years_employed' => 5,
                'monthly_income' => 8333.33,
            ],
            'financial' => [
                'credit_score'          => 750,
                'debt_to_income'        => 0.28,
                'existing_loans'        => 2,
                'monthly_debt_payments' => 2500,
            ],
            'references' => [
                ['name' => 'Jane Smith', 'relationship' => 'colleague'],
                ['name' => 'Bob Johnson', 'relationship' => 'landlord'],
            ],
        ];

        $event = new LoanApplicationSubmitted(
            'complex-app',
            'complex-borrower',
            '75000',
            48,
            'business',
            $complexBorrowerInfo,
            new DateTimeImmutable()
        );

        $this->assertEquals($complexBorrowerInfo, $event->borrowerInfo);
        $this->assertEquals('Tech Corp', $event->borrowerInfo['employment']['employer']);
        $this->assertEquals(750, $event->borrowerInfo['financial']['credit_score']);
        $this->assertCount(2, $event->borrowerInfo['references']);
    }

    #[Test]
    public function test_submitted_at_preserves_timezone(): void
    {
        $timezone = new DateTimeZone('America/New_York');
        $submittedAt = new DateTimeImmutable('2024-01-15 15:30:00', $timezone);

        $event = new LoanApplicationSubmitted(
            'tz-app',
            'tz-borrower',
            '30000',
            24,
            'personal',
            [],
            $submittedAt
        );

        $this->assertEquals($timezone->getName(), $event->submittedAt->getTimezone()->getName());
        $this->assertEquals('2024-01-15 15:30:00', $event->submittedAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function test_event_properties_are_public(): void
    {
        $event = new LoanApplicationSubmitted(
            'public-app',
            'public-borrower',
            '20000',
            18,
            'auto',
            ['test' => 'data'],
            new DateTimeImmutable()
        );

        // Direct property access should work
        $this->assertEquals('public-app', $event->applicationId);
        $this->assertEquals('public-borrower', $event->borrowerId);
        $this->assertEquals('20000', $event->requestedAmount);
        $this->assertEquals(18, $event->termMonths);
        $this->assertEquals('auto', $event->purpose);
        $this->assertEquals(['test' => 'data'], $event->borrowerInfo);
    }
}
