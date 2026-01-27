<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use PHPUnit\Framework\TestCase;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

/**
 * Unit tests for TenantCouldNotBeIdentifiedByTeamException.
 *
 * @group multitenancy
 */
class TenantCouldNotBeIdentifiedByTeamExceptionTest extends TestCase
{
    public function test_exception_extends_base_exception(): void
    {
        $exception = new TenantCouldNotBeIdentifiedByTeamException();

        $this->assertInstanceOf(TenantCouldNotBeIdentifiedException::class, $exception);
    }

    public function test_exception_without_team_id(): void
    {
        $exception = new TenantCouldNotBeIdentifiedByTeamException();

        $this->assertNull($exception->getTeamId());
        $this->assertStringContainsString('No team context available', $exception->getMessage());
    }

    public function test_exception_with_null_team_id(): void
    {
        $exception = new TenantCouldNotBeIdentifiedByTeamException(null);

        $this->assertNull($exception->getTeamId());
        $this->assertStringContainsString('No team context available', $exception->getMessage());
    }

    public function test_exception_with_team_id(): void
    {
        $exception = new TenantCouldNotBeIdentifiedByTeamException(123);

        $this->assertEquals(123, $exception->getTeamId());
        $this->assertStringContainsString('team ID: 123', $exception->getMessage());
    }

    public function test_exception_message_contains_team_id(): void
    {
        $exception = new TenantCouldNotBeIdentifiedByTeamException(456);

        $this->assertStringContainsString('456', $exception->getMessage());
    }

    public function test_exception_is_throwable(): void
    {
        $this->expectException(TenantCouldNotBeIdentifiedByTeamException::class);
        $this->expectExceptionMessage('team ID: 789');

        throw new TenantCouldNotBeIdentifiedByTeamException(789);
    }

    public function test_get_log_context_without_team_id(): void
    {
        $exception = new TenantCouldNotBeIdentifiedByTeamException();
        $context = $exception->getLogContext();

        $this->assertIsArray($context);
        $this->assertArrayHasKey('exception', $context);
        $this->assertArrayHasKey('team_id', $context);
        $this->assertArrayHasKey('has_team_id', $context);
        $this->assertNull($context['team_id']);
        $this->assertFalse($context['has_team_id']);
    }

    public function test_get_log_context_with_team_id(): void
    {
        $exception = new TenantCouldNotBeIdentifiedByTeamException(123);
        $context = $exception->getLogContext();

        $this->assertIsArray($context);
        $this->assertEquals(123, $context['team_id']);
        $this->assertTrue($context['has_team_id']);
        $this->assertEquals(TenantCouldNotBeIdentifiedByTeamException::class, $context['exception']);
    }
}
