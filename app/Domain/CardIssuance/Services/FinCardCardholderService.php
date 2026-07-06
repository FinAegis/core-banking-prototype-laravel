<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\CardIssuance\Events\Broadcast\FinCardKycStatusChanged;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Support\FinCardCardholderMapper;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates FinCard cardholder KYC: creates the remote cardholder, persists
 * the local Cardholder row (mapping the Zelta user ↔ FinCard holderId), and
 * applies the two-stage approval reported by FinCard cardholder webhooks.
 *
 * The persistent Cardholder row is the bridge between our user and FinCard —
 * the issuer adapter path does not persist, so this service owns it.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §3.2, §7
 */
final class FinCardCardholderService
{
    public function __construct(
        private readonly FinCardClient $client,
    ) {
    }

    /**
     * The user's FinCard cardholder, if one has been created.
     */
    public function existingFor(User $user): ?Cardholder
    {
        return Cardholder::query()
            ->where('user_id', $user->id)
            ->whereNotNull('issuer_cardholder_id')
            ->first();
    }

    /**
     * Upload a KYC document to FinCard, returning the fileId to reference in
     * createCardholder.
     *
     * @param  array<string, mixed>  $context
     */
    public function uploadDocument(string $contents, string $filename, string $mimeType, array $context = []): string
    {
        $response = $this->client->uploadKycDocument($contents, $filename, $mimeType, $context);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return (string) ($data['fileId'] ?? $data['id'] ?? '');
    }

    /**
     * Create the FinCard cardholder and persist the local row.
     *
     * @param  array<string, mixed>  $validated  snake_case onboarding fields
     * @param  array<string, mixed>  $context    forwarded_for / platform / device_id
     */
    public function createCardholder(User $user, array $validated, array $context): Cardholder
    {
        $ip = (string) ($context['forwarded_for'] ?? '');
        $payload = FinCardCardholderMapper::toPayload($validated, $ip);

        $response = $this->client->createCardholder($payload, $context);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $holderId = (string) ($data['holderId'] ?? $data['cardholderId'] ?? '');

        return Cardholder::create([
            'user_id'                => $user->id,
            'first_name'             => (string) ($validated['first_name'] ?? ''),
            'last_name'              => (string) ($validated['last_name'] ?? ''),
            'email'                  => $validated['email'] ?? null,
            'phone'                  => $validated['phone'] ?? null,
            'kyc_status'             => 'in_review',
            'kyc_stage'              => 'admin',
            'issuer_cardholder_id'   => $holderId,
            'shipping_address_line1' => $validated['address'] ?? null,
            'shipping_city'          => $validated['city'] ?? null,
            'shipping_state'         => $validated['state'] ?? null,
            'shipping_postal_code'   => $validated['zip_code'] ?? null,
            'shipping_country'       => $validated['country'] ?? null,
            'verification_data'      => FinCardCardholderMapper::persistedAttributes($validated),
        ]);
    }

    /**
     * Apply a FinCard cardholder webhook (approval workflow) to the local row.
     *
     * @param  array<string, mixed>  $payload  decoded webhook body
     */
    public function applyKycWebhook(string $eventType, array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $holderId = (string) ($data['holderId'] ?? $data['cardholderId'] ?? $payload['holderId'] ?? '');

        if ($holderId === '') {
            Log::warning('FinCard KYC webhook without holderId', ['event' => $eventType]);

            return;
        }

        $cardholder = Cardholder::query()->where('issuer_cardholder_id', $holderId)->first();
        if (! $cardholder instanceof Cardholder) {
            Log::warning('FinCard KYC webhook for unknown cardholder', ['holder_id' => $holderId, 'event' => $eventType]);

            return;
        }

        [$status, $stage] = $this->mapEventToState($eventType);
        $cardholder->kyc_status = $status;
        $cardholder->kyc_stage = $stage;

        if ($status === 'verified') {
            $cardholder->verified_at = now();
            $cardholder->kyc_rejection_reason = null;
        } elseif ($status === 'rejected') {
            $cardholder->kyc_rejection_reason = (string) ($data['reason'] ?? $data['rejectReason'] ?? 'Rejected by issuer');
        }

        $cardholder->save();

        FinCardKycStatusChanged::dispatch(
            (int) $cardholder->user_id,
            (string) $cardholder->id,
            $status,
            $stage,
            $status === 'rejected' ? $cardholder->kyc_rejection_reason : null,
        );
    }

    /**
     * Map a FinCard cardholder event type to [kyc_status, kyc_stage].
     *
     * @return array{0: string, 1: string|null}
     */
    private function mapEventToState(string $eventType): array
    {
        return match ($eventType) {
            'wait_audit'   => ['in_review', 'admin'],
            'under_review' => ['in_review', 'channel'],
            'pass_audit'   => ['verified', 'channel'],
            'reject'       => ['rejected', null],
            default        => ['in_review', 'admin'],
        };
    }
}
