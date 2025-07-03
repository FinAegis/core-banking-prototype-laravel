<?php

namespace Tests\Feature;

use App\Models\Subscriber;
use App\Services\Email\SubscriberEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use App\Mail\SubscriberWelcome;
use App\Mail\SubscriberNewsletter;

class SubscriberTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_a_new_subscriber()
    {
        Mail::fake();
        
        $response = $this->postJson('/subscriber/blog', [
            'email' => 'test@example.com',
            'tags' => ['newsletter'],
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Thank you for subscribing! Please check your email.',
            ]);
        
        $this->assertDatabaseHas('subscribers', [
            'email' => 'test@example.com',
            'source' => 'blog',
            'status' => 'active',
        ]);
        
        Mail::assertQueued(SubscriberWelcome::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }
    
    /** @test */
    public function it_reactivates_existing_unsubscribed_subscriber()
    {
        Mail::fake();
        
        $subscriber = Subscriber::factory()->create([
            'email' => 'existing@example.com',
            'status' => 'unsubscribed',
            'unsubscribed_at' => now()->subDays(30),
        ]);
        
        $response = $this->postJson('/subscriber/blog', [
            'email' => 'existing@example.com',
        ]);
        
        $response->assertStatus(200);
        
        $subscriber->refresh();
        $this->assertEquals('active', $subscriber->status);
        $this->assertNull($subscriber->unsubscribed_at);
        
        Mail::assertNotQueued(SubscriberWelcome::class);
    }
    
    /** @test */
    public function it_validates_email_format()
    {
        $response = $this->postJson('/subscriber/blog', [
            'email' => 'invalid-email',
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
    
    /** @test */
    public function it_handles_unsubscribe_request()
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'unsubscribe@example.com',
            'status' => 'active',
        ]);
        
        $encryptedEmail = encrypt($subscriber->email);
        
        $response = $this->get("/subscriber/unsubscribe/{$encryptedEmail}");
        
        $response->assertStatus(200)
            ->assertViewIs('subscriber.unsubscribed')
            ->assertViewHas('message', 'You have been successfully unsubscribed from our mailing list.');
        
        $subscriber->refresh();
        $this->assertEquals('unsubscribed', $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }
    
    /** @test */
    public function it_handles_invalid_unsubscribe_link()
    {
        $response = $this->get('/subscriber/unsubscribe/invalid-encrypted-string');
        
        $response->assertStatus(200)
            ->assertViewHas('message', 'Invalid unsubscribe link. Please contact support if you need assistance.');
    }
    
    /** @test */
    public function it_adds_tags_to_subscriber()
    {
        $subscriber = Subscriber::factory()->create([
            'tags' => ['existing_tag'],
        ]);
        
        $subscriber->addTags(['new_tag1', 'new_tag2']);
        
        $this->assertContains('existing_tag', $subscriber->tags);
        $this->assertContains('new_tag1', $subscriber->tags);
        $this->assertContains('new_tag2', $subscriber->tags);
        $this->assertCount(3, $subscriber->tags);
    }
    
    /** @test */
    public function it_removes_tags_from_subscriber()
    {
        $subscriber = Subscriber::factory()->create([
            'tags' => ['tag1', 'tag2', 'tag3'],
        ]);
        
        $subscriber->removeTags(['tag2']);
        
        $this->assertContains('tag1', $subscriber->tags);
        $this->assertNotContains('tag2', $subscriber->tags);
        $this->assertContains('tag3', $subscriber->tags);
        $this->assertCount(2, $subscriber->tags);
    }
    
    /** @test */
    public function it_sends_newsletter_to_active_subscribers()
    {
        Mail::fake();
        
        // Create subscribers with different statuses
        $activeSubscriber1 = Subscriber::factory()->create(['status' => 'active']);
        $activeSubscriber2 = Subscriber::factory()->create(['status' => 'active']);
        $unsubscribedSubscriber = Subscriber::factory()->create(['status' => 'unsubscribed']);
        $bouncedSubscriber = Subscriber::factory()->create(['status' => 'bounced']);
        
        $service = new SubscriberEmailService();
        $sentCount = $service->sendNewsletter('Test Newsletter', 'Test content');
        
        $this->assertEquals(2, $sentCount);
        
        Mail::assertQueued(SubscriberNewsletter::class, function ($mail) use ($activeSubscriber1) {
            return $mail->hasTo($activeSubscriber1->email);
        });
        
        Mail::assertQueued(SubscriberNewsletter::class, function ($mail) use ($activeSubscriber2) {
            return $mail->hasTo($activeSubscriber2->email);
        });
        
        Mail::assertNotQueued(SubscriberNewsletter::class, function ($mail) use ($unsubscribedSubscriber) {
            return $mail->hasTo($unsubscribedSubscriber->email);
        });
    }
    
    /** @test */
    public function it_filters_newsletter_by_tags()
    {
        Mail::fake();
        
        $subscriber1 = Subscriber::factory()->create([
            'status' => 'active',
            'tags' => ['newsletter', 'product_updates'],
        ]);
        
        $subscriber2 = Subscriber::factory()->create([
            'status' => 'active',
            'tags' => ['blog_updates'],
        ]);
        
        $service = new SubscriberEmailService();
        $sentCount = $service->sendNewsletter(
            'Product Update',
            'New features released!',
            ['product_updates']
        );
        
        $this->assertEquals(1, $sentCount);
        
        Mail::assertQueued(SubscriberNewsletter::class, function ($mail) use ($subscriber1) {
            return $mail->hasTo($subscriber1->email);
        });
        
        Mail::assertNotQueued(SubscriberNewsletter::class, function ($mail) use ($subscriber2) {
            return $mail->hasTo($subscriber2->email);
        });
    }
    
    /** @test */
    public function it_filters_newsletter_by_source()
    {
        Mail::fake();
        
        $blogSubscriber = Subscriber::factory()->create([
            'status' => 'active',
            'source' => 'blog',
        ]);
        
        $cgoSubscriber = Subscriber::factory()->create([
            'status' => 'active',
            'source' => 'cgo',
        ]);
        
        $service = new SubscriberEmailService();
        $sentCount = $service->sendNewsletter(
            'Blog Update',
            'New blog post!',
            [],
            'blog'
        );
        
        $this->assertEquals(1, $sentCount);
        
        Mail::assertQueued(SubscriberNewsletter::class, function ($mail) use ($blogSubscriber) {
            return $mail->hasTo($blogSubscriber->email);
        });
        
        Mail::assertNotQueued(SubscriberNewsletter::class, function ($mail) use ($cgoSubscriber) {
            return $mail->hasTo($cgoSubscriber->email);
        });
    }
}