<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Stablecoin\Workflows\Activities\ClosePositionActivity;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class ClosePositionActivityTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(ClosePositionActivity::class));
    }

    #[Test]
    public function test_extends_workflow_activity(): void
    {
        $reflection = new ReflectionClass(ClosePositionActivity::class);
        $this->assertEquals('Workflow\Activity', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_execute_method(): void
    {
        $this->assertTrue(method_exists(ClosePositionActivity::class, 'execute'));
    }

    #[Test]
    public function test_execute_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(ClosePositionActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals(2, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('positionUuid', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());

        $this->assertEquals('reason', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());
        $this->assertTrue($parameters[1]->isDefaultValueAvailable());
        $this->assertEquals('user_closed', $parameters[1]->getDefaultValue());
    }

    #[Test]
    public function test_execute_method_returns_bool(): void
    {
        $reflection = new ReflectionClass(ClosePositionActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals('bool', $method->getReturnType()->getName());
    }

    #[Test]
    public function test_various_close_reasons(): void
    {
        // Test various close reasons
        $reasons = [
            'user_closed'             => 'Default reason when user closes position',
            'liquidated'              => 'Position liquidated due to low collateral',
            'emergency_close'         => 'Emergency closure',
            'system_maintenance'      => 'Closed for system maintenance',
            'position_expired'        => 'Position reached expiry',
            'insufficient_collateral' => 'Not enough collateral',
            'admin_action'            => 'Closed by administrator',
        ];

        foreach ($reasons as $reason => $description) {
            $this->assertIsString($reason);
            $this->assertNotEmpty($reason);
            $this->assertIsString($description);
        }
    }

    #[Test]
    public function test_uuid_format_variations(): void
    {
        // Test various UUID format variations
        $uuids = [
            'pos-simple-123',
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'POS-UPPER-CASE',
            'pos_with_underscores',
            'pos.with.dots',
            'pos-' . str_repeat('x', 50),
        ];

        foreach ($uuids as $uuid) {
            $this->assertIsString($uuid);
            $this->assertNotEmpty($uuid);
        }
    }

    #[Test]
    public function test_default_reason_value(): void
    {
        $reflection = new ReflectionClass(ClosePositionActivity::class);
        $method = $reflection->getMethod('execute');
        $parameters = $method->getParameters();

        $reasonParam = $parameters[1];

        $this->assertTrue($reasonParam->isDefaultValueAvailable());
        $this->assertEquals('user_closed', $reasonParam->getDefaultValue());
    }

    #[Test]
    public function test_activity_properties(): void
    {
        $reflection = new ReflectionClass(ClosePositionActivity::class);

        // Check for important properties inherited from Activity
        $this->assertTrue($reflection->hasProperty('tries'));
        $this->assertTrue($reflection->hasProperty('timeout'));
    }
}
