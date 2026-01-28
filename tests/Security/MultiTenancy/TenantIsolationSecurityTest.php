<?php

declare(strict_types=1);

namespace Tests\Security\MultiTenancy;

use App\Http\Middleware\InitializeTenancyByTeam;
use App\Models\Tenant;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Security audit tests for multi-tenancy isolation.
 *
 * These are pure unit tests that verify structural security aspects
 * without requiring database or Redis access.
 */
class TenantIsolationSecurityTest extends TestCase
{
    #[Test]
    public function middleware_class_exists(): void
    {
        $this->assertTrue(class_exists(InitializeTenancyByTeam::class));
    }

    #[Test]
    public function middleware_has_handle_method(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        $this->assertTrue(method_exists(InitializeTenancyByTeam::class, 'handle'));
    }

    #[Test]
    public function tenant_model_exists(): void
    {
        $this->assertTrue(class_exists(Tenant::class));
    }

    #[Test]
    public function tenant_model_has_required_security_attributes(): void
    {
        // Use reflection to check fillable property without instantiating model
        // (instantiation triggers Laravel container which isn't available in pure unit tests)
        $reflection = new ReflectionClass(Tenant::class);
        $fillableProperty = $reflection->getProperty('fillable');

        /** @var array<string> $fillable */
        $fillable = $fillableProperty->getDefaultValue();

        // Verify fillable attributes don't include sensitive fields
        $this->assertNotContains('password', $fillable);
        $this->assertNotContains('secret', $fillable);
    }

    #[Test]
    public function user_model_does_not_expose_sensitive_data(): void
    {
        // Use reflection to check hidden property without instantiating model
        $reflection = new ReflectionClass(User::class);
        $hiddenProperty = $reflection->getProperty('hidden');

        /** @var array<string> $hidden */
        $hidden = $hiddenProperty->getDefaultValue();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    #[Test]
    public function tenant_model_has_team_relationship_method(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        $this->assertTrue(method_exists(Tenant::class, 'team'));
    }

    #[Test]
    public function tenant_model_implements_required_interfaces(): void
    {
        $reflection = new ReflectionClass(Tenant::class);
        $interfaces = $reflection->getInterfaceNames();

        // Tenant must implement TenantWithDatabase for proper isolation
        $this->assertContains(
            'Stancl\Tenancy\Contracts\TenantWithDatabase',
            $interfaces
        );
    }

    #[Test]
    public function tenant_model_uses_has_database_trait(): void
    {
        $reflection = new ReflectionClass(Tenant::class);
        $traits = $reflection->getTraitNames();

        // Must use HasDatabase for database isolation
        $this->assertContains(
            'Stancl\Tenancy\Database\Concerns\HasDatabase',
            $traits
        );
    }

    #[Test]
    public function tenant_model_has_custom_columns_for_team_link(): void
    {
        $columns = Tenant::getCustomColumns();

        // Must have team_id for linking to existing Teams
        $this->assertContains('team_id', $columns);
    }
}
