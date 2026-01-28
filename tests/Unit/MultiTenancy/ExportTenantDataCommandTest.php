<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Console\Commands\ExportTenantDataCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Tests for ExportTenantDataCommand.
 *
 * These are structural tests that verify the command methods exist
 * and have the correct signatures without requiring database access.
 */
class ExportTenantDataCommandTest extends TestCase
{
    #[Test]
    public function command_class_exists(): void
    {
        $this->assertTrue(class_exists(ExportTenantDataCommand::class));
    }

    #[Test]
    public function command_extends_illuminate_command(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
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
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $property = $reflection->getProperty('signature');

        $this->assertTrue($property->isProtected());

        // Get the signature value
        $command = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);
        $signature = $property->getValue($command);

        $this->assertStringContainsString('tenants:export-data', $signature);
        $this->assertStringContainsString('--tenant=', $signature);
        $this->assertStringContainsString('--tables=', $signature);
        $this->assertStringContainsString('--format=', $signature);
        $this->assertStringContainsString('--output=', $signature);
        $this->assertStringContainsString('--compress', $signature);
    }

    #[Test]
    public function command_has_description_property(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $property = $reflection->getProperty('description');

        $this->assertTrue($property->isProtected());

        $command = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);
        $description = $property->getValue($command);

        $this->assertNotEmpty($description);
        $this->assertIsString($description);
    }

    #[Test]
    public function command_has_exportable_tables_property(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $property = $reflection->getProperty('exportableTables');

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
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
    }

    #[Test]
    public function command_has_export_data_method(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('exportData');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenant', $params[0]->getName());
        $this->assertEquals('tables', $params[1]->getName());
        $this->assertEquals('format', $params[2]->getName());
        $this->assertEquals('outputDir', $params[3]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('string', $returnType->getName());
    }

    #[Test]
    public function command_has_export_as_json_method(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('exportAsJson');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('string', $returnType->getName());
    }

    #[Test]
    public function command_has_export_as_csv_method(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('exportAsCsv');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('string', $returnType->getName());
    }

    #[Test]
    public function command_has_export_as_sql_method(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('exportAsSql');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('string', $returnType->getName());
    }

    #[Test]
    public function command_has_table_exists_method(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('tableExists');

        $this->assertTrue($method->isProtected());
        $this->assertCount(1, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('table', $params[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('bool', $returnType->getName());
    }

    #[Test]
    public function command_has_compress_file_method(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('compressFile');

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
    public function command_has_add_directory_to_zip_method(): void
    {
        $reflection = new ReflectionClass(ExportTenantDataCommand::class);
        $method = $reflection->getMethod('addDirectoryToZip');

        $this->assertTrue($method->isProtected());
        $this->assertCount(3, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('zip', $params[0]->getName());
        $this->assertEquals('dir', $params[1]->getName());
        $this->assertEquals('basePath', $params[2]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('void', $returnType->getName());
    }
}
