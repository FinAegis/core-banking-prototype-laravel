<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Http\Middleware\FilamentTenantMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for FilamentTenantMiddleware.
 *
 * These are structural tests that verify the middleware class
 * and its methods exist with correct signatures.
 */
class FilamentTenantMiddlewareTest extends TestCase
{
    #[Test]
    public function middleware_class_exists(): void
    {
        $this->assertTrue(class_exists(FilamentTenantMiddleware::class));
    }

    #[Test]
    public function middleware_has_handle_method(): void
    {
        $reflection = new ReflectionClass(FilamentTenantMiddleware::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->isPublic());
    }

    #[Test]
    public function middleware_has_switch_tenant_method(): void
    {
        $reflection = new ReflectionClass(FilamentTenantMiddleware::class);
        $method = $reflection->getMethod('switchTenant');

        $this->assertTrue($method->isProtected());
    }

    #[Test]
    public function middleware_has_resolve_tenant_id_method(): void
    {
        $reflection = new ReflectionClass(FilamentTenantMiddleware::class);
        $method = $reflection->getMethod('resolveTenantId');

        $this->assertTrue($method->isProtected());
    }

    #[Test]
    public function middleware_has_initialize_tenant_method(): void
    {
        $reflection = new ReflectionClass(FilamentTenantMiddleware::class);
        $method = $reflection->getMethod('initializeTenant');

        $this->assertTrue($method->isProtected());
    }

    #[Test]
    public function middleware_has_user_can_access_tenant_method(): void
    {
        $reflection = new ReflectionClass(FilamentTenantMiddleware::class);
        $method = $reflection->getMethod('userCanAccessTenant');

        $this->assertTrue($method->isProtected());
    }

    #[Test]
    public function middleware_has_is_platform_admin_method(): void
    {
        $reflection = new ReflectionClass(FilamentTenantMiddleware::class);
        $method = $reflection->getMethod('isPlatformAdmin');

        $this->assertTrue($method->isProtected());
    }

    #[Test]
    public function middleware_has_session_key_constant(): void
    {
        $this->assertEquals('filament_tenant_id', FilamentTenantMiddleware::TENANT_SESSION_KEY);
    }

    #[Test]
    public function handle_method_accepts_correct_parameters(): void
    {
        $reflection = new ReflectionClass(FilamentTenantMiddleware::class);
        $method = $reflection->getMethod('handle');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('request', $params[0]->getName());
        $this->assertEquals('next', $params[1]->getName());
    }
}
