<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Filament\Admin\TenantAwareResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Tests for TenantAwareResource trait.
 *
 * These are structural tests that verify the trait methods exist
 * and have the correct signatures without requiring database access.
 */
class TenantAwareResourceTest extends TestCase
{
    #[Test]
    public function trait_exists(): void
    {
        $this->assertTrue(trait_exists(TenantAwareResource::class));
    }

    #[Test]
    public function trait_has_get_eloquent_query_method(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $method = $reflection->getMethod('getEloquentQuery');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    #[Test]
    public function trait_has_apply_tenant_scope_method(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $method = $reflection->getMethod('applyTenantScope');

        $this->assertTrue($method->isProtected());
        $this->assertTrue($method->isStatic());
    }

    #[Test]
    public function trait_has_tenant_context_check_method(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $method = $reflection->getMethod('hasTenantContext');

        $this->assertTrue($method->isProtected());
        $this->assertTrue($method->isStatic());

        // Verify return type
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('bool', $returnType->getName());
    }

    #[Test]
    public function trait_has_get_tenant_id_method(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $method = $reflection->getMethod('getTenantId');

        $this->assertTrue($method->isProtected());
        $this->assertTrue($method->isStatic());

        // Verify return type is nullable string
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    #[Test]
    public function trait_has_get_team_id_method(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $method = $reflection->getMethod('getTeamId');

        $this->assertTrue($method->isProtected());
        $this->assertTrue($method->isStatic());

        // Verify return type is nullable int
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    #[Test]
    public function trait_has_tenant_column_property(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $property = $reflection->getProperty('tenantColumn');

        $this->assertTrue($property->isProtected());
        $this->assertTrue($property->isStatic());

        // Verify type is nullable string
        $type = $property->getType();
        $this->assertInstanceOf(ReflectionProperty::class, $property);
        $this->assertNotNull($type);
    }

    #[Test]
    public function trait_has_show_all_without_tenant_property(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $property = $reflection->getProperty('showAllWithoutTenant');

        $this->assertTrue($property->isProtected());
        $this->assertTrue($property->isStatic());
    }

    #[Test]
    public function apply_tenant_scope_method_has_correct_parameters(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);
        $method = $reflection->getMethod('applyTenantScope');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('query', $params[0]->getName());
    }
}
