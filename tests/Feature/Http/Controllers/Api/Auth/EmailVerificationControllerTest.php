<?php

namespace Tests\Feature\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class EmailVerificationControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $unverifiedUser;

    protected User $verifiedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable rate limiting for tests
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->unverifiedUser = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
        ]);

        $this->verifiedUser = User::factory()->create([
            'email'             => 'verified@example.com',
            'email_verified_at' => now(),
        ]);
    }

    #[Test]
    public function test_verify_email_with_valid_link(): void
    {
        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $this->unverifiedUser->id,
                'hash' => sha1($this->unverifiedUser->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully.',
            ]);

        // Check that email was verified
        $this->assertNotNull($this->unverifiedUser->fresh()->email_verified_at);

        // Check that Verified event was dispatched
        Event::assertDispatched(Verified::class, function ($event) {
            return $event->user->id === $this->unverifiedUser->id;
        });
    }

    #[Test]
    public function test_verify_email_with_invalid_hash(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $this->unverifiedUser->id,
                'hash' => 'invalid-hash',
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Invalid verification link.',
            ]);

        // Check that email was not verified
        $this->assertNull($this->unverifiedUser->fresh()->email_verified_at);
    }

    #[Test]
    public function test_verify_email_with_expired_link(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->subMinutes(1), // Expired
            [
                'id'   => $this->unverifiedUser->id,
                'hash' => sha1($this->unverifiedUser->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(403);
        // The exact message may vary based on middleware
        $this->assertContains($response->json('message'), [
            'Invalid or expired verification link.',
            'Invalid signature.',
        ]);
    }

    #[Test]
    public function test_verify_email_with_invalid_signature(): void
    {
        $response = $this->getJson(sprintf(
            '/api/auth/verify-email/%d/%s?expires=%s&signature=%s',
            $this->unverifiedUser->id,
            sha1($this->unverifiedUser->email),
            Carbon::now()->addMinutes(60)->timestamp,
            'invalid-signature'
        ));

        $response->assertStatus(403);
        // The exact message may vary based on middleware
        $this->assertContains($response->json('message'), [
            'Invalid or expired verification link.',
            'Invalid signature.',
        ]);
    }

    #[Test]
    public function test_verify_email_when_already_verified(): void
    {
        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $this->verifiedUser->id,
                'hash' => sha1($this->verifiedUser->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email already verified.',
            ]);

        // Check that Verified event was NOT dispatched
        Event::assertNotDispatched(Verified::class);
    }

    #[Test]
    public function test_verify_email_with_nonexistent_user(): void
    {
        $nonExistentId = 999999;

        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $nonExistentId,
                'hash' => sha1('some@email.com'),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(404);
    }

    #[Test]
    public function test_resend_verification_email_for_unverified_user(): void
    {
        Sanctum::actingAs($this->unverifiedUser);

        $response = $this->postJson('/api/auth/resend-verification');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification link sent.',
            ]);
    }

    #[Test]
    public function test_resend_verification_email_for_verified_user(): void
    {
        Sanctum::actingAs($this->verifiedUser);

        $response = $this->postJson('/api/auth/resend-verification');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Email already verified.',
            ]);
    }

    #[Test]
    public function test_resend_verification_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/resend-verification');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_verify_email_updates_verified_at_timestamp(): void
    {
        Event::fake();

        $this->assertNull($this->unverifiedUser->email_verified_at);

        Carbon::setTestNow(Carbon::create(2023, 10, 15, 12, 0, 0));

        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $this->unverifiedUser->id,
                'hash' => sha1($this->unverifiedUser->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(200);

        $user = $this->unverifiedUser->fresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('2023-10-15 12:00:00', $user->email_verified_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    #[Test]
    public function test_verify_email_requires_all_query_parameters(): void
    {
        // Missing signature
        $response = $this->getJson(sprintf(
            '/api/auth/verify-email/%d/%s?expires=%s',
            $this->unverifiedUser->id,
            sha1($this->unverifiedUser->email),
            Carbon::now()->addMinutes(60)->timestamp
        ));

        $response->assertStatus(403);

        // Missing expires
        $response = $this->getJson(sprintf(
            '/api/auth/verify-email/%d/%s?signature=%s',
            $this->unverifiedUser->id,
            sha1($this->unverifiedUser->email),
            'some-signature'
        ));

        $response->assertStatus(403);
    }

    #[Test]
    public function test_resend_verification_triggers_notification(): void
    {
        Sanctum::actingAs($this->unverifiedUser);

        $mock = Mockery::mock($this->unverifiedUser)->makePartial();
        $mock->shouldReceive('sendEmailVerificationNotification')->once();

        $this->app->instance(User::class, $mock);

        // Need to bind the mock user to the request
        $this->actingAs($mock, 'sanctum');

        $response = $this->postJson('/api/auth/resend-verification');

        $response->assertStatus(200);
    }
}
