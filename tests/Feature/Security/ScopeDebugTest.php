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
        $canRead = $user->tokenCan('read');
        $canWrite = $user->tokenCan('write');
        $canNonsense = $user->tokenCan('nonsense');

        echo "tokenCan('read'): " . ($canRead ? 'true' : 'false') . "\n";
        echo "tokenCan('write'): " . ($canWrite ? 'true' : 'false') . "\n";
        echo "tokenCan('nonsense'): " . ($canNonsense ? 'true' : 'false') . "\n";
        $token = $user->currentAccessToken();
        echo 'currentAccessToken exists: ' . ($token ? 'yes' : 'no') . "\n";

        // When no abilities are specified, the user should NOT have any specific abilities
        $this->assertFalse($canRead, 'User should not have read ability when no abilities specified');
        $this->assertFalse($canWrite, 'User should not have write ability when no abilities specified');
        $this->assertFalse($canNonsense, 'User should not have nonsense ability when no abilities specified');
        $this->assertNotNull($token, 'Current access token should exist');

        // Test with explicit abilities
        Sanctum::actingAs($user, ['read', 'write']);

        echo "\n=== Test 2: Sanctum::actingAs with ['read', 'write'] ===\n";
        $canRead = $user->tokenCan('read');
        $canWrite = $user->tokenCan('write');
        $canDelete = $user->tokenCan('delete');
        $canNonsense = $user->tokenCan('nonsense');

        echo "tokenCan('read'): " . ($canRead ? 'true' : 'false') . "\n";
        echo "tokenCan('write'): " . ($canWrite ? 'true' : 'false') . "\n";
        echo "tokenCan('delete'): " . ($canDelete ? 'true' : 'false') . "\n";
        echo "tokenCan('nonsense'): " . ($canNonsense ? 'true' : 'false') . "\n";

        // When specific abilities are provided, only those should be available
        $this->assertTrue($canRead, 'User should have read ability');
        $this->assertTrue($canWrite, 'User should have write ability');
        $this->assertFalse($canDelete, 'User should not have delete ability');
        $this->assertFalse($canNonsense, 'User should not have nonsense ability');
    }
}
