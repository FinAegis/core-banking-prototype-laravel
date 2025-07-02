<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FinancialInstitutionApplicationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_display_the_application_page()
    {
        $response = $this->get('/financial-institutions/apply');

        $response->assertStatus(200);
        $response->assertSee('Partner Institution Application');
        $response->assertSee('Partnership Requirements');
        $response->assertSee('Technical Requirements');
        $response->assertSee('Juridical Requirements');
        $response->assertSee('Financial Requirements');
        $response->assertSee('Insurance Requirements');
    }

    /** @test */
    public function it_can_submit_a_valid_application()
    {
        $response = $this->post('/financial-institutions/submit', [
            'institution_name' => 'Test Bank Ltd',
            'country' => 'Germany',
            'contact_name' => 'John Doe',
            'contact_email' => 'john.doe@testbank.com',
            'technical_capabilities' => 'We have modern REST APIs with OAuth 2.0 authentication and 99.99% uptime',
            'regulatory_compliance' => 'Fully licensed by BaFin with EU passporting rights and compliant with all regulations',
            'financial_strength' => '€500M assets under management with A+ credit rating from major agencies',
            'insurance_coverage' => 'Complete deposit insurance coverage through German deposit protection scheme',
            'partnership_vision' => 'We aim to provide seamless banking services to FinAegis users',
            'terms' => '1',
        ]);

        $response->assertRedirect('/financial-institutions/apply');
        $response->assertSessionHas('success', 'Thank you for your application. We will review it and contact you soon.');
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->post('/financial-institutions/submit', []);

        $response->assertSessionHasErrors([
            'institution_name',
            'country',
            'contact_name',
            'contact_email',
            'technical_capabilities',
            'regulatory_compliance',
            'financial_strength',
            'insurance_coverage',
            'terms',
        ]);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $response = $this->post('/financial-institutions/submit', [
            'institution_name' => 'Test Bank',
            'country' => 'Germany',
            'contact_name' => 'John Doe',
            'contact_email' => 'invalid-email',
            'technical_capabilities' => 'We have modern REST APIs with OAuth 2.0 authentication and 99.99% uptime',
            'regulatory_compliance' => 'Fully licensed by BaFin with EU passporting rights and compliant with all regulations',
            'financial_strength' => '€500M assets under management with A+ credit rating from major agencies',
            'insurance_coverage' => 'Complete deposit insurance coverage through German deposit protection scheme',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors(['contact_email']);
    }

    /** @test */
    public function it_validates_minimum_text_length()
    {
        $response = $this->post('/financial-institutions/submit', [
            'institution_name' => 'Test Bank',
            'country' => 'Germany',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'technical_capabilities' => 'Too short',
            'regulatory_compliance' => 'Too short',
            'financial_strength' => 'Too short',
            'insurance_coverage' => 'Too short',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors([
            'technical_capabilities',
            'regulatory_compliance',
            'financial_strength',
            'insurance_coverage',
        ]);
    }

    /** @test */
    public function it_requires_terms_acceptance()
    {
        $response = $this->post('/financial-institutions/submit', [
            'institution_name' => 'Test Bank Ltd',
            'country' => 'Germany',
            'contact_name' => 'John Doe',
            'contact_email' => 'john.doe@testbank.com',
            'technical_capabilities' => 'We have modern REST APIs with OAuth 2.0 authentication and 99.99% uptime',
            'regulatory_compliance' => 'Fully licensed by BaFin with EU passporting rights and compliant with all regulations',
            'financial_strength' => '€500M assets under management with A+ credit rating from major agencies',
            'insurance_coverage' => 'Complete deposit insurance coverage through German deposit protection scheme',
            // 'terms' => not provided
        ]);

        $response->assertSessionHasErrors(['terms']);
        $response->assertSessionHasErrorsIn('default', ['terms' => 'Please accept the terms and conditions.']);
    }
}