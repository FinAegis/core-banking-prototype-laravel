<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acknowledgment_message' => 'nullable|string|max:500',
        ];
    }
}
