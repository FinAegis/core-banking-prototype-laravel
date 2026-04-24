<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Seeds exactly one active virtual card (and supporting cardholder) for a
 * review/demo account.
 *
 * Review-account seeding shortcut — bypasses the CardProvisioningService /
 * external-issuer adapter pipeline (Rain/Marqeta API calls). Writes directly
 * to the cardholders + cards tables. Safe because the reviewer bypass flag
 * gates any spend via these rows, and the `issuer='review_bypass'` tag makes
 * the origin traceable in audit logs.
 */
class CardSeeder
{
    public function seed(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $cardholder = $this->ensureCardholder($user);
            $this->ensureCard($user, $cardholder);
        });
    }

    private function ensureCardholder(User $user): Cardholder
    {
        [$firstName, $lastName] = $this->splitName($user->name ?? 'Review User');

        return Cardholder::firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'email'             => $user->email,
                'kyc_status'        => 'verified',
                'verification_data' => ['source' => 'review_bypass'],
                'verified_at'       => now(),
            ]
        );
    }

    private function ensureCard(User $user, Cardholder $cardholder): void
    {
        $existing = Card::where('user_id', $user->id)
            ->where('issuer', 'review_bypass')
            ->first();

        if ($existing instanceof Card) {
            return;
        }

        Card::create([
            'user_id'           => $user->id,
            'cardholder_id'     => $cardholder->id,
            'issuer_card_token' => 'review-' . $user->id . '-' . bin2hex(random_bytes(8)),
            'issuer'            => 'review_bypass',
            'last4'             => str_pad((string) ($user->id % 10000), 4, '0', STR_PAD_LEFT),
            'network'           => 'visa',
            'status'            => 'active',
            'currency'          => 'USD',
            'label'             => 'Review Virtual Card',
            'metadata'          => [
                'type'   => 'virtual',
                'source' => 'review_bypass',
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);
        if ($parts === false || $parts === []) {
            return ['Review', 'User'];
        }

        return [$parts[0], $parts[1] ?? 'User'];
    }
}
