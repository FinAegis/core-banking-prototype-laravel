<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Services\MultiTenancy\TenantDataMigrationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Tests for TenantDataMigrationService.
 *
 * These are structural tests that verify the service methods exist
 * and have the correct signatures without requiring database access.
 */
class TenantDataMigrationServiceTest extends TestCase
{
    #[Test]
    public function service_class_exists(): void
    {
        $this->assertTrue(class_exists(TenantDataMigrationService::class));
    }

    #[Test]
    public function service_has_migratable_tables_property(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $property = $reflection->getProperty('migratableTables');

        $this->assertTrue($property->isProtected());
    }

    #[Test]
    public function service_has_batch_size_property(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $property = $reflection->getProperty('batchSize');

        $this->assertTrue($property->isProtected());

        $type = $property->getType();
        $this->assertNotNull($type);
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        /** @var ReflectionNamedType $type */
        $this->assertEquals('int', $type->getName());
    }

    #[Test]
    public function service_has_get_migratable_tables_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('getMigratableTables');

        $this->assertTrue($method->isPublic());
        $this->assertCount(0, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function service_has_register_table_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('registerTable');

        $this->assertTrue($method->isPublic());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('name', $params[0]->getName());
        $this->assertEquals('config', $params[1]->getName());
    }

    #[Test]
    public function service_has_set_batch_size_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('setBatchSize');

        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('size', $params[0]->getName());

        $paramType = $params[0]->getType();
        $this->assertNotNull($paramType);
        $this->assertInstanceOf(ReflectionNamedType::class, $paramType);
        /** @var ReflectionNamedType $paramType */
        $this->assertEquals('int', $paramType->getName());
    }

    #[Test]
    public function service_has_migrate_data_for_tenant_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('migrateDataForTenant');

        $this->assertTrue($method->isPublic());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenant', $params[0]->getName());
        $this->assertEquals('tables', $params[1]->getName());

        // Second parameter should have a default value of null
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertNull($params[1]->getDefaultValue());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function service_has_migrate_table_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('migrateTable');

        $this->assertTrue($method->isProtected());
        $this->assertCount(2, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
    }

    #[Test]
    public function service_has_build_indirect_query_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('buildIndirectQuery');

        $this->assertTrue($method->isProtected());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tableName', $params[0]->getName());
        $this->assertEquals('teamId', $params[1]->getName());
    }

    #[Test]
    public function service_has_insert_batch_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('insertBatch');

        $this->assertTrue($method->isProtected());
        $this->assertCount(4, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tenant', $params[0]->getName());
        $this->assertEquals('targetTable', $params[1]->getName());
        $this->assertEquals('keyColumn', $params[2]->getName());
        $this->assertEquals('records', $params[3]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('void', $returnType->getName());
    }

    #[Test]
    public function service_has_get_tenant_connection_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('getTenantConnection');

        $this->assertTrue($method->isProtected());
        $this->assertCount(1, $method->getParameters());
    }

    #[Test]
    public function service_has_log_migration_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('logMigration');

        $this->assertTrue($method->isProtected());
        $this->assertCount(2, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('void', $returnType->getName());
    }

    #[Test]
    public function service_has_get_migration_history_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('getMigrationHistory');

        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());
    }

    #[Test]
    public function service_has_is_migrated_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('isMigrated');

        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('bool', $returnType->getName());
    }

    #[Test]
    public function service_has_get_record_counts_method(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);
        $method = $reflection->getMethod('getRecordCounts');

        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function get_migratable_tables_returns_expected_structure(): void
    {
        $service = new TenantDataMigrationService();
        $tables = $service->getMigratableTables();

        $this->assertIsArray($tables);
        $this->assertArrayHasKey('accounts', $tables);
        $this->assertArrayHasKey('transactions', $tables);
        $this->assertArrayHasKey('transfers', $tables);

        // Check structure of a table config
        $accountConfig = $tables['accounts'];
        $this->assertArrayHasKey('source', $accountConfig);
        $this->assertArrayHasKey('target', $accountConfig);
        $this->assertArrayHasKey('key', $accountConfig);
        $this->assertArrayHasKey('team_column', $accountConfig);
    }

    #[Test]
    public function register_table_returns_self_for_chaining(): void
    {
        $service = new TenantDataMigrationService();
        $result = $service->registerTable('test_table', [
            'source'      => 'test_source',
            'target'      => 'test_target',
            'key'         => 'id',
            'team_column' => 'team_id',
        ]);

        $this->assertSame($service, $result);
    }

    #[Test]
    public function set_batch_size_returns_self_for_chaining(): void
    {
        $service = new TenantDataMigrationService();
        $result = $service->setBatchSize(500);

        $this->assertSame($service, $result);
    }

    #[Test]
    public function registered_table_appears_in_migratable_tables(): void
    {
        $service = new TenantDataMigrationService();
        $service->registerTable('custom_table', [
            'source'      => 'custom_source',
            'target'      => 'custom_target',
            'key'         => 'uuid',
            'team_column' => null,
        ]);

        $tables = $service->getMigratableTables();
        $this->assertArrayHasKey('custom_table', $tables);
        $this->assertEquals('custom_source', $tables['custom_table']['source']);
    }
}
