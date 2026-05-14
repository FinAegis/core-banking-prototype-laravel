<?php

/**
 * IapVerifyRequest — Plan B Slice 2 validation for POST /api/v1/subscription/iap/verify.
 *
 * Wire shape per spec §5.1 / §7. `platform` field discriminates Apple vs
 * Google. `originalTransactionId` is Apple-only (StoreKit 2 stable id). All
 * keys are camelCase per project convention.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read string                            $platform
 * @property-read string                            $receipt
 * @property-read string|null                       $originalTransactionId
 * @property-read string                            $productId
 * @property-read string|null                       $appVersion
 * @property-read string                            $currency
 * @property-read array<string, mixed>|null         $withdrawalConsent
 */
final class IapVerifyRequest extends FormRequest
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
            'platform'              => ['required', 'string', 'in:apple_iap,google_play'],
            'receipt'               => ['required', 'string', 'min:1'],
            'originalTransactionId' => ['nullable', 'string', 'min:1'],
            'productId'             => ['required', 'string', 'in:zelta_pro_monthly,zelta_pro_annual'],
            'appVersion'            => ['nullable', 'string'],
            // EUR-only in v1.3.x — non-EUR is rejected later as ERR_CUR_001
            // (we accept the field here and let the service enforce per ADR-0004).
            'currency' => ['nullable', 'string', 'size:3'],
            // Optional in v1.3.0 — required from v1.3.1 (§8.6).
            'withdrawalConsent'             => ['nullable', 'array'],
            'withdrawalConsent.given'       => ['required_with:withdrawalConsent', 'boolean'],
            'withdrawalConsent.shownAt'     => ['required_with:withdrawalConsent', 'string'],
            'withdrawalConsent.acceptedAt'  => ['required_with:withdrawalConsent', 'string'],
            'withdrawalConsent.consentText' => ['required_with:withdrawalConsent', 'string', 'min:1'],
            'withdrawalConsent.version'     => ['required_with:withdrawalConsent', 'integer', 'min:1'],
        ];
    }
}
