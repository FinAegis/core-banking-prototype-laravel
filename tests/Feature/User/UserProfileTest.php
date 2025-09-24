<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Domain\User\Aggregates\UserActivityAggregate;
use App\Domain\User\Services\EnhancedUserProfileService;
use App\Domain\User\Services\UserAnalyticsService;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private EnhancedUserProfileService $profileService;

    private UserAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileService = new EnhancedUserProfileService(
            new UserAnalyticsService()
        );
        $this->analyticsService = new UserAnalyticsService();
    }

    public function test_can_create_user_profile(): void
    {
        $user = User::factory()->create();

        $profileData = [
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'phone_number' => '+1234567890',
            'country'      => 'US',
            'city'         => 'New York',
        ];

        $profile = $this->profileService->createProfile(
            (string) $user->id,
            $user->email,
            $profileData
        );

        $this->assertDatabaseHas('user_profiles', [
            'user_id'    => (string) $user->id,
            'email'      => $user->email,
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'status'     => 'active',
        ]);

        // Check activity was tracked
        $this->assertDatabaseHas('user_activities', [
            'user_id'  => (string) $user->id,
            'activity' => 'profile_created',
        ]);
    }

    public function test_can_update_user_profile(): void
    {
        $user = User::factory()->create();

        $profile = $this->profileService->createProfile(
            (string) $user->id,
            $user->email,
            ['first_name' => 'John']
        );

        $updated = $this->profileService->updateProfile((string) $user->id, [
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'city'       => 'Los Angeles',
        ]);

        $this->assertEquals('Jane', $updated->first_name);
        $this->assertEquals('Smith', $updated->last_name);
        $this->assertEquals('Los Angeles', $updated->city);

        // Check activity was tracked
        $this->assertDatabaseHas('user_activities', [
            'user_id'  => (string) $user->id,
            'activity' => 'profile_updated',
        ]);
    }

    public function test_can_update_preferences(): void
    {
        $user = User::factory()->create();

        $profile = $this->profileService->createProfile(
            (string) $user->id,
            $user->email
        );

        $preferences = [
            'theme'    => 'dark',
            'language' => 'en',
            'timezone' => 'America/New_York',
        ];

        $updated = $this->profileService->updatePreferences((string) $user->id, $preferences);

        $this->assertEquals($preferences, $updated->preferences);

        // Check activity was tracked
        $this->assertDatabaseHas('user_activities', [
            'user_id'  => (string) $user->id,
            'activity' => 'preferences_updated',
        ]);
    }

    public function test_can_track_user_activity(): void
    {
        $user = User::factory()->create();
        $userId = (string) $user->id;

        $this->profileService->trackActivity(
            $userId,
            'login',
            ['method' => 'email', 'device' => 'desktop'],
            '192.168.1.1',
            'Mozilla/5.0',
            'session123'
        );

        $activity = UserActivity::where('user_id', $userId)
            ->where('activity', 'login')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('login', $activity->activity);
        $this->assertEquals(['method' => 'email', 'device' => 'desktop'], $activity->context);
        $this->assertEquals('192.168.1.1', $activity->ip_address);
    }

    public function test_can_get_user_analytics(): void
    {
        $user = User::factory()->create();
        $userId = (string) $user->id;

        // Create profile
        $this->profileService->createProfile(
            $userId,
            $user->email,
            ['first_name' => 'John', 'is_verified' => true]
        );

        // Track various activities
        $this->profileService->trackActivity($userId, 'login', ['device' => 'desktop']);
        $this->profileService->trackActivity($userId, 'view_dashboard', []);
        $this->profileService->trackActivity($userId, 'update_settings', []);

        $analytics = $this->profileService->getUserAnalytics($userId);

        $this->assertArrayHasKey('profile', $analytics);
        $this->assertArrayHasKey('activity', $analytics);
        $this->assertArrayHasKey('engagement', $analytics);
        $this->assertArrayHasKey('behavior', $analytics);

        $this->assertEquals(3, $analytics['activity']['total_activities']);
    }

    public function test_can_verify_profile(): void
    {
        $user = User::factory()->create();

        $profile = $this->profileService->createProfile(
            (string) $user->id,
            $user->email
        );

        $this->assertFalse($profile->is_verified);

        $this->profileService->verifyProfile((string) $user->id, 'email');

        $profile->refresh();
        $this->assertTrue($profile->is_verified);

        // Check activity was tracked
        $this->assertDatabaseHas('user_activities', [
            'user_id'  => (string) $user->id,
            'activity' => 'profile_verified',
        ]);
    }

    public function test_can_suspend_profile(): void
    {
        $user = User::factory()->create();

        $profile = $this->profileService->createProfile(
            (string) $user->id,
            $user->email
        );

        $this->assertEquals('active', $profile->status);

        $this->profileService->suspendProfile((string) $user->id, 'Terms violation');

        $profile->refresh();
        $this->assertEquals('suspended', $profile->status);
        $this->assertEquals('Terms violation', $profile->suspension_reason);
        $this->assertNotNull($profile->suspended_at);

        // Check activity was tracked
        $this->assertDatabaseHas('user_activities', [
            'user_id'  => (string) $user->id,
            'activity' => 'profile_suspended',
        ]);
    }

    public function test_activity_aggregate_tracks_correctly(): void
    {
        $userId = '123';
        $aggregateUuid = "user-activity-{$userId}";

        $aggregate = UserActivityAggregate::retrieve($aggregateUuid);

        $aggregate->trackActivity(
            $userId,
            'test_action',
            ['test' => 'data'],
            '192.168.1.1',
            'TestAgent',
            'session123'
        );

        $aggregate->persist();

        // Retrieve and check
        $aggregate = UserActivityAggregate::retrieve($aggregateUuid);

        $this->assertEquals(1, $aggregate->getActivityCount());
        $this->assertTrue($aggregate->hasPerformedActivity('test_action'));
        $this->assertNotNull($aggregate->getLastActivityAt());

        $recent = $aggregate->getRecentActivities(1);
        $this->assertCount(1, $recent);
        $this->assertEquals('test_action', $recent[0]['activity']);
    }

    public function test_user_segmentation_works_correctly(): void
    {
        $user = User::factory()->create();
        $userId = (string) $user->id;

        // Create fully completed profile
        $this->profileService->createProfile(
            $userId,
            $user->email,
            [
                'first_name'    => 'John',
                'last_name'     => 'Doe',
                'phone_number'  => '+1234567890',
                'date_of_birth' => '1990-01-01',
                'country'       => 'US',
                'city'          => 'New York',
                'is_verified'   => true,
            ]
        );

        // Track high engagement activities
        for ($i = 0; $i < 30; $i++) {
            $this->profileService->trackActivity($userId, 'login', []);
            $this->profileService->trackActivity($userId, 'view_dashboard', []);
        }

        $segment = $this->analyticsService->getUserSegment($userId);

        // With high engagement and profile completeness, should be power_user or active_user
        $this->assertContains($segment, ['power_user', 'active_user']);
    }

    public function test_can_get_profile_with_activities(): void
    {
        $user = User::factory()->create();
        $userId = (string) $user->id;

        $this->profileService->createProfile(
            $userId,
            $user->email,
            ['first_name' => 'John']
        );

        // Track some activities
        $this->profileService->trackActivity($userId, 'login', []);
        $this->profileService->trackActivity($userId, 'view_dashboard', []);

        $data = $this->profileService->getProfileWithActivities($userId);

        $this->assertArrayHasKey('profile', $data);
        $this->assertArrayHasKey('recent_activities', $data);
        $this->assertArrayHasKey('activity_count', $data);
        $this->assertArrayHasKey('last_activity', $data);
        $this->assertArrayHasKey('segment', $data);

        $this->assertEquals(2, $data['activity_count']);
        $this->assertNotEmpty($data['recent_activities']);
    }
}
