<?php

namespace Database\Factories\Domain\FinancialInstitution\Models;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication>
 */
class FinancialInstitutionApplicationFactory extends Factory
{
    protected $model = FinancialInstitutionApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $institutionType = $this->faker->randomElement(array_keys(FinancialInstitutionApplication::INSTITUTION_TYPES));

        return [
            'application_number'        => 'FIA-' . date('Y') . '-' . str_pad($this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'institution_name'          => $this->faker->company() . ' Bank',
            'legal_name'                => $this->faker->company() . ' Limited',
            'registration_number'       => $this->faker->numerify('REG########'),
            'tax_id'                    => $this->faker->numerify('TAX######'),
            'country'                   => $this->faker->countryCode(),
            'institution_type'          => $institutionType,
            'assets_under_management'   => $this->faker->numberBetween(1000000, 10000000000),
            'years_in_operation'        => $this->faker->numberBetween(1, 50),
            'primary_regulator'         => $this->faker->randomElement(['FCA', 'SEC', 'BaFin', 'ASIC']),
            'regulatory_license_number' => $this->faker->numerify('LIC######'),

            // Contact Information
            'contact_name'       => $this->faker->name(),
            'contact_email'      => $this->faker->companyEmail(),
            'contact_phone'      => $this->faker->phoneNumber(),
            'contact_position'   => $this->faker->randomElement(['CEO', 'CFO', 'CCO', 'CTO']),
            'contact_department' => $this->faker->randomElement(['Executive', 'Compliance', 'Operations']),

            // Address Information
            'headquarters_address'     => $this->faker->streetAddress(),
            'headquarters_city'        => $this->faker->city(),
            'headquarters_state'       => $this->faker->state(),
            'headquarters_postal_code' => $this->faker->postcode(),
            'headquarters_country'     => $this->faker->countryCode(),

            // Business Information
            'business_description'          => $this->faker->paragraph(5),
            'target_markets'                => [$this->faker->countryCode(), $this->faker->countryCode()],
            'product_offerings'             => ['Deposits', 'Lending', 'Payments'],
            'expected_monthly_transactions' => $this->faker->numberBetween(100, 100000),
            'expected_monthly_volume'       => $this->faker->numberBetween(100000, 1000000000),
            'required_currencies'           => ['USD', 'EUR', 'GBP'],

            // Technical Requirements
            'integration_requirements' => ['API', 'Webhooks'],
            'requires_api_access'      => true,
            'requires_webhooks'        => true,
            'requires_reporting'       => $this->faker->boolean(),
            'security_certifications'  => ['ISO27001'],

            // Compliance Information
            'has_aml_program'            => true,
            'has_kyc_procedures'         => true,
            'has_data_protection_policy' => true,
            'is_pci_compliant'           => $this->faker->boolean(),
            'is_gdpr_compliant'          => true,
            'compliance_certifications'  => ['FCA', 'PRA'],

            // Status and Review
            'status'              => FinancialInstitutionApplication::STATUS_PENDING,
            'review_stage'        => FinancialInstitutionApplication::STAGE_INITIAL,
            'risk_rating'         => FinancialInstitutionApplication::RISK_LOW,
            'risk_factors'        => [],
            'risk_score'          => $this->faker->numberBetween(0, 100),
            'required_documents'  => [],
            'submitted_documents' => [],
            'documents_verified'  => false,

            // Access Flags
            'sandbox_access_granted'    => false,
            'production_access_granted' => false,

            // Metadata
            'metadata' => [],
            'source'   => 'web',
        ];
    }

    /**
     * Indicate that the application is under review.
     */
    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => FinancialInstitutionApplication::STATUS_UNDER_REVIEW,
            'review_stage' => FinancialInstitutionApplication::STAGE_COMPLIANCE,
        ]);
    }

    /**
     * Indicate that the application is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'                    => FinancialInstitutionApplication::STATUS_APPROVED,
            'review_stage'              => FinancialInstitutionApplication::STAGE_FINAL,
            'reviewed_at'               => now(),
            'sandbox_access_granted'    => true,
            'production_access_granted' => false,
            'onboarding_completed_at'   => now(),
        ]);
    }

    /**
     * Indicate that the application is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => FinancialInstitutionApplication::STATUS_REJECTED,
            'reviewed_at'      => now(),
            'rejection_reason' => 'Failed compliance checks',
        ]);
    }

    /**
     * Indicate that the application is on hold.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => FinancialInstitutionApplication::STATUS_ON_HOLD,
            'review_notes' => 'Additional documentation required',
        ]);
    }

    /**
     * Indicate that the application is pending review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FinancialInstitutionApplication::STATUS_PENDING,
        ]);
    }
}
