<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Console\Commands\ImportTenantDataCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Tests for ImportTenantDataCommand.
 *
 * These are structural tests that verify the command methods exist
 * and have the correct signatures without requiring database access.
 */
class ImportTenantDataCommandTest extends TestCase
{
    #[Test]
    public function command_class_exists(): void
    {
        $this->assertTrue(class_exists(ImportTenantDataCommand::class));
    }

    #[Test]
    public function command_extends_illuminate_command(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
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
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $property = $reflection->getProperty('signature');

        $this->assertTrue($property->isProtected());

        // Get the signature value
        $command = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);
        $signature = $property->getValue($command);

        $this->assertStringContainsString('tenants:import-data', $signature);
        $this->assertStringContainsString('--tenant=', $signature);
        $this->assertStringContainsString('--file=', $signature);
        $this->assertStringContainsString('--format=', $signature);
        $this->assertStringContainsString('--truncate', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    #[Test]
    public function command_has_description_property(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $property = $reflection->getProperty('description');

        $this->assertTrue($property->isProtected());

        $command = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);
        $description = $property->getValue($command);

        $this->assertNotEmpty($description);
        $this->assertIsString($description);
    }

    #[Test]
    public function command_has_importable_tables_property(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $property = $reflection->getProperty('importableTables');

        $this->assertTrue($property->isProtected());

        $command = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);
        $tables = $property->getValue($command);

        $this->assertIsArray($tables);
        $this->assertContains('accounts', $tables);
        $this->assertContains('transactions', $tables);
        $this->assertContains('transfers', $tables);
    }

    #[Test]
    public function command_has_handle_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
    }

    #[Test]
    public function command_has_detect_format_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('detectFormat');

        $this->assertTrue($method->isProtected());
        $this->assertCount(1, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('filePath', $params[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('string', $returnType->getName());
    }

    #[Test]
    public function command_has_extract_zip_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('extractZip');

        $this->assertTrue($method->isProtected());
        $this->assertCount(1, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('zipPath', $params[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('string', $returnType->getName());
    }

    #[Test]
    public function command_has_import_data_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('importData');

        $this->assertTrue($method->isProtected());
        $this->assertCount(5, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenant', $params[0]->getName());
        $this->assertEquals('filePath', $params[1]->getName());
        $this->assertEquals('format', $params[2]->getName());
        $this->assertEquals('truncate', $params[3]->getName());
        $this->assertEquals('dryRun', $params[4]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function command_has_import_from_json_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('importFromJson');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function command_has_import_from_csv_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('importFromCsv');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function command_has_import_from_sql_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('importFromSql');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function command_has_import_record_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('importRecord');

        $this->assertTrue($method->isProtected());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('table', $params[0]->getName());
        $this->assertEquals('record', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('void', $returnType->getName());
    }

    #[Test]
    public function command_has_log_import_method(): void
    {
        $reflection = new ReflectionClass(ImportTenantDataCommand::class);
        $method = $reflection->getMethod('logImport');

        $this->assertTrue($method->isProtected());
        $this->assertCount(3, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenant', $params[0]->getName());
        $this->assertEquals('filePath', $params[1]->getName());
        $this->assertEquals('result', $params[2]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('void', $returnType->getName());
    }
}
