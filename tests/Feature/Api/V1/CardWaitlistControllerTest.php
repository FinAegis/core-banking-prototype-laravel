<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\CardIssuance\Models\CardWaitlist;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CardWaitlistControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_join_waitlist_successfully(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/v1/cards/waitlist');

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'position', 'joinedAt'])
            ->assertJsonPath('position', 1);

        $id = $response->json('id');
        $this->assertStringStartsWith('wl_', $id);

        $this->assertDatabaseHas('card_waitlist', [
            'user_id'  => $this->user->id,
            'position' => 1,
        ]);
    }

    public function test_join_waitlist_returns_409_if_already_joined(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        CardWaitlist::create([
            'user_id'   => $this->user->id,
            'position'  => 1,
            'joined_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/cards/waitlist');

        $response->assertStatus(409)
            ->assertJsonPath('position', 1);

        $this->assertStringStartsWith('wl_', $response->json('id'));
    }

    public function test_status_returns_joined_true_for_enrolled_user(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        CardWaitlist::create([
            'user_id'   => $this->user->id,
            'position'  => 5,
            'joined_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/cards/waitlist/status');

        $response->assertOk()
            ->assertJsonPath('joined', true)
            ->assertJsonPath('position', 5);

        $this->assertNotNull($response->json('joinedAt'));
    }

    public function test_status_returns_joined_false_for_new_user(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/v1/cards/waitlist/status');

        $response->assertOk()
            ->assertJsonPath('joined', false)
            ->assertJsonPath('position', null)
            ->assertJsonPath('joinedAt', null);
    }
}
