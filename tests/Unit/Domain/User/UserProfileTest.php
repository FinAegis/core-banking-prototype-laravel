<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User;

use App\Domain\User\Models\UserProfile;
use App\Domain\User\Services\UserProfileService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private UserProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserProfileService();
    }

    public function test_can_create_user_profile(): void
    {
        $user = User::factory()->create();

        $profile = $this->service->createProfile($user, [
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'phone_number' => '+1234567890',
        ]);

        $this->assertInstanceOf(UserProfile::class, $profile);
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals('John', $profile->first_name);
        $this->assertEquals('Doe', $profile->last_name);
        $this->assertEquals('+1234567890', $profile->phone_number);
        $this->assertEquals('active', $profile->status);
        $this->assertFalse($profile->is_verified);
    }

    public function test_can_update_preferences(): void
    {
        $user = User::factory()->create();
        $profile = $this->service->createProfile($user);

        $updated = $this->service->updatePreferences((string) $user->id, [
            'language' => 'es',
            'timezone' => 'Europe/Madrid',
            'currency' => 'EUR',
            'darkMode' => true,
        ], 'user');

        $this->assertEquals('es', $updated->preferences['language']);
        $this->assertEquals('EUR', $updated->preferences['currency']);
        $this->assertTrue($updated->preferences['darkMode']);
    }

    public function test_can_track_activity(): void
    {
        $user = User::factory()->create();
        $profile = $this->service->createProfile($user);

        $this->service->trackActivity((string) $user->id, 'login', [
            'method' => 'password',
            'device' => 'mobile',
        ]);

        $activities = $this->service->getUserActivities((string) $user->id);

        $this->assertCount(1, $activities);
        $this->assertEquals('login', $activities->first()->activity);
        $this->assertEquals('password', $activities->first()->context['method']);
    }

    public function test_can_verify_profile(): void
    {
        $user = User::factory()->create();
        $profile = $this->service->createProfile($user);

        $this->assertFalse($profile->is_verified);

        $verified = $this->service->verifyProfile((string) $user->id, 'email', 'system');

        $this->assertTrue($verified->is_verified);
    }

    public function test_can_suspend_profile(): void
    {
        $user = User::factory()->create();
        $profile = $this->service->createProfile($user);

        $this->assertEquals('active', $profile->status);

        $suspended = $this->service->suspendProfile(
            (string) $user->id,
            'Terms violation',
            'admin'
        );

        $this->assertEquals('suspended', $suspended->status);
        $this->assertEquals('Terms violation', $suspended->suspension_reason);
        $this->assertNotNull($suspended->suspended_at);
    }
}
