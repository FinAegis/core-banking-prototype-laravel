<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_user_endpoint_returns_authenticated_user()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
    }

    public function test_user_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_user_endpoint_structure()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'uuid',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]);
    }
}