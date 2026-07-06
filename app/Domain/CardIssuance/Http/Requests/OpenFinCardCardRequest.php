<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a FinCard card-open request. `amount_cents` is the initial load in
 * integer minor units; `card_type_id` selects the BIN/product (from
 * /v1/cards/reference/card-types).
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
            'card_type_id' => ['required', 'integer', 'min:1'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'label'        => ['nullable', 'string', 'max:64'],
        ];
    }
}
