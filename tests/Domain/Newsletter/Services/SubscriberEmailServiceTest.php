<?php

declare(strict_types=1);

namespace Tests\Domain\Newsletter\Services;

use App\Domain\Newsletter\Mail\SubscriberNewsletter;
use App\Domain\Newsletter\Mail\SubscriberWelcome;
use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Newsletter\Services\SubscriberEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriberEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriberEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = new SubscriberEmailService();
    }

    /** @param array<string, mixed> $overrides */
    private function createSubscriber(array $overrides = []): Subscriber
    {
        return Subscriber::create(array_merge([
            'email'  => 'test@example.com',
            'source' => Subscriber::SOURCE_BLOG,
            'status' => Subscriber::STATUS_ACTIVE,
            'tags'   => [],
        ], $overrides));
    }

    public function test_send_welcome_email_sends_to_subscriber(): void
    {
        $subscriber = $this->createSubscriber();

        $this->service->sendWelcomeEmail($subscriber);

        Mail::assertQueued(SubscriberWelcome::class, function ($mail) use ($subscriber) {
            return $mail->hasTo($subscriber->email);
        });
    }

    public function test_send_newsletter_sends_to_active_subscribers(): void
    {
        $this->createSubscriber(['email' => 'active1@test.com']);
        $this->createSubscriber(['email' => 'active2@test.com']);
        $this->createSubscriber(['email' => 'inactive@test.com', 'status' => Subscriber::STATUS_UNSUBSCRIBED]);

        $sentCount = $this->service->sendNewsletter('Test Subject', 'Test content');

        $this->assertEquals(2, $sentCount);
        Mail::assertQueued(SubscriberNewsletter::class, 2);
    }

    public function test_send_newsletter_filters_by_source(): void
    {
        $this->createSubscriber(['email' => 'blog@test.com', 'source' => Subscriber::SOURCE_BLOG]);
        $this->createSubscriber(['email' => 'cgo@test.com', 'source' => Subscriber::SOURCE_CGO]);

        $sentCount = $this->service->sendNewsletter('Test', 'Content', [], Subscriber::SOURCE_BLOG);

        $this->assertEquals(1, $sentCount);
        Mail::assertQueued(SubscriberNewsletter::class, function ($mail) {
            return $mail->hasTo('blog@test.com');
        });
    }

    public function test_send_newsletter_filters_by_tags(): void
    {
        $this->createSubscriber(['email' => 'tagged@test.com', 'tags' => ['defi', 'news']]);
        $this->createSubscriber(['email' => 'untagged@test.com', 'tags' => ['general']]);

        $sentCount = $this->service->sendNewsletter('DeFi Update', 'Content', ['defi']);

        $this->assertEquals(1, $sentCount);
        Mail::assertQueued(SubscriberNewsletter::class, function ($mail) {
            return $mail->hasTo('tagged@test.com');
        });
    }

    public function test_send_newsletter_returns_zero_when_no_subscribers(): void
    {
        $sentCount = $this->service->sendNewsletter('Empty', 'No recipients');

        $this->assertEquals(0, $sentCount);
        Mail::assertNothingQueued();
    }

    public function test_subscribe_creates_new_subscriber(): void
    {
        $subscriber = $this->service->subscribe(
            'new@example.com',
            Subscriber::SOURCE_FOOTER,
            ['welcome'],
            '10.0.0.1',
            'TestAgent'
        );

        $this->assertInstanceOf(Subscriber::class, $subscriber);
        $this->assertEquals('new@example.com', $subscriber->email);
        $this->assertEquals(Subscriber::SOURCE_FOOTER, $subscriber->source);
        $this->assertTrue($subscriber->isActive());
        $this->assertNotNull($subscriber->confirmed_at);
        Mail::assertQueued(SubscriberWelcome::class);
    }

    public function test_subscribe_reactivates_unsubscribed_user(): void
    {
        $existing = $this->createSubscriber([
            'email'  => 'comeback@test.com',
            'status' => Subscriber::STATUS_UNSUBSCRIBED,
        ]);

        $subscriber = $this->service->subscribe('comeback@test.com', Subscriber::SOURCE_BLOG);

        $this->assertTrue($subscriber->isActive());
        $this->assertEquals($existing->id, $subscriber->id);
    }

    public function test_subscribe_adds_tags_to_existing_subscriber(): void
    {
        $this->createSubscriber([
            'email' => 'existing@test.com',
            'tags'  => ['original'],
        ]);

        $subscriber = $this->service->subscribe('existing@test.com', Subscriber::SOURCE_BLOG, ['new-tag']);
        $subscriber->refresh();

        $tags = (array) $subscriber->tags;
        $this->assertContains('original', $tags);
        $this->assertContains('new-tag', $tags);
    }

    public function test_process_unsubscribe_marks_subscriber_inactive(): void
    {
        $this->createSubscriber(['email' => 'leaving@test.com']);

        $result = $this->service->processUnsubscribe('leaving@test.com', 'Too many emails');

        $this->assertTrue($result);
        /** @var Subscriber $subscriber */
        $subscriber = Subscriber::where('email', 'leaving@test.com')->first();
        $this->assertFalse($subscriber->isActive());
        $this->assertEquals('Too many emails', $subscriber->unsubscribe_reason);
    }

    public function test_process_unsubscribe_returns_false_for_unknown_email(): void
    {
        $result = $this->service->processUnsubscribe('unknown@test.com');

        $this->assertFalse($result);
    }

    public function test_process_unsubscribe_returns_false_for_already_unsubscribed(): void
    {
        $this->createSubscriber([
            'email'  => 'already@test.com',
            'status' => Subscriber::STATUS_UNSUBSCRIBED,
        ]);

        $result = $this->service->processUnsubscribe('already@test.com');

        $this->assertFalse($result);
    }
}
