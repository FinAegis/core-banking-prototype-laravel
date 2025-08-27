<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Compliance\Activities;

use App\Domain\Compliance\Activities\KycVerificationActivity;
use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use Workflow\Activity;

class KycVerificationActivityTest extends TestCase
{
    #[Test]
    public function test_activity_extends_workflow_activity()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycVerificationActivity($kycService);

        $this->assertInstanceOf(Activity::class, $activity);
    }

    #[Test]
    public function test_execute_method_validates_required_parameters()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycVerificationActivity($kycService);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameters: user_uuid, action, verified_by');

        $activity->execute([]);
    }

    #[Test]
    public function test_execute_method_validates_invalid_action()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycVerificationActivity($kycService);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action must be either "approve" or "reject"');

        $activity->execute([
            'user_uuid'   => 'test-uuid',
            'action'      => 'invalid',
            'verified_by' => 'admin',
        ]);
    }

    #[Test]
    public function test_execute_method_validates_missing_reason_for_reject()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason is required for rejection');

        // Create a user to avoid ModelNotFoundException
        $user = User::factory()->create(['uuid' => 'test-uuid']);

        $kycService = Mockery::mock(KycService::class);
        $activity = new KycVerificationActivity($kycService);

        $activity->execute([
            'user_uuid'   => 'test-uuid',
            'action'      => 'reject',
            'verified_by' => 'admin',
        ]);
    }

    #[Test]
    public function test_execute_method_has_correct_signature()
    {
        $kycService = Mockery::mock(KycService::class);
        $activity = new KycVerificationActivity($kycService);

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
