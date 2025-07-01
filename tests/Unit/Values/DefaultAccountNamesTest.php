<?php

declare(strict_types=1);

namespace Tests\Unit\Values;

use App\Values\DefaultAccountNames;
use Tests\UnitTestCase;

class DefaultAccountNamesTest extends UnitTestCase
{
    public function test_enum_has_correct_values()
    {
        $this->assertEquals('Main', DefaultAccountNames::MAIN->value);
        $this->assertEquals('Savings', DefaultAccountNames::SAVINGS->value);
        $this->assertEquals('Loan', DefaultAccountNames::LOAN->value);
    }

    public function test_default_returns_main()
    {
        $default = DefaultAccountNames::default();
        
        $this->assertEquals(DefaultAccountNames::MAIN, $default);
        $this->assertEquals('Main', $default->value);
    }

    public function test_label_returns_translation()
    {
        $mainLabel = DefaultAccountNames::MAIN->label();
        $savingsLabel = DefaultAccountNames::SAVINGS->label();
        $loanLabel = DefaultAccountNames::LOAN->label();
        
        // These should return translation strings
        $this->assertIsString($mainLabel);
        $this->assertIsString($savingsLabel);
        $this->assertIsString($loanLabel);
        
        // Test that labels are not empty
        $this->assertNotEmpty($mainLabel);
        $this->assertNotEmpty($savingsLabel);
        $this->assertNotEmpty($loanLabel);
    }

    public function test_all_enum_cases_exist()
    {
        $cases = DefaultAccountNames::cases();
        
        $this->assertCount(3, $cases);
        $this->assertContains(DefaultAccountNames::MAIN, $cases);
        $this->assertContains(DefaultAccountNames::SAVINGS, $cases);
        $this->assertContains(DefaultAccountNames::LOAN, $cases);
    }

    public function test_enum_from_value()
    {
        $main = DefaultAccountNames::from('Main');
        $savings = DefaultAccountNames::from('Savings');
        $loan = DefaultAccountNames::from('Loan');
        
        $this->assertEquals(DefaultAccountNames::MAIN, $main);
        $this->assertEquals(DefaultAccountNames::SAVINGS, $savings);
        $this->assertEquals(DefaultAccountNames::LOAN, $loan);
    }

    public function test_enum_try_from_valid()
    {
        $main = DefaultAccountNames::tryFrom('Main');
        $savings = DefaultAccountNames::tryFrom('Savings');
        $loan = DefaultAccountNames::tryFrom('Loan');
        
        $this->assertEquals(DefaultAccountNames::MAIN, $main);
        $this->assertEquals(DefaultAccountNames::SAVINGS, $savings);
        $this->assertEquals(DefaultAccountNames::LOAN, $loan);
    }

    public function test_enum_try_from_invalid()
    {
        $invalid = DefaultAccountNames::tryFrom('Invalid');
        
        $this->assertNull($invalid);
    }
}