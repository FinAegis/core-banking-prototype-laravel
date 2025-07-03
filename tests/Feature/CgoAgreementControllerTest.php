<?php

namespace Tests\Feature;

use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\User;
use App\Services\Cgo\InvestmentAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class CgoAgreementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_generate_agreement_requires_authentication()
    {
        $investment = CgoInvestment::factory()->create();
        
        $response = $this->postJson(route('cgo.agreement.generate', $investment->uuid));
        
        $response->assertUnauthorized();
    }

    public function test_generate_agreement_creates_new_agreement()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
        
        // Mock the service
        $serviceMock = Mockery::mock(InvestmentAgreementService::class);
        $serviceMock->shouldReceive('generateAgreement')
            ->once()
            ->with(Mockery::type(CgoInvestment::class))
            ->andReturn('cgo/agreements/test.pdf');
        
        $this->app->instance(InvestmentAgreementService::class, $serviceMock);
        
        $response = $this->actingAs($user)
            ->postJson(route('cgo.agreement.generate', $investment->uuid));
        
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Agreement generated successfully',
            ])
            ->assertJsonStructure(['download_url']);
    }

    public function test_generate_agreement_returns_existing_if_already_generated()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'agreement_path' => 'cgo/agreements/existing.pdf',
        ]);
        
        // Create fake file
        Storage::put('cgo/agreements/existing.pdf', 'existing content');
        
        $response = $this->actingAs($user)
            ->postJson(route('cgo.agreement.generate', $investment->uuid));
        
        // For JSON requests, should return success with download URL
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Agreement already exists',
            ])
            ->assertJsonStructure(['download_url']);
    }

    public function test_download_agreement_requires_ownership()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $otherUser->id,
            'agreement_path' => 'cgo/agreements/test.pdf',
        ]);
        
        $response = $this->actingAs($user)
            ->get(route('cgo.agreement.download', $investment->uuid));
        
        $response->assertNotFound();
    }

    public function test_download_agreement_returns_pdf()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'agreement_path' => 'cgo/agreements/test.pdf',
        ]);
        
        // Create fake file
        Storage::put('cgo/agreements/test.pdf', 'pdf content');
        
        $response = $this->actingAs($user)
            ->get(route('cgo.agreement.download', $investment->uuid));
        
        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=Investment_Agreement_' . $investment->uuid . '.pdf');
    }

    public function test_generate_certificate_requires_confirmed_investment()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        
        $response = $this->actingAs($user)
            ->postJson(route('cgo.certificate.generate', $investment->uuid));
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Certificate can only be generated for confirmed investments',
            ]);
    }

    public function test_generate_certificate_creates_certificate()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
        
        // Mock the service
        $serviceMock = Mockery::mock(InvestmentAgreementService::class);
        $serviceMock->shouldReceive('generateCertificate')
            ->once()
            ->with(Mockery::type(CgoInvestment::class))
            ->andReturn('cgo/certificates/test.pdf');
        
        $this->app->instance(InvestmentAgreementService::class, $serviceMock);
        
        $response = $this->actingAs($user)
            ->postJson(route('cgo.certificate.generate', $investment->uuid));
        
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Certificate generated successfully',
            ])
            ->assertJsonStructure(['download_url']);
    }

    public function test_mark_as_signed_requires_agreement_to_exist()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
        ]);
        
        $response = $this->actingAs($user)
            ->postJson(route('cgo.agreement.sign', $investment->uuid), [
                'signature_data' => 'base64_encoded_signature',
            ]);
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Agreement must be generated first',
            ]);
    }

    public function test_mark_as_signed_updates_investment()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'agreement_generated_at' => now()->subHour(),
        ]);
        
        $response = $this->actingAs($user)
            ->postJson(route('cgo.agreement.sign', $investment->uuid), [
                'signature_data' => 'base64_encoded_signature',
            ]);
        
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Agreement marked as signed',
            ]);
        
        $investment->refresh();
        $this->assertNotNull($investment->agreement_signed_at);
        $this->assertEquals('base64_encoded_signature', $investment->metadata['signature_data']);
    }

    public function test_preview_agreement_requires_admin_role()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'agreement_path' => 'cgo/agreements/test.pdf',
        ]);
        
        Storage::put('cgo/agreements/test.pdf', 'pdf content');
        
        $response = $this->actingAs($user)
            ->get(route('cgo.agreement.preview', $investment->uuid));
        
        $response->assertForbidden();
    }

    public function test_preview_agreement_shows_pdf_inline()
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        
        $investment = CgoInvestment::factory()->create([
            'agreement_path' => 'cgo/agreements/test.pdf',
        ]);
        
        Storage::put('cgo/agreements/test.pdf', 'pdf content');
        
        $response = $this->actingAs($admin)
            ->get(route('cgo.agreement.preview', $investment->uuid));
        
        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="agreement_preview.pdf"');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}