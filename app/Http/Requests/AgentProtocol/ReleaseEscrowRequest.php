<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseEscrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_did' => 'required|string|max:500',
            'reason'    => 'nullable|string|max:500',
        ];
    }
}
