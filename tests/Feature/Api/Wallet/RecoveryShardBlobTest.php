<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Wallet;

use App\Domain\KeyManagement\Models\RecoveryShardCloudBackup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RecoveryShardBlobTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write', 'delete'])->plainTextToken;
    }

    // --- Store with encrypted_shard ---

    public function test_store_with_encrypted_shard_saves_blob(): void
    {
        $shardData = base64_encode('encrypted-shard-binary-data');

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/recovery-shard-backup', [
                'device_id'            => 'device_001',
                'backup_provider'      => 'icloud',
                'encrypted_shard_hash' => hash('sha256', $shardData),
                'shard_version'        => 'v1',
                'encrypted_shard'      => $shardData,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device_id', 'device_001');

        // Verify the shard was stored (encrypted at rest via model cast)
        $backup = RecoveryShardCloudBackup::where('user_id', $this->user->id)
            ->where('device_id', 'device_001')
            ->first();

        $this->assertNotNull($backup);
        $this->assertEquals($shardData, $backup->encrypted_shard);
    }

    public function test_store_without_encrypted_shard_still_works(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/recovery-shard-backup', [
                'device_id'            => 'device_002',
                'backup_provider'      => 'google_drive',
                'encrypted_shard_hash' => 'sha256_hash',
                'shard_version'        => 'v1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $backup = RecoveryShardCloudBackup::where('user_id', $this->user->id)
            ->where('device_id', 'device_002')
            ->first();

        $this->assertNotNull($backup);
        $this->assertNull($backup->encrypted_shard);
    }

    public function test_store_validates_encrypted_shard_max_length(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/wallet/recovery-shard-backup', [
                'device_id'            => 'device_001',
                'backup_provider'      => 'icloud',
                'encrypted_shard_hash' => 'hash',
                'shard_version'        => 'v1',
                'encrypted_shard'      => str_repeat('x', 65536),
            ]);

        $response->assertUnprocessable();
    }

    // --- Retrieve endpoint ---

    public function test_retrieve_returns_encrypted_shard(): void
    {
        $shardData = base64_encode('my-secret-shard');

        RecoveryShardCloudBackup::create([
            'user_id'              => $this->user->id,
            'device_id'            => 'device_001',
            'backup_provider'      => 'icloud',
            'encrypted_shard_hash' => hash('sha256', $shardData),
            'shard_version'        => 'v1',
            'encrypted_shard'      => $shardData,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recovery-shard-backup/retrieve?device_id=device_001&backup_provider=icloud');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.encrypted_shard', $shardData)
            ->assertJsonPath('data.shard_version', 'v1')
            ->assertJsonPath('data.encrypted_shard_hash', hash('sha256', $shardData));
    }

    public function test_retrieve_returns_404_when_no_backup(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recovery-shard-backup/retrieve?device_id=nonexistent&backup_provider=icloud');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SHARD_NOT_FOUND');
    }

    public function test_retrieve_returns_404_when_no_shard_blob(): void
    {
        // Backup exists but without encrypted_shard (metadata-only)
        RecoveryShardCloudBackup::create([
            'user_id'              => $this->user->id,
            'device_id'            => 'device_meta',
            'backup_provider'      => 'manual',
            'encrypted_shard_hash' => 'some_hash',
            'shard_version'        => 'v1',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recovery-shard-backup/retrieve?device_id=device_meta&backup_provider=manual');

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'SHARD_NOT_FOUND');
    }

    public function test_retrieve_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/wallet/recovery-shard-backup/retrieve?device_id=d&backup_provider=icloud');

        $response->assertUnauthorized();
    }

    public function test_retrieve_validates_required_params(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recovery-shard-backup/retrieve');

        $response->assertUnprocessable();
    }

    public function test_retrieve_does_not_return_other_users_shard(): void
    {
        $otherUser = User::factory()->create();

        RecoveryShardCloudBackup::create([
            'user_id'              => $otherUser->id,
            'device_id'            => 'device_001',
            'backup_provider'      => 'icloud',
            'encrypted_shard_hash' => 'hash',
            'shard_version'        => 'v1',
            'encrypted_shard'      => base64_encode('other-user-shard'),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recovery-shard-backup/retrieve?device_id=device_001&backup_provider=icloud');

        $response->assertNotFound();
    }

    // --- $hidden test ---

    public function test_encrypted_shard_is_hidden_in_default_serialization(): void
    {
        $shardData = base64_encode('hidden-shard');

        $backup = RecoveryShardCloudBackup::create([
            'user_id'              => $this->user->id,
            'device_id'            => 'device_hidden',
            'backup_provider'      => 'icloud',
            'encrypted_shard_hash' => 'hash',
            'shard_version'        => 'v1',
            'encrypted_shard'      => $shardData,
        ]);

        $json = $backup->toArray();

        $this->assertArrayNotHasKey('encrypted_shard', $json);
    }

    // --- Show (list) endpoint does NOT leak encrypted_shard ---

    public function test_show_list_does_not_include_encrypted_shard(): void
    {
        RecoveryShardCloudBackup::create([
            'user_id'              => $this->user->id,
            'device_id'            => 'device_list',
            'backup_provider'      => 'icloud',
            'encrypted_shard_hash' => 'hash',
            'shard_version'        => 'v1',
            'encrypted_shard'      => base64_encode('should-not-appear'),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/wallet/recovery-shard-backup');

        $response->assertOk()
            ->assertJsonMissing(['encrypted_shard']);
    }

    // --- Route existence ---

    public function test_retrieve_route_exists(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertNotNull($routes->getByName('mobile.wallet.recovery-shard-backup.retrieve'));
    }
}
