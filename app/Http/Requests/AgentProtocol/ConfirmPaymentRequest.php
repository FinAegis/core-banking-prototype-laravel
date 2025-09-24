<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmation_code' => 'nullable|string|max:100',
        ];
    }
}
