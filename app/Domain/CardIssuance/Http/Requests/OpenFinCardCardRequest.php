<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a FinCard card-open request. `amount_cents` is the initial load in
 * integer minor units; `card_type_id` selects the BIN/product (enumerated via
 * GET /v1/cards/reference/card-types).
 *
 * `card_type_id` is OPTIONAL: when omitted the controller falls back to the
 * tenant default (`FINCARD_DEFAULT_CARD_TYPE_ID`). A v1 single-product tenant
 * therefore never has to send it; multi-product tenants pass the chosen id.
 */
final class OpenFinCardCardRequest extends FormRequest
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
            'card_type_id' => ['nullable', 'integer', 'min:1'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'label'        => ['nullable', 'string', 'max:64'],
        ];
    }
}
