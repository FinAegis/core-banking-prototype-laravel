<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a card top-up / withdraw amount in integer minor units.
 */
final class FinCardAmountRequest extends FormRequest
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
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
        ];
    }
}
