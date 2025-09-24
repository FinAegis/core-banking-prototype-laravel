<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_did'                => 'required|string|max:500',
            'amount'                      => 'required|numeric|min:0.01|max:1000000',
            'currency'                    => 'nullable|string|size:3',
            'description'                 => 'nullable|string|max:500',
            'split_payments'              => 'nullable|array',
            'split_payments.*.agent_did'  => 'required|string',
            'split_payments.*.amount'     => 'nullable|numeric|min:0',
            'split_payments.*.percentage' => 'nullable|numeric|min:0|max:100',
            'idempotency_key'             => 'nullable|string|max:100',
            'metadata'                    => 'nullable|array',
        ];
    }
}
