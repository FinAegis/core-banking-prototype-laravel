<?php

declare(strict_types=1);

namespace Tests\Unit\Values;

use App\Values\EventQueues;
use Tests\UnitTestCase;

class EventQueuesTest extends UnitTestCase
{
    public function test_enum_has_correct_values()
    {
        $this->assertEquals('events', EventQueues::EVENTS->value);
        $this->assertEquals('ledger', EventQueues::LEDGER->value);
        $this->assertEquals('transactions', EventQueues::TRANSACTIONS->value);
        $this->assertEquals('transfers', EventQueues::TRANSFERS->value);
    }

    public function test_default_returns_events()
    {
        $default = EventQueues::default();
        
        $this->assertEquals(EventQueues::EVENTS, $default);
        $this->assertEquals('events', $default->value);
    }

    public function test_all_enum_cases_exist()
    {
        $cases = EventQueues::cases();
        
        $this->assertCount(4, $cases);
        $this->assertContains(EventQueues::EVENTS, $cases);
        $this->assertContains(EventQueues::LEDGER, $cases);
        $this->assertContains(EventQueues::TRANSACTIONS, $cases);
        $this->assertContains(EventQueues::TRANSFERS, $cases);
    }

    public function test_enum_from_value()
    {
        $events = EventQueues::from('events');
        $ledger = EventQueues::from('ledger');
        $transactions = EventQueues::from('transactions');
        $transfers = EventQueues::from('transfers');
        
        $this->assertEquals(EventQueues::EVENTS, $events);
        $this->assertEquals(EventQueues::LEDGER, $ledger);
        $this->assertEquals(EventQueues::TRANSACTIONS, $transactions);
        $this->assertEquals(EventQueues::TRANSFERS, $transfers);
    }

    public function test_enum_try_from_valid()
    {
        $events = EventQueues::tryFrom('events');
        $ledger = EventQueues::tryFrom('ledger');
        $transactions = EventQueues::tryFrom('transactions');
        $transfers = EventQueues::tryFrom('transfers');
        
        $this->assertEquals(EventQueues::EVENTS, $events);
        $this->assertEquals(EventQueues::LEDGER, $ledger);
        $this->assertEquals(EventQueues::TRANSACTIONS, $transactions);
        $this->assertEquals(EventQueues::TRANSFERS, $transfers);
    }

    public function test_enum_try_from_invalid()
    {
        $invalid = EventQueues::tryFrom('invalid');
        
        $this->assertNull($invalid);
    }

    public function test_queue_values_are_lowercase()
    {
        $cases = EventQueues::cases();
        
        foreach ($cases as $case) {
            $this->assertEquals(strtolower($case->value), $case->value);
        }
    }
}