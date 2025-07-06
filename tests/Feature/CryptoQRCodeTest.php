<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CryptoQRCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with team
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($this->user);
    }

    public function test_crypto_deposit_page_includes_qr_code_library()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        
        // Should have QR code generation function (now in compiled JS)
        $response->assertSee('selectCrypto', false);
        $response->assertSee('copyAddress', false);
        
        // Should not have placeholder text
        $response->assertDontSee('QR Code Placeholder');
    }
}