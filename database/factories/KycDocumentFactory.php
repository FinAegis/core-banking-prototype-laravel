<?php

namespace Database\Factories;

use App\Domain\Compliance\Models\KycDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\KycDocument>
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
            'user_uuid'     => User::factory(),
            'document_type' => fake()->randomElement($documentTypes),
            'status'        => fake()->randomElement($statuses),
            'file_path'     => 'kyc/' . fake()->uuid . '/' . fake()->word . '.' . fake()->randomElement(['pdf', 'jpg', 'png']),
            'file_hash'     => fake()->sha256,
            'metadata'      => [
                'original_name' => fake()->word . '.' . fake()->fileExtension,
                'mime_type'     => fake()->mimeType,
                'size'          => fake()->numberBetween(100000, 5000000),
            ],
            'uploaded_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the document is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'pending',
            'verified_at'      => null,
            'verified_by'      => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the document is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'verified',
            'verified_at'      => fake()->dateTimeBetween('-1 week', 'now'),
            'verified_by'      => 'admin-' . fake()->uuid,
            'rejection_reason' => null,
            'expires_at'       => fake()->optional()->dateTimeBetween('now', '+2 years'),
        ]);
    }

    /**
     * Indicate that the document is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'rejected',
            'verified_at'      => fake()->dateTimeBetween('-1 week', 'now'),
            'verified_by'      => 'admin-' . fake()->uuid,
            'rejection_reason' => fake()->randomElement([
                'Document is blurry or unreadable',
                'Document appears to be altered',
                'Document has expired',
                'Wrong document type uploaded',
                'Name mismatch with account',
            ]),
        ]);
    }
}
