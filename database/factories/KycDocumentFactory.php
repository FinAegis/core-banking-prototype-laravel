<?php

namespace Database\Factories;

use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KycDocument>
 */
class KycDocumentFactory extends Factory
{
    protected $model = KycDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentTypes = ['passport', 'national_id', 'drivers_license', 'utility_bill', 'bank_statement', 'selfie'];
        $statuses = ['pending', 'verified', 'rejected', 'expired'];
        
        return [
            'user_uuid' => User::factory(),
            'document_type' => $this->faker->randomElement($documentTypes),
            'status' => $this->faker->randomElement($statuses),
            'file_path' => 'kyc/' . $this->faker->uuid . '/' . $this->faker->word . '.' . $this->faker->randomElement(['pdf', 'jpg', 'png']),
            'file_hash' => $this->faker->sha256,
            'metadata' => [
                'original_name' => $this->faker->word . '.' . $this->faker->fileExtension,
                'mime_type' => $this->faker->mimeType,
                'size' => $this->faker->numberBetween(100000, 5000000),
            ],
            'uploaded_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the document is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'verified_at' => null,
            'verified_by' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the document is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
            'verified_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'verified_by' => 'admin-' . $this->faker->uuid,
            'rejection_reason' => null,
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
        ]);
    }

    /**
     * Indicate that the document is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'verified_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'verified_by' => 'admin-' . $this->faker->uuid,
            'rejection_reason' => $this->faker->randomElement([
                'Document is blurry or unreadable',
                'Document appears to be altered',
                'Document has expired',
                'Wrong document type uploaded',
                'Name mismatch with account',
            ]),
        ]);
    }
}