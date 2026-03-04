<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\Mobile\Models\MobilePushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createNotification(array $overrides = []): MobilePushNotification
    {
        return MobilePushNotification::create(array_merge([
            'user_id'           => $this->user->id,
            'notification_type' => MobilePushNotification::TYPE_TRANSACTION_RECEIVED,
            'title'             => 'Test Notification',
            'body'              => 'Test body content',
            'data'              => ['amount' => '100'],
            'status'            => MobilePushNotification::STATUS_SENT,
        ], $overrides));
    }

    public function test_list_notifications_requires_auth(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertStatus(401);
    }

    public function test_list_notifications_returns_paginated_results(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        // Create 5 notifications
        for ($i = 0; $i < 5; $i++) {
            $this->createNotification(['title' => "Notification {$i}"]);
        }

        $response = $this->getJson('/api/v1/notifications?offset=0&limit=3')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'body', 'data', 'read', 'created_at'],
                ],
                'meta' => ['total', 'offset', 'limit', 'unread_count'],
            ]);

        $data = $response->json();
        $this->assertCount(3, $data['data']);
        $this->assertEquals(5, $data['meta']['total']);
        $this->assertEquals(0, $data['meta']['offset']);
        $this->assertEquals(3, $data['meta']['limit']);
    }

    public function test_list_notifications_filters_by_type(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->createNotification(['notification_type' => 'transaction.received']);
        $this->createNotification(['notification_type' => 'transaction.sent']);
        $this->createNotification(['notification_type' => 'security.login']);

        $response = $this->getJson('/api/v1/notifications?type=transaction')
            ->assertOk();

        $data = $response->json();
        $this->assertCount(2, $data['data']);
        foreach ($data['data'] as $notification) {
            $this->assertEquals('transaction', $notification['type']);
        }
    }

    public function test_list_notifications_maps_types_correctly(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->createNotification(['notification_type' => 'transaction.received']);
        $this->createNotification(['notification_type' => 'security.login']);
        $this->createNotification(['notification_type' => 'general']);
        $this->createNotification(['notification_type' => 'price.alert']);

        $response = $this->getJson('/api/v1/notifications')
            ->assertOk();

        $types = collect($response->json('data'))->pluck('type')->sort()->values()->all();
        $this->assertEquals(['promo', 'security', 'system', 'transaction'], $types);
    }

    public function test_show_notification_returns_single(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $notification = $this->createNotification(['title' => 'Specific One']);

        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Specific One')
            ->assertJsonPath('data.read', false);
    }

    public function test_show_notification_returns_404_for_other_user(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $otherUser = User::factory()->create();
        $notification = $this->createNotification(['user_id' => $otherUser->id]);

        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(404);
    }

    public function test_mark_read_marks_notification_as_read(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $notification = $this->createNotification();
        $this->assertNull($notification->read_at);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true)
            ->assertJsonPath('message', 'Notification marked as read');

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_all_read_marks_all_as_read(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $this->createNotification();
        $this->createNotification();
        $this->createNotification(['read_at' => now(), 'status' => 'read']);

        $response = $this->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertEquals(2, $response->json('data.count'));
    }

    public function test_unread_count_returns_correct_count(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $this->createNotification();
        $this->createNotification();
        $this->createNotification(['read_at' => now(), 'status' => 'read']);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_list_notifications_default_limit_is_20(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        for ($i = 0; $i < 25; $i++) {
            $this->createNotification();
        }

        $response = $this->getJson('/api/v1/notifications')
            ->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertEquals(25, $response->json('meta.total'));
    }
}
