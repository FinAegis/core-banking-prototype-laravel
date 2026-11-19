<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\OpenBanking\Services;

use App\Domain\OpenBanking\Services\ConsentService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Structural tests for ConsentService.
 *
 * These tests verify the service methods exist and have the correct
 * signatures without requiring database access.
 */
class ConsentServiceTest extends TestCase
{
    #[Test]
    public function service_class_exists(): void
    {
        $this->assertNotEmpty((new ReflectionClass(ConsentService::class))->getName());
    }

    #[Test]
    public function service_is_final(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function service_has_create_consent_method(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $method = $reflection->getMethod('createConsent');

        $this->assertTrue($method->isPublic());
        $this->assertCount(4, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('tppId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
        $this->assertEquals('permissions', $params[2]->getName());
        $this->assertEquals('accountIds', $params[3]->getName());

        // accountIds is optional
        $this->assertTrue($params[3]->isDefaultValueAvailable());
        $this->assertNull($params[3]->getDefaultValue());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('App\Domain\OpenBanking\Models\Consent', $returnType->getName());
    }

    #[Test]
    public function service_has_authorize_consent_method(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $method = $reflection->getMethod('authorizeConsent');

        $this->assertTrue($method->isPublic());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('consentId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('App\Domain\OpenBanking\Models\Consent', $returnType->getName());
    }

    #[Test]
    public function service_has_reject_consent_method(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $method = $reflection->getMethod('rejectConsent');

        $this->assertTrue($method->isPublic());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('consentId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('App\Domain\OpenBanking\Models\Consent', $returnType->getName());
    }

    #[Test]
    public function service_has_revoke_consent_method(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $method = $reflection->getMethod('revokeConsent');

        $this->assertTrue($method->isPublic());
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('consentId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('App\Domain\OpenBanking\Models\Consent', $returnType->getName());
    }

    #[Test]
    public function service_has_get_consent_method(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $method = $reflection->getMethod('getConsent');

        $this->assertTrue($method->isPublic());
        // Scoped to the owner: (consentId, userId).
        $this->assertCount(2, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('consentId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('App\Domain\OpenBanking\Models\Consent', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    #[Test]
    public function service_has_get_active_consents_for_user_method(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $method = $reflection->getMethod('getActiveConsentsForUser');

        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());

        $params = $method->getParameters();
        $this->assertEquals('userId', $params[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('Illuminate\Database\Eloquent\Collection', $returnType->getName());
    }

    #[Test]
    public function service_has_expire_stale_consents_method(): void
    {
        $reflection = new ReflectionClass(ConsentService::class);
        $method = $reflection->getMethod('expireStaleConsents');

        $this->assertTrue($method->isPublic());
        $this->assertCount(0, $method->getParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        /** @var ReflectionNamedType $returnType */
        $this->assertEquals('int', $returnType->getName());
    }
}
