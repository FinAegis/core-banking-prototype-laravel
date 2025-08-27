<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Compliance\Activities;

use App\Domain\Compliance\Activities\KycSubmissionActivity;
use App\Domain\Compliance\Services\KycService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use Workflow\Activity;

class KycSubmissionActivityTest extends TestCase
{
    #[Test]
    public function test_activity_extends_workflow_activity()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycSubmissionActivity($kycService);

        $this->assertInstanceOf(Activity::class, $activity);
    }

    #[Test]
    public function test_execute_method_validates_required_parameters()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycSubmissionActivity($kycService);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameters: user_uuid, documents');

        $activity->execute([]);
    }

    #[Test]
    public function test_execute_method_validates_missing_user_uuid()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycSubmissionActivity($kycService);

        $this->expectException(InvalidArgumentException::class);

        $activity->execute([
            'documents' => [['type' => 'passport']],
        ]);
    }

    #[Test]
    public function test_execute_method_validates_empty_documents()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycSubmissionActivity($kycService);

        $this->expectException(InvalidArgumentException::class);

        $activity->execute([
            'user_uuid' => 'test-uuid',
            'documents' => [],
        ]);
    }

    #[Test]
    public function test_execute_method_has_correct_signature()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycSubmissionActivity($kycService);

        $reflection = new ReflectionClass($activity);
        $executeMethod = $reflection->getMethod('execute');

        $this->assertTrue($executeMethod->isPublic());
        $this->assertEquals('execute', $executeMethod->getName());

        $parameters = $executeMethod->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('input', $parameters[0]->getName());
        $this->assertEquals('array', $parameters[0]->getType()->getName());
    }
}
