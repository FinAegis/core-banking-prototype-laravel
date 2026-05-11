<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Http\Requests;

use App\Domain\Pricing\Validation\MoneyFormRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateQuoteRequest — Form Request for POST /api/v1/pricing/quote.
 *
 * Validates the request body per spec §5.1. The Idempotency-Key header
 * validation is handled by the idempotency.required middleware applied at
 * the route level (OD-1 decision: both middleware and entity-key dedup coexist).
 *
 * The ?dryRun query parameter is read separately via $request->boolean('dryRun')
 * and is NOT a body field. This FormRequest does not validate it.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.1
 */
final class CreateQuoteRequest extends FormRequest
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
            'kind' => [
                'required',
                'string',
                'in:send,swap,ramp_buy,ramp_sell,subscription_initial,card_waitlist_deposit',
            ],
            'amount'       => ['required', 'array', new MoneyFormRule(allowNegative: false)],
            'from'         => ['nullable', 'array'],
            'from.asset'   => ['nullable', 'string', 'max:16'],
            'from.network' => ['nullable', 'string', 'max:32'],
            'to'           => ['nullable', 'array'],
            'to.asset'     => ['nullable', 'string', 'max:16'],
            'to.network'   => ['nullable', 'string', 'max:32'],
            'recipient'    => ['nullable', 'string', 'max:255'],
            'currency'     => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * Whether this is a dry-run request (read from query string, not body).
     */
    public function isDryRun(): bool
    {
        return $this->boolean('dryRun');
    }
}
