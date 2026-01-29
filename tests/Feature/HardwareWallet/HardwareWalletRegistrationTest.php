<?php

declare(strict_types=1);

namespace Tests\Feature\HardwareWallet;

use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for Hardware Wallet Registration.
 *
 * Tests the complete registration flow for hardware wallets
 * via the API endpoints.
 */
class HardwareWalletRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function it_registers_ledger_device(): void
    {
        $response = $this->postJson('/api/hardware-wallet/register', [
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_' . time(),
            'device_label'     => 'My Ledger Nano X',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon', 'bsc'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'association_id',
                'device_type',
                'address',
                'chain',
            ],
        ]);

        $this->assertDatabaseHas('hardware_wallet_associations', [
            'user_id'     => $this->user->id,
            'device_type' => 'ledger_nano_x',
            'chain'       => 'ethereum',
            'is_active'   => true,
        ]);
    }

    #[Test]
    public function it_registers_trezor_device(): void
    {
        $response = $this->postJson('/api/hardware-wallet/register', [
            'device_type'      => 'trezor_model_t',
            'device_id'        => 'trezor_' . time(),
            'device_label'     => 'My Trezor Model T',
            'public_key'       => '04' . str_repeat('cd', 64),
            'address'          => '0x0987654321098765432109876543210987654321',
            'chain'            => 'polygon',
            'derivation_path'  => "m/44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.device_type', 'trezor_model_t');
    }

    #[Test]
    public function it_rejects_invalid_device_type(): void
    {
        $response = $this->postJson('/api/hardware-wallet/register', [
            'device_type'      => 'invalid_device',
            'device_id'        => 'device_123',
            'device_label'     => 'Invalid Device',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_invalid_ethereum_address(): void
    {
        $response = $this->postJson('/api/hardware-wallet/register', [
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_' . time(),
            'device_label'     => 'My Ledger',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => 'invalid-address',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        // Clear authentication
        Sanctum::actingAs(User::factory()->create());

        // Now test without auth by making a raw request
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/hardware-wallet/register', [
            'device_type' => 'ledger_nano_x',
            'device_id'   => 'ledger_123',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_lists_user_associations(): void
    {
        HardwareWalletAssociation::create([
            'user_id'         => $this->user->id,
            'device_type'     => 'ledger_nano_x',
            'device_id'       => 'ledger_123',
            'device_label'    => 'Ledger 1',
            'public_key'      => '04' . str_repeat('ab', 64),
            'address'         => '0x1111111111111111111111111111111111111111',
            'chain'           => 'ethereum',
            'derivation_path' => "44'/60'/0'/0/0",
            'is_active'       => true,
        ]);

        HardwareWalletAssociation::create([
            'user_id'         => $this->user->id,
            'device_type'     => 'trezor_model_t',
            'device_id'       => 'trezor_456',
            'device_label'    => 'Trezor 1',
            'public_key'      => '04' . str_repeat('cd', 64),
            'address'         => '0x2222222222222222222222222222222222222222',
            'chain'           => 'polygon',
            'derivation_path' => "m/44'/60'/0'/0/0",
            'is_active'       => true,
        ]);

        $response = $this->getJson('/api/hardware-wallet/associations');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_removes_association(): void
    {
        $association = HardwareWalletAssociation::create([
            'user_id'         => $this->user->id,
            'device_type'     => 'ledger_nano_x',
            'device_id'       => 'ledger_123',
            'device_label'    => 'Ledger 1',
            'public_key'      => '04' . str_repeat('ab', 64),
            'address'         => '0x1111111111111111111111111111111111111111',
            'chain'           => 'ethereum',
            'derivation_path' => "44'/60'/0'/0/0",
            'is_active'       => true,
        ]);

        $response = $this->deleteJson('/api/hardware-wallet/associations/' . $association->id);

        $response->assertStatus(200);

        $association->refresh();
        $this->assertFalse($association->is_active);
    }

    #[Test]
    public function it_cannot_remove_other_users_association(): void
    {
        $otherUser = User::factory()->create();

        $association = HardwareWalletAssociation::create([
            'user_id'         => $otherUser->id,
            'device_type'     => 'ledger_nano_x',
            'device_id'       => 'ledger_123',
            'device_label'    => 'Ledger 1',
            'public_key'      => '04' . str_repeat('ab', 64),
            'address'         => '0x1111111111111111111111111111111111111111',
            'chain'           => 'ethereum',
            'derivation_path' => "44'/60'/0'/0/0",
            'is_active'       => true,
        ]);

        $response = $this->deleteJson('/api/hardware-wallet/associations/' . $association->id);

        $response->assertStatus(403);

        $association->refresh();
        $this->assertTrue($association->is_active);
    }

    #[Test]
    public function it_returns_supported_devices(): void
    {
        $response = $this->getJson('/api/hardware-wallet/supported');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'devices',
                'chains',
            ],
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_registration(): void
    {
        $deviceId = 'ledger_' . time();
        $address = '0x1234567890123456789012345678901234567890';

        // First registration
        $this->postJson('/api/hardware-wallet/register', [
            'device_type'      => 'ledger_nano_x',
            'device_id'        => $deviceId,
            'device_label'     => 'My Ledger',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => $address,
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum'],
        ])->assertStatus(201);

        // Duplicate registration should fail
        $response = $this->postJson('/api/hardware-wallet/register', [
            'device_type'      => 'ledger_nano_x',
            'device_id'        => $deviceId,
            'device_label'     => 'My Ledger Again',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => $address,
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum'],
        ]);

        $response->assertStatus(422);
    }
}
