<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_did'            => 'required|string|max:500',
            'message_type'            => 'nullable|string|in:text,json,binary,command',
            'content'                 => 'required',
            'priority'                => 'nullable|string|in:low,normal,high,urgent',
            'requires_acknowledgment' => 'nullable|boolean',
            'expires_at'              => 'nullable|date|after:now',
            'metadata'                => 'nullable|array',
        ];
    }
}
