<?php

namespace Tests\Feature;

use App\Domain\Newsletter\Mail\SubscriberConfirmation;
use App\Domain\Newsletter\Models\Subscriber;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogSubscriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The per-email and per-IP subscription limits are counted in the
        // cache store, which persists between tests when it is Redis.
        Cache::flush();
        Mail::fake();
    }

    /**
     * Subscribe through the blog form and return the confirmation link
     * from the double opt-in email.
     */
    private function subscribeAndGetConfirmationUrl(string $email, string $expectedMessage): string
    {
        $response = $this->postJson('/blog/subscribe', [
            'email' => $email,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => $expectedMessage,
        ]);

        // Nothing reaches Mailchimp before the address is confirmed.
        Http::assertNothingSent();
        $subscriber = Subscriber::where('email', $email)->firstOrFail();
        $this->assertSame(Subscriber::STATUS_PENDING, $subscriber->status);

        $url = null;
        Mail::assertQueued(SubscriberConfirmation::class, function (SubscriberConfirmation $mail) use ($email, &$url): bool {
            if (! $mail->hasTo($email)) {
                return false;
            }
            $url = $mail->confirmationUrl;

            return true;
        });
        $this->assertIsString($url);

        return $url;
    }

    #[Test]
    public function user_can_view_blog_page()
    {
        $response = $this->get('/blog');

        $response->assertOk();
        $response->assertSee('FinAegis Blog');
        $response->assertSee('Stay Updated');
        $response->assertSee('Subscribe');
    }

    #[Test]
    public function user_can_subscribe_to_newsletter()
    {
        // Mock successful Mailchimp response
        Http::fake([
            '*.api.mailchimp.com/*' => Http::response([
                'id'            => 'abc123',
                'email_address' => 'test@example.com',
                'status'        => 'subscribed',
            ], 200),
        ]);

        // Set test config
        config(['services.mailchimp.api_key' => 'test-key-us1']);
        config(['services.mailchimp.list_id' => 'test-list']);

        $url = $this->subscribeAndGetConfirmationUrl(
            'test@example.com',
            'Almost done! Check your inbox and confirm your email address to subscribe.'
        );

        $this->post($url)->assertOk()->assertSee('Subscription confirmed');

        // The confirmed address is added to Mailchimp as subscribed.
        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://us1.api.mailchimp.com/3.0/lists/test-list/members'
                && $request['email_address'] === 'test@example.com'
                && $request['status'] === 'subscribed'
                && $request['tags'] === ['blog_subscriber', 'finaegis_demo'];
        });
        $this->assertSame(Subscriber::STATUS_ACTIVE, Subscriber::where('email', 'test@example.com')->value('status'));
    }

    #[Test]
    public function handles_already_subscribed_email()
    {
        // Mock "already subscribed" response
        Http::fake([
            '*.api.mailchimp.com/*' => Http::response([
                'type'   => 'http://developer.mailchimp.com/documentation/mailchimp/guides/error-glossary/',
                'title'  => 'Member Exists',
                'status' => 400,
                'detail' => 'test@example.com is already a list member',
            ], 400),
        ]);

        config(['services.mailchimp.api_key' => 'test-key-us1']);
        config(['services.mailchimp.list_id' => 'test-list']);
        $log = Log::spy();

        $url = $this->subscribeAndGetConfirmationUrl(
            'test@example.com',
            'Almost done! Check your inbox and confirm your email address to subscribe.'
        );

        // An address Mailchimp already holds counts as synced.
        $this->post($url)->assertOk()->assertSee('Subscription confirmed');

        Http::assertSentCount(1);
        $this->assertSame(Subscriber::STATUS_ACTIVE, Subscriber::where('email', 'test@example.com')->value('status'));
        $log->shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'Mailchimp member already exists')->once();
        $log->shouldNotHaveReceived('error');
        $log->shouldNotHaveReceived('warning');
    }

    #[Test]
    public function validates_email_format()
    {
        $response = $this->postJson('/blog/subscribe', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function handles_missing_mailchimp_configuration()
    {
        Http::fake();

        // Clear Mailchimp config
        config(['services.mailchimp.api_key' => null]);
        config(['services.mailchimp.list_id' => null]);

        $url = $this->subscribeAndGetConfirmationUrl(
            'test@example.com',
            'Almost done! Check your inbox and confirm your email address to subscribe.'
        );

        $this->post($url)->assertOk()->assertSee('Subscription confirmed');

        Http::assertNothingSent();
        $this->assertSame(Subscriber::STATUS_ACTIVE, Subscriber::where('email', 'test@example.com')->value('status'));
    }

    #[Test]
    public function handles_mailchimp_api_error()
    {
        // Mock error response
        Http::fake([
            '*.api.mailchimp.com/*' => Http::response([
                'type'   => 'http://developer.mailchimp.com/documentation/mailchimp/guides/error-glossary/',
                'title'  => 'API Key Invalid',
                'status' => 401,
                'detail' => 'Your API key may be invalid',
            ], 401),
        ]);

        config(['services.mailchimp.api_key' => 'invalid-key-us1']);
        config(['services.mailchimp.list_id' => 'test-list']);
        $log = Log::spy();

        $url = $this->subscribeAndGetConfirmationUrl(
            'test@example.com',
            'Almost done! Check your inbox and confirm your email address to subscribe.'
        );

        // Rejected credentials are logged as an error; the local
        // confirmation stands.
        $this->post($url)->assertOk()->assertSee('Subscription confirmed');

        Http::assertSentCount(1);
        $this->assertSame(Subscriber::STATUS_ACTIVE, Subscriber::where('email', 'test@example.com')->value('status'));
        $log->shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context = []): bool => $message === 'Mailchimp API credentials rejected' && ($context['status'] ?? null) === 401
        )->once();
    }

    #[Test]
    public function does_not_sync_confirmed_subscribers_from_other_sources(): void
    {
        Http::fake();
        config(['services.mailchimp.api_key' => 'test-key-us1']);
        config(['services.mailchimp.list_id' => 'test-list']);

        $this->postJson('/subscriber/' . Subscriber::SOURCE_FOOTER, ['email' => 'footer@example.com'])->assertOk();

        $url = null;
        Mail::assertQueued(SubscriberConfirmation::class, function (SubscriberConfirmation $mail) use (&$url): bool {
            $url = $mail->confirmationUrl;

            return $mail->hasTo('footer@example.com');
        });
        $this->assertIsString($url);

        $this->post($url)->assertOk()->assertSee('Subscription confirmed');

        Http::assertNothingSent();
    }
}
