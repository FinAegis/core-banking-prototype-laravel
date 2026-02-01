<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RegTech\Enums;

use App\Domain\RegTech\Enums\Jurisdiction;
use Tests\TestCase;
use ValueError;

class JurisdictionTest extends TestCase
{
    public function test_enum_values(): void
    {
        $this->assertEquals('US', Jurisdiction::US->value);
        $this->assertEquals('EU', Jurisdiction::EU->value);
        $this->assertEquals('UK', Jurisdiction::UK->value);
        $this->assertEquals('SG', Jurisdiction::SG->value);
    }

    public function test_name_method(): void
    {
        $this->assertEquals('United States', Jurisdiction::US->name());
        $this->assertEquals('European Union', Jurisdiction::EU->name());
        $this->assertEquals('United Kingdom', Jurisdiction::UK->name());
        $this->assertEquals('Singapore', Jurisdiction::SG->name());
    }

    public function test_currency_method(): void
    {
        $this->assertEquals('USD', Jurisdiction::US->currency());
        $this->assertEquals('EUR', Jurisdiction::EU->currency());
        $this->assertEquals('GBP', Jurisdiction::UK->currency());
        $this->assertEquals('SGD', Jurisdiction::SG->currency());
    }

    public function test_timezone_method(): void
    {
        $this->assertEquals('America/New_York', Jurisdiction::US->timezone());
        $this->assertEquals('Europe/Paris', Jurisdiction::EU->timezone());
        $this->assertEquals('Europe/London', Jurisdiction::UK->timezone());
        $this->assertEquals('Asia/Singapore', Jurisdiction::SG->timezone());
    }

    public function test_try_from_valid(): void
    {
        $jurisdiction = Jurisdiction::tryFrom('US');

        $this->assertInstanceOf(Jurisdiction::class, $jurisdiction);
        $this->assertEquals(Jurisdiction::US, $jurisdiction);
    }

    public function test_try_from_invalid(): void
    {
        $jurisdiction = Jurisdiction::tryFrom('INVALID');

        $this->assertNull($jurisdiction);
    }

    public function test_from_valid(): void
    {
        $jurisdiction = Jurisdiction::from('EU');

        $this->assertEquals(Jurisdiction::EU, $jurisdiction);
    }

    public function test_from_invalid_throws_exception(): void
    {
        $this->expectException(ValueError::class);

        Jurisdiction::from('INVALID');
    }

    public function test_cases(): void
    {
        $cases = Jurisdiction::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(Jurisdiction::US, $cases);
        $this->assertContains(Jurisdiction::EU, $cases);
        $this->assertContains(Jurisdiction::UK, $cases);
        $this->assertContains(Jurisdiction::SG, $cases);
    }
}
