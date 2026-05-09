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
        ];
    }
}
