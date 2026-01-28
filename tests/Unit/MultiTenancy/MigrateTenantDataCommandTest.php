<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Console\Commands\MigrateTenantDataCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Tests for MigrateTenantDataCommand.
 *
 * These are structural tests that verify the command methods exist
 * and have the correct signatures without requiring database access.
 */
class MigrateTenantDataCommandTest extends TestCase
{
    #[Test]
    public function command_class_exists(): void
    {
        $this->assertTrue(class_exists(MigrateTenantDataCommand::class));
    }

    #[Test]
    public function command_extends_illuminate_command(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $parentClass = $reflection->getParentClass();

        $this->assertNotFalse($parentClass);
        $this->assertEquals(
            'Illuminate\Console\Command',
            $parentClass->getName()
        );
    }

    #[Test]
    public function command_has_signature_property(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $property = $reflection->getProperty('signature');

        $this->assertTrue($property->isProtected());

        // Get the signature value
        $command = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);
        $signature = $property->getValue($command);

        $this->assertStringContainsString('tenants:migrate-data', $signature);
        $this->assertStringContainsString('--tenant=', $signature);
        $this->assertStringContainsString('--tables=', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--batch-size=', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    #[Test]
    public function command_has_description_property(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $property = $reflection->getProperty('description');

        $this->assertTrue($property->isProtected());

        $command = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);
        $description = $property->getValue($command);

        $this->assertNotEmpty($description);
        $this->assertIsString($description);
    }

    #[Test]
    public function command_has_handle_method(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
    }

    #[Test]
    public function command_has_get_tenants_to_migrate_method(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $method = $reflection->getMethod('getTenantsToMigrate');

        $this->assertTrue($method->isProtected());
        $this->assertCount(1, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertTrue($params[0]->allowsNull());
    }

    #[Test]
    public function command_has_display_available_tables_method(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $method = $reflection->getMethod('displayAvailableTables');

        $this->assertTrue($method->isProtected());
        $this->assertCount(0, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('void', $returnType->getName());
    }

    #[Test]
    public function command_has_perform_dry_run_method(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $method = $reflection->getMethod('performDryRun');

        $this->assertTrue($method->isProtected());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenants', $params[0]->getName());
        $this->assertEquals('tables', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
    }

    #[Test]
    public function command_has_perform_migration_method(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $method = $reflection->getMethod('performMigration');

        $this->assertTrue($method->isProtected());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenants', $params[0]->getName());
        $this->assertEquals('tables', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
    }

    #[Test]
    public function command_uses_constructor_injection(): void
    {
        $reflection = new ReflectionClass(MigrateTenantDataCommand::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('migrationService', $params[0]->getName());
    }
}
