<?php

namespace Tests\Unit\Domain\Lending\ValueObjects;

use App\Domain\Lending\ValueObjects\RepaymentSchedule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RepaymentScheduleTest extends TestCase
{
    #[Test]
    public function test_creates_valid_repayment_schedule(): void
    {
        $payments = [
            [
                'payment_number' => 1,
                'due_date'       => '2024-02-01',
                'principal'      => '833.33',
                'interest'       => '100.00',
                'total'          => '933.33',
            ],
            [
                'payment_number' => 2,
                'due_date'       => '2024-03-01',
                'principal'      => '833.33',
                'interest'       => '91.67',
                'total'          => '925.00',
            ],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals($payments, $schedule->getPayments());
    }

    #[Test]
    public function test_throws_exception_for_empty_payments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Repayment schedule cannot be empty');

        new RepaymentSchedule([]);
    }

    #[Test]
    public function test_get_payment_returns_correct_payment(): void
    {
        $payments = [
            [
                'payment_number' => 1,
                'due_date'       => '2024-02-01',
                'principal'      => '833.33',
                'interest'       => '100.00',
                'total'          => '933.33',
            ],
            [
                'payment_number' => 2,
                'due_date'       => '2024-03-01',
                'principal'      => '833.33',
                'interest'       => '91.67',
                'total'          => '925.00',
            ],
            [
                'payment_number' => 3,
                'due_date'       => '2024-04-01',
                'principal'      => '833.34',
                'interest'       => '83.33',
                'total'          => '916.67',
            ],
        ];

        $schedule = new RepaymentSchedule($payments);

        $payment2 = $schedule->getPayment(2);
        $this->assertEquals($payments[1], $payment2);
        $this->assertEquals('2024-03-01', $payment2['due_date']);
        $this->assertEquals('833.33', $payment2['principal']);
    }

    #[Test]
    public function test_get_payment_throws_exception_for_invalid_payment_number(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '100.00', 'interest' => '10.00'],
            ['payment_number' => 2, 'principal' => '100.00', 'interest' => '9.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment number 3 not found in schedule');

        $schedule->getPayment(3);
    }

    #[Test]
    public function test_get_payment_throws_exception_for_zero_payment_number(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '100.00', 'interest' => '10.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment number 0 not found in schedule');

        $schedule->getPayment(0);
    }

    #[Test]
    public function test_get_total_payments_returns_correct_count(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '100.00', 'interest' => '10.00'],
            ['payment_number' => 2, 'principal' => '100.00', 'interest' => '9.00'],
            ['payment_number' => 3, 'principal' => '100.00', 'interest' => '8.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals(3, $schedule->getTotalPayments());
    }

    #[Test]
    public function test_get_total_payments_with_single_payment(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '1000.00', 'interest' => '50.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals(1, $schedule->getTotalPayments());
    }

    #[Test]
    public function test_get_total_principal_calculates_correctly(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '333.33', 'interest' => '100.00'],
            ['payment_number' => 2, 'principal' => '333.33', 'interest' => '91.67'],
            ['payment_number' => 3, 'principal' => '333.34', 'interest' => '83.33'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals('1000.00', $schedule->getTotalPrincipal());
    }

    #[Test]
    public function test_get_total_principal_with_decimal_precision(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '123.45', 'interest' => '10.00'],
            ['payment_number' => 2, 'principal' => '234.56', 'interest' => '9.00'],
            ['payment_number' => 3, 'principal' => '345.67', 'interest' => '8.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals('703.68', $schedule->getTotalPrincipal());
    }

    #[Test]
    public function test_get_total_interest_calculates_correctly(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '333.33', 'interest' => '100.00'],
            ['payment_number' => 2, 'principal' => '333.33', 'interest' => '91.67'],
            ['payment_number' => 3, 'principal' => '333.34', 'interest' => '83.33'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals('275.00', $schedule->getTotalInterest());
    }

    #[Test]
    public function test_get_total_interest_with_zero_interest(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '500.00', 'interest' => '0.00'],
            ['payment_number' => 2, 'principal' => '500.00', 'interest' => '0.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals('0.00', $schedule->getTotalInterest());
    }

    #[Test]
    public function test_get_next_due_payment_returns_first_payment_when_no_after_specified(): void
    {
        $payments = [
            ['payment_number' => 1, 'due_date' => '2024-02-01', 'principal' => '100.00'],
            ['payment_number' => 2, 'due_date' => '2024-03-01', 'principal' => '100.00'],
            ['payment_number' => 3, 'due_date' => '2024-04-01', 'principal' => '100.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $nextPayment = $schedule->getNextDuePayment();
        $this->assertEquals($payments[0], $nextPayment);
        $this->assertEquals(1, $nextPayment['payment_number']);
    }

    #[Test]
    public function test_get_next_due_payment_returns_payment_after_specified_number(): void
    {
        $payments = [
            ['payment_number' => 1, 'due_date' => '2024-02-01', 'principal' => '100.00'],
            ['payment_number' => 2, 'due_date' => '2024-03-01', 'principal' => '100.00'],
            ['payment_number' => 3, 'due_date' => '2024-04-01', 'principal' => '100.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $nextPayment = $schedule->getNextDuePayment(1);
        $this->assertEquals($payments[1], $nextPayment);
        $this->assertEquals(2, $nextPayment['payment_number']);
    }

    #[Test]
    public function test_get_next_due_payment_returns_null_when_no_more_payments(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '100.00'],
            ['payment_number' => 2, 'principal' => '100.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $nextPayment = $schedule->getNextDuePayment(2);
        $this->assertNull($nextPayment);
    }

    #[Test]
    public function test_get_next_due_payment_with_non_sequential_payment_numbers(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '100.00'],
            ['payment_number' => 3, 'principal' => '100.00'],
            ['payment_number' => 7, 'principal' => '100.00'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $nextPayment = $schedule->getNextDuePayment(1);
        $this->assertEquals(3, $nextPayment['payment_number']);

        $nextPayment = $schedule->getNextDuePayment(3);
        $this->assertEquals(7, $nextPayment['payment_number']);
    }

    #[Test]
    public function test_to_array_returns_payments(): void
    {
        $payments = [
            [
                'payment_number'    => 1,
                'due_date'          => '2024-02-01',
                'principal'         => '833.33',
                'interest'          => '100.00',
                'total'             => '933.33',
                'balance_remaining' => '9166.67',
            ],
            [
                'payment_number'    => 2,
                'due_date'          => '2024-03-01',
                'principal'         => '833.33',
                'interest'          => '91.67',
                'total'             => '925.00',
                'balance_remaining' => '8333.34',
            ],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals($payments, $schedule->toArray());
    }

    #[Test]
    public function test_handles_complex_payment_structure(): void
    {
        $payments = [
            [
                'payment_number'    => 1,
                'due_date'          => '2024-02-01',
                'principal'         => '833.33',
                'interest'          => '100.00',
                'fees'              => '25.00',
                'insurance'         => '15.00',
                'total'             => '973.33',
                'balance_remaining' => '9166.67',
                'status'            => 'pending',
                'metadata'          => [
                    'interest_rate'  => 0.12,
                    'days_in_period' => 30,
                ],
            ],
        ];

        $schedule = new RepaymentSchedule($payments);

        $payment = $schedule->getPayment(1);
        $this->assertEquals('25.00', $payment['fees']);
        $this->assertEquals('15.00', $payment['insurance']);
        $this->assertIsArray($payment['metadata']);
        $this->assertEquals(0.12, $payment['metadata']['interest_rate']);
    }

    #[Test]
    public function test_handles_large_payment_schedules(): void
    {
        $payments = [];
        for ($i = 1; $i <= 360; $i++) { // 30-year mortgage
            $payments[] = [
                'payment_number' => $i,
                'principal'      => '500.00',
                'interest'       => '1000.00',
            ];
        }

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals(360, $schedule->getTotalPayments());
        $this->assertEquals('180000.00', $schedule->getTotalPrincipal());
        $this->assertEquals('360000.00', $schedule->getTotalInterest());
    }

    #[Test]
    public function test_total_calculations_handle_string_numeric_values(): void
    {
        $payments = [
            ['payment_number' => 1, 'principal' => '100.50', 'interest' => '10.25'],
            ['payment_number' => 2, 'principal' => '200.75', 'interest' => '20.50'],
        ];

        $schedule = new RepaymentSchedule($payments);

        $this->assertEquals('301.25', $schedule->getTotalPrincipal());
        $this->assertEquals('30.75', $schedule->getTotalInterest());
    }
}
