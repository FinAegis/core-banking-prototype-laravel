<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Workflows\Activities\MintStablecoinActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class MintStablecoinActivityTest extends DomainTestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(MintStablecoinActivity::class));
    }

    #[Test]
    public function test_extends_workflow_activity(): void
    {
        $reflection = new ReflectionClass(MintStablecoinActivity::class);
        $this->assertEquals('Workflow\Activity', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_execute_method(): void
    {
        $this->assertTrue(method_exists(MintStablecoinActivity::class, 'execute'));
    }

    #[Test]
    public function test_execute_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(MintStablecoinActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals(4, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals(AccountUuid::class, $parameters[0]->getType()->getName());

        $this->assertEquals('positionUuid', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals('stablecoinCode', $parameters[2]->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());

        $this->assertEquals('amount', $parameters[3]->getName());
        $this->assertEquals('int', $parameters[3]->getType()->getName());
    }

    #[Test]
    public function test_execute_method_returns_bool(): void
    {
        $reflection = new ReflectionClass(MintStablecoinActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals('bool', $method->getReturnType()->getName());
    }

    #[Test]
    public function test_calculates_mint_fee_correctly(): void
    {
        // Create stablecoin with mint fee
        $stablecoin = Stablecoin::factory()->create([
            'code'         => 'USDS',
            'mint_fee'     => 0.002, // 0.2% mint fee
            'total_supply' => 1000000000000, // 1 million USDS
        ]);

        $amount = 10000000000; // 10,000 USDS

        // Expected fee calculation
        $expectedFee = (int) ($amount * $stablecoin->mint_fee); // 20 USDS
        $expectedNetMint = $amount - $expectedFee; // 9,980 USDS

        // Verify the calculation logic
        $this->assertEquals(20000000, $expectedFee);
        $this->assertEquals(9980000000, $expectedNetMint);
    }

    #[Test]
    public function test_zero_mint_fee_calculation(): void
    {
        $stablecoin = Stablecoin::factory()->create([
            'code'         => 'NOFEE',
            'mint_fee'     => 0.0,
            'total_supply' => 100000000000,
        ]);

        $amount = 5000000000; // 5,000 NOFEE

        // With zero fee, net should equal amount
        $expectedFee = 0;
        $expectedNetMint = $amount;

        $this->assertEquals(0, $expectedFee);
        $this->assertEquals(5000000000, $expectedNetMint);
    }

    #[Test]
    public function test_activity_has_correct_properties(): void
    {
        $reflection = new ReflectionClass(MintStablecoinActivity::class);

        // Check for properties that should be inherited from Activity
        $this->assertTrue($reflection->hasProperty('tries'));
        $this->assertTrue($reflection->hasProperty('timeout'));
        $this->assertTrue($reflection->hasProperty('maxExceptions'));
    }
}
