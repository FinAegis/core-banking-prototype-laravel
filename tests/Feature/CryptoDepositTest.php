<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CryptoDepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with team
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($this->user);
    }

    public function test_crypto_deposit_page_loads_successfully()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        $response->assertSee('Cryptocurrency Deposit');
        
        // Check all crypto options are present
        $response->assertSee('Bitcoin (BTC)');
        $response->assertSee('Ethereum (ETH)');
        $response->assertSee('Tether (USDT)');
        
        // Check network information
        $response->assertSee('Network: Bitcoin');
        $response->assertSee('Network: ERC-20');
        $response->assertSee('Network: TRC-20');
    }
    
    public function test_crypto_deposit_page_includes_qr_code_functionality()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        
        // Should include QR code library
        $response->assertSee('qrcode@1.5.3', false);
        
        // Should have QR code generation function
        $response->assertSee('generateQRCode', false);
        $response->assertSee('QRCode.toCanvas', false);
        
        // Should not have placeholder text
        $response->assertDontSee('QR Code Placeholder');
    }
    
    public function test_crypto_deposit_page_has_copy_functionality()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        
        // Check copy functionality exists
        $response->assertSee('copyAddress()', false);
        $response->assertSee('navigator.clipboard', false);
        $response->assertSee('Copy');
        
        // Check feedback mechanism
        $response->assertSee('Copied!');
        $response->assertSee('showCopyFeedback', false);
    }
    
    public function test_crypto_deposit_page_has_correct_addresses()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        
        // Check BTC address
        $response->assertSee('1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', false);
        
        // Check ETH address
        $response->assertSee('0x742d35Cc6634C0532925a3b844Bc9e7595f06789', false);
        
        // Check USDT address
        $response->assertSee('TN3W4H6rK2UM6GnKms9iFGQfVY73Gmwm7T', false);
    }
    
    public function test_crypto_deposit_page_shows_important_notices()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        
        // Check important notice section
        $response->assertSee('Important Notice');
        $response->assertSee('Send only');
        $response->assertSee('Minimum deposit:');
        $response->assertSee('network confirmations');
        $response->assertSee('Processing time: 10-60 minutes');
    }
    
    public function test_crypto_deposit_page_has_back_button()
    {
        $response = $this->get('/wallet/deposit/crypto');
        
        $response->assertStatus(200);
        
        // Check back button exists
        $response->assertSee('Back to Deposit Options');
        $response->assertSee(route('wallet.deposit'));
    }
}