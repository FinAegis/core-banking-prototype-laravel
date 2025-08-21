<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScopeDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_sanctum_acting_as(): void
    {
        $user = User::factory()->create();

        // Test with no abilities
        Sanctum::actingAs($user);

        echo "\n=== Test 1: Sanctum::actingAs without abilities ===\n";
        echo "tokenCan('read'): " . ($user->tokenCan('read') ? 'true' : 'false') . "\n";
        echo "tokenCan('write'): " . ($user->tokenCan('write') ? 'true' : 'false') . "\n";
        echo "tokenCan('nonsense'): " . ($user->tokenCan('nonsense') ? 'true' : 'false') . "\n";
        $token = $user->currentAccessToken();
        echo 'currentAccessToken exists: ' . ($token ? 'yes' : 'no') . "\n";

        // Test with explicit abilities
        Sanctum::actingAs($user, ['read', 'write']);

        echo "\n=== Test 2: Sanctum::actingAs with ['read', 'write'] ===\n";
        echo "tokenCan('read'): " . ($user->tokenCan('read') ? 'true' : 'false') . "\n";
        echo "tokenCan('write'): " . ($user->tokenCan('write') ? 'true' : 'false') . "\n";
        echo "tokenCan('delete'): " . ($user->tokenCan('delete') ? 'true' : 'false') . "\n";
        echo "tokenCan('nonsense'): " . ($user->tokenCan('nonsense') ? 'true' : 'false') . "\n";

        $this->assertTrue(true);
    }
}
