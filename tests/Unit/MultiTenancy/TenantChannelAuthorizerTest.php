<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Broadcasting\TenantChannelAuthorizer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Tests for TenantChannelAuthorizer.
 *
 * These are structural tests that verify the class methods exist
 * and have the correct signatures without requiring database access.
 */
class TenantChannelAuthorizerTest extends TestCase
{
    #[Test]
    public function authorizer_class_exists(): void
    {
        $this->assertTrue(class_exists(TenantChannelAuthorizer::class));
    }

    #[Test]
    public function authorize_user_method_exists_and_is_static(): void
    {
        $reflection = new ReflectionClass(TenantChannelAuthorizer::class);
        $method = $reflection->getMethod('authorizeUser');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    #[Test]
    public function authorize_admin_method_exists_and_is_static(): void
    {
        $reflection = new ReflectionClass(TenantChannelAuthorizer::class);
        $method = $reflection->getMethod('authorizeAdmin');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    #[Test]
    public function user_belongs_to_tenant_method_exists(): void
    {
        $reflection = new ReflectionClass(TenantChannelAuthorizer::class);
        $method = $reflection->getMethod('userBelongsToTenant');

        $this->assertTrue($method->isProtected());
        $this->assertTrue($method->isStatic());
    }

    #[Test]
    public function user_is_team_admin_method_exists(): void
    {
        $reflection = new ReflectionClass(TenantChannelAuthorizer::class);
        $method = $reflection->getMethod('userIsTeamAdmin');

        $this->assertTrue($method->isProtected());
        $this->assertTrue($method->isStatic());
    }

    #[Test]
    public function authorize_user_accepts_correct_parameters(): void
    {
        $reflection = new ReflectionClass(TenantChannelAuthorizer::class);
        $method = $reflection->getMethod('authorizeUser');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('user', $params[0]->getName());
        $this->assertEquals('tenantId', $params[1]->getName());

        // Verify first param type is User
        $userType = $params[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $userType);
        /** @var ReflectionNamedType $userType */
        $this->assertEquals(User::class, $userType->getName());

        // Verify second param type is string
        $tenantIdType = $params[1]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $tenantIdType);
        /** @var ReflectionNamedType $tenantIdType */
        $this->assertEquals('string', $tenantIdType->getName());
    }
}
