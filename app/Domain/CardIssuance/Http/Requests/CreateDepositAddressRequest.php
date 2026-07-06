<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a crypto deposit-address request. The coin set is dynamic (FinCard's
 * /wallet/v2/coins is the runtime authority), so we only shape-check here and
 * let FinCard reject an unsupported coin.
 */
final class CreateDepositAddressRequest extends FormRequest
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
            'coin_key' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_]+$/'],
        ];
    }
}
