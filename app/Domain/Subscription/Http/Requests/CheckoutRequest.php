<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read array{
 *     given: bool,
 *     shownAt: string,
 *     acceptedAt: string,
 *     consentText: string,
 *     version: int
 * }|null $withdrawalConsent
 */
final class CheckoutRequest extends FormRequest
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
            'plan'                          => ['required', 'string', 'in:monthly_pro,annual_pro'],
            'withdrawalConsent'             => ['required', 'array'],
            'withdrawalConsent.given'       => ['required', 'boolean', 'accepted'],
            'withdrawalConsent.shownAt'     => ['required', 'string'],
            'withdrawalConsent.acceptedAt'  => ['required', 'string'],
            'withdrawalConsent.consentText' => ['required', 'string', 'min:1'],
            'withdrawalConsent.version'     => ['required', 'integer', 'min:1'],
            'successUrl'                    => ['nullable', 'url'],
            'cancelUrl'                     => ['nullable', 'url'],
            // Slice 3 expansion: optional quoteId to lock displayed price at checkout.
            // Nullable + uuid format. quoteId absent = proceed without a locked quote
            // (back-compat, v1.3.0). Will be required in v1.3.1 (OD-6 decision: keep
            // optional to avoid deploy-ordering dependency on slice 3).
            'quoteId' => ['nullable', 'string', 'uuid'],
        ];
    }
}
