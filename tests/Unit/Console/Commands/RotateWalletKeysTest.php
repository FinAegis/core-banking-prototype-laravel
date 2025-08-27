<?php

namespace Tests\Unit\Console\Commands;

use App\Domain\Wallet\Models\SecureKeyStorage;
use App\Domain\Wallet\Services\SecureKeyStorageService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RotateWalletKeysTest extends TestCase
{
    use RefreshDatabase;

    private SecureKeyStorageService $keyStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyStorage = $this->mock(SecureKeyStorageService::class);
        $this->app->instance(SecureKeyStorageService::class, $this->keyStorage);
    }

    public function test_command_requires_wallet_ids_or_all_flag(): void
    {
        // Act & Assert
        $this->artisan('wallet:rotate-keys')
            ->expectsOutput('Please specify wallet IDs with --wallet or use --all flag')
            ->assertExitCode(1);
    }

    public function test_rotate_specific_wallets_with_confirmation(): void
    {
        // Arrange
        $walletIds = ['wallet-123', 'wallet-456'];

        $this->keyStorage->shouldReceive('rotateKeys')
            ->with('wallet-123', 'system', 'Scheduled key rotation')
            ->once();

        $this->keyStorage->shouldReceive('rotateKeys')
            ->with('wallet-456', 'system', 'Scheduled key rotation')
            ->once();

        $this->keyStorage->shouldReceive('purgeExpiredKeys')
            ->once()
            ->andReturn(0);

        // Act
        $this->artisan('wallet:rotate-keys', [
            '--wallet' => $walletIds,
        ])
            ->expectsOutput('Found 2 wallet(s) for key rotation')
            ->expectsConfirmation('Are you sure you want to rotate keys for 2 wallet(s)?', 'yes')
            ->expectsOutputToContain('✓ Rotated keys for wallet: wallet-123')
            ->expectsOutputToContain('✓ Rotated keys for wallet: wallet-456')
            ->expectsOutput('Key rotation completed:')
            ->expectsOutput('  Successful: 2')
            ->assertExitCode(0);
    }

    public function test_rotate_all_wallets_with_force_flag(): void
    {
        // Arrange
        SecureKeyStorage::create([
            'wallet_id'      => 'wallet-123',
            'encrypted_data' => 'data',
            'auth_tag'       => 'tag',
            'iv'             => 'iv',
            'salt'           => 'salt',
            'key_version'    => 1,
            'storage_type'   => 'database',
            'is_active'      => true,
            'metadata'       => [],
        ]);

        SecureKeyStorage::create([
            'wallet_id'      => 'wallet-456',
            'encrypted_data' => 'data',
            'auth_tag'       => 'tag',
            'iv'             => 'iv',
            'salt'           => 'salt',
            'key_version'    => 1,
            'storage_type'   => 'database',
            'is_active'      => true,
            'metadata'       => [],
        ]);

        $this->keyStorage->shouldReceive('rotateKeys')
            ->with('wallet-123', 'system', 'Scheduled key rotation')
            ->once();

        $this->keyStorage->shouldReceive('rotateKeys')
            ->with('wallet-456', 'system', 'Scheduled key rotation')
            ->once();

        $this->keyStorage->shouldReceive('purgeExpiredKeys')
            ->once()
            ->andReturn(3);

        // Act
        $this->artisan('wallet:rotate-keys', [
            '--all'   => true,
            '--force' => true,
        ])
            ->expectsOutput('Found 2 wallet(s) for key rotation')
            ->expectsOutputToContain('✓ Rotated keys for wallet: wallet-123')
            ->expectsOutputToContain('✓ Rotated keys for wallet: wallet-456')
            ->expectsOutput('Key rotation completed:')
            ->expectsOutput('  Successful: 2')
            ->expectsOutput('  Purged 3 expired temporary keys')
            ->assertExitCode(0);
    }

    public function test_handle_rotation_failure_gracefully(): void
    {
        // Arrange
        SecureKeyStorage::create([
            'wallet_id'      => 'wallet-123',
            'encrypted_data' => 'data',
            'auth_tag'       => 'tag',
            'iv'             => 'iv',
            'salt'           => 'salt',
            'key_version'    => 1,
            'storage_type'   => 'database',
            'is_active'      => true,
            'metadata'       => [],
        ]);

        SecureKeyStorage::create([
            'wallet_id'      => 'wallet-456',
            'encrypted_data' => 'data',
            'auth_tag'       => 'tag',
            'iv'             => 'iv',
            'salt'           => 'salt',
            'key_version'    => 1,
            'storage_type'   => 'database',
            'is_active'      => true,
            'metadata'       => [],
        ]);

        $this->keyStorage->shouldReceive('rotateKeys')
            ->with('wallet-123', 'system', 'Scheduled key rotation')
            ->once()
            ->andThrow(new Exception('Rotation failed'));

        $this->keyStorage->shouldReceive('rotateKeys')
            ->with('wallet-456', 'system', 'Scheduled key rotation')
            ->once();

        $this->keyStorage->shouldReceive('purgeExpiredKeys')
            ->once()
            ->andReturn(0);

        Log::spy();

        // Act
        $this->artisan('wallet:rotate-keys', [
            '--all'   => true,
            '--force' => true,
        ])
            ->expectsOutput('Found 2 wallet(s) for key rotation')
            ->expectsOutputToContain('✗ Failed to rotate keys for wallet: wallet-123')
            ->expectsOutput('  Error: Rotation failed')
            ->expectsOutputToContain('✓ Rotated keys for wallet: wallet-456')
            ->expectsOutput('Key rotation completed:')
            ->expectsOutput('  Successful: 1')
            ->expectsOutput('  Failed: 1')
            ->assertExitCode(1);

        // Verify error was logged
        Log::shouldHaveReceived('error')
            ->with('Key rotation failed', Mockery::on(function ($context) {
                return $context['wallet_id'] === 'wallet-123'
                    && $context['error'] === 'Rotation failed'
                    && isset($context['trace']);
            }));
    }

    public function test_custom_rotation_reason(): void
    {
        // Arrange
        $walletId = 'wallet-123';
        $customReason = 'Security audit requirement';

        $this->keyStorage->shouldReceive('rotateKeys')
            ->with($walletId, 'system', $customReason)
            ->once();

        $this->keyStorage->shouldReceive('purgeExpiredKeys')
            ->once()
            ->andReturn(0);

        // Act
        $this->artisan('wallet:rotate-keys', [
            '--wallet' => [$walletId],
            '--force'  => true,
            '--reason' => $customReason,
        ])
            ->expectsOutput('Found 1 wallet(s) for key rotation')
            ->expectsOutputToContain("✓ Rotated keys for wallet: {$walletId}")
            ->expectsOutput('Key rotation completed:')
            ->expectsOutput('  Successful: 1')
            ->assertExitCode(0);
    }

    public function test_user_cancels_rotation(): void
    {
        // Arrange
        $walletIds = ['wallet-123'];

        // Act
        $this->artisan('wallet:rotate-keys', [
            '--wallet' => $walletIds,
        ])
            ->expectsOutput('Found 1 wallet(s) for key rotation')
            ->expectsConfirmation('Are you sure you want to rotate keys for 1 wallet(s)?', 'no')
            ->expectsOutput('Key rotation cancelled')
            ->assertExitCode(0);
    }

    public function test_no_wallets_found_for_rotation(): void
    {
        // Arrange - No wallets in database

        // Act
        $this->artisan('wallet:rotate-keys', [
            '--all'   => true,
            '--force' => true,
        ])
            ->expectsOutput('No wallets found for key rotation')
            ->assertExitCode(0);
    }

    public function test_progress_bar_displays_correctly(): void
    {
        // Arrange
        $walletIds = ['wallet-1', 'wallet-2', 'wallet-3'];

        SecureKeyStorage::create([
            'wallet_id'      => 'wallet-1',
            'encrypted_data' => 'data',
            'auth_tag'       => 'tag',
            'iv'             => 'iv',
            'salt'           => 'salt',
            'key_version'    => 1,
            'storage_type'   => 'database',
            'is_active'      => true,
            'metadata'       => [],
        ]);

        SecureKeyStorage::create([
            'wallet_id'      => 'wallet-2',
            'encrypted_data' => 'data',
            'auth_tag'       => 'tag',
            'iv'             => 'iv',
            'salt'           => 'salt',
            'key_version'    => 1,
            'storage_type'   => 'database',
            'is_active'      => true,
            'metadata'       => [],
        ]);

        SecureKeyStorage::create([
            'wallet_id'      => 'wallet-3',
            'encrypted_data' => 'data',
            'auth_tag'       => 'tag',
            'iv'             => 'iv',
            'salt'           => 'salt',
            'key_version'    => 1,
            'storage_type'   => 'database',
            'is_active'      => true,
            'metadata'       => [],
        ]);

        foreach ($walletIds as $walletId) {
            $this->keyStorage->shouldReceive('rotateKeys')
                ->with($walletId, 'system', 'Scheduled key rotation')
                ->once();
        }

        $this->keyStorage->shouldReceive('purgeExpiredKeys')
            ->once()
            ->andReturn(0);

        // Act
        $this->artisan('wallet:rotate-keys', [
            '--all'   => true,
            '--force' => true,
        ])
            ->expectsOutput('Found 3 wallet(s) for key rotation')
            ->assertExitCode(0);
    }
}
