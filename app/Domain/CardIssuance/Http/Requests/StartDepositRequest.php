<?php

/**
 * StartDepositRequest — POST /api/v1/cards/waitlist/deposit body.
 *
 * The Idempotency-Key header is enforced by the idempotency.required
 * middleware applied at the route level; this FormRequest only validates
 * the JSON body shape.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-5-card-waitlist-deposit-design.md §5.1, §7.1
 */

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quoteId'                       => ['required', 'string', 'min:8', 'max:64'],
            'withdrawalConsent'             => ['nullable', 'array'],
            'withdrawalConsent.consentText' => ['nullable', 'string', 'max:4096'],
            'withdrawalConsent.consentedAt' => ['nullable', 'string', 'max:64'],
            'withdrawalConsent.version'     => ['nullable'],
            'successUrl'                    => ['nullable', 'string', 'max:2048'],
            'cancelUrl'                     => ['nullable', 'string', 'max:2048'],
        ];
    }
}
