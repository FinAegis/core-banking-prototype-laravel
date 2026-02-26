<?php

declare(strict_types=1);

namespace Tests\Domain\Newsletter\Models;

use App\Domain\Newsletter\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function createSubscriber(array $overrides = []): Subscriber
    {
        return Subscriber::create(array_merge([
            'email'  => 'test@example.com',
            'source' => Subscriber::SOURCE_BLOG,
            'status' => Subscriber::STATUS_ACTIVE,
            'tags'   => ['general'],
        ], $overrides));
    }

    public function test_it_creates_subscriber_with_fillable_fields(): void
    {
        $subscriber = $this->createSubscriber([
            'email'      => 'new@example.com',
            'source'     => Subscriber::SOURCE_CGO,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertDatabaseHas('subscribers', [
            'email'  => 'new@example.com',
            'source' => 'cgo',
            'status' => 'active',
        ]);
    }

    public function test_is_active_returns_true_for_active_subscriber(): void
    {
        $subscriber = $this->createSubscriber(['status' => Subscriber::STATUS_ACTIVE]);
        $this->assertTrue($subscriber->isActive());
    }

    public function test_is_active_returns_false_for_unsubscribed(): void
    {
        $subscriber = $this->createSubscriber(['status' => Subscriber::STATUS_UNSUBSCRIBED]);
        $this->assertFalse($subscriber->isActive());
    }

    public function test_is_active_returns_false_for_bounced(): void
    {
        $subscriber = $this->createSubscriber(['status' => Subscriber::STATUS_BOUNCED]);
        $this->assertFalse($subscriber->isActive());
    }

    public function test_unsubscribe_updates_status_and_timestamp(): void
    {
        $subscriber = $this->createSubscriber();

        $subscriber->unsubscribe('No longer interested');
        $subscriber->refresh();

        $this->assertEquals(Subscriber::STATUS_UNSUBSCRIBED, $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertEquals('No longer interested', $subscriber->unsubscribe_reason);
    }

    public function test_unsubscribe_without_reason(): void
    {
        $subscriber = $this->createSubscriber();

        $subscriber->unsubscribe();
        $subscriber->refresh();

        $this->assertEquals(Subscriber::STATUS_UNSUBSCRIBED, $subscriber->status);
        $this->assertNull($subscriber->unsubscribe_reason);
    }

    public function test_add_tags_merges_new_tags(): void
    {
        $subscriber = $this->createSubscriber(['tags' => ['alpha', 'beta']]);

        $subscriber->addTags(['gamma', 'alpha']);
        $subscriber->refresh();

        $tags = (array) $subscriber->tags;
        $this->assertContains('alpha', $tags);
        $this->assertContains('beta', $tags);
        $this->assertContains('gamma', $tags);
        $this->assertCount(3, $tags);
    }

    public function test_remove_tags_removes_specified_tags(): void
    {
        $subscriber = $this->createSubscriber(['tags' => ['alpha', 'beta', 'gamma']]);

        $subscriber->removeTags(['beta']);
        $subscriber->refresh();

        $tags = (array) $subscriber->tags;
        $this->assertContains('alpha', $tags);
        $this->assertNotContains('beta', $tags);
        $this->assertContains('gamma', $tags);
    }

    public function test_has_tag_returns_true_when_tag_exists(): void
    {
        $subscriber = $this->createSubscriber(['tags' => ['newsletter', 'updates']]);
        $this->assertTrue($subscriber->hasTag('newsletter'));
    }

    public function test_has_tag_returns_false_when_tag_missing(): void
    {
        $subscriber = $this->createSubscriber(['tags' => ['newsletter']]);
        $this->assertFalse($subscriber->hasTag('promotions'));
    }

    public function test_has_tag_handles_null_tags(): void
    {
        $subscriber = $this->createSubscriber(['tags' => null]);
        $this->assertFalse($subscriber->hasTag('anything'));
    }

    public function test_scope_active_filters_correctly(): void
    {
        $this->createSubscriber(['email' => 'a@test.com', 'status' => Subscriber::STATUS_ACTIVE]);
        $this->createSubscriber(['email' => 'b@test.com', 'status' => Subscriber::STATUS_UNSUBSCRIBED]);
        $this->createSubscriber(['email' => 'c@test.com', 'status' => Subscriber::STATUS_ACTIVE]);

        $active = Subscriber::active()->get();

        $this->assertCount(2, $active);
    }

    public function test_scope_by_source_filters_correctly(): void
    {
        $this->createSubscriber(['email' => 'a@test.com', 'source' => Subscriber::SOURCE_BLOG]);
        $this->createSubscriber(['email' => 'b@test.com', 'source' => Subscriber::SOURCE_CGO]);
        $this->createSubscriber(['email' => 'c@test.com', 'source' => Subscriber::SOURCE_BLOG]);

        $blogSubscribers = Subscriber::bySource(Subscriber::SOURCE_BLOG)->get();

        $this->assertCount(2, $blogSubscribers);
    }

    public function test_preferences_cast_to_array(): void
    {
        $subscriber = $this->createSubscriber([
            'preferences' => ['frequency' => 'weekly', 'format' => 'html'],
        ]);
        $subscriber->refresh();

        $this->assertIsArray($subscriber->preferences);
        $this->assertEquals('weekly', $subscriber->preferences['frequency']);
    }

    public function test_confirmed_at_cast_to_datetime(): void
    {
        $subscriber = $this->createSubscriber(['confirmed_at' => now()]);
        $subscriber->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $subscriber->confirmed_at);
    }

    public function test_source_constants_have_expected_values(): void
    {
        $this->assertEquals('blog', Subscriber::SOURCE_BLOG);
        $this->assertEquals('cgo', Subscriber::SOURCE_CGO);
        $this->assertEquals('investment', Subscriber::SOURCE_INVESTMENT);
        $this->assertEquals('footer', Subscriber::SOURCE_FOOTER);
        $this->assertEquals('contact', Subscriber::SOURCE_CONTACT);
        $this->assertEquals('partner', Subscriber::SOURCE_PARTNER);
    }
}
