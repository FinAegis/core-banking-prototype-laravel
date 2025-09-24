<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class CreateEscrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_did'         => 'required|string|max:500',
            'receiver_did'       => 'required|string|max:500',
            'amount'             => 'required|numeric|min:0.01|max:1000000',
            'currency'           => 'nullable|string|size:3',
            'conditions'         => 'nullable|array',
            'release_conditions' => 'nullable|array',
            'expires_at'         => 'nullable|date|after:now',
            'metadata'           => 'nullable|array',
        ];
    }
}
