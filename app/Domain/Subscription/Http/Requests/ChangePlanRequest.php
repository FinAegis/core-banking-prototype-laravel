<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangePlanRequest extends FormRequest
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
            'plan'              => ['required', 'string', 'in:monthly_pro,annual_pro'],
            'prorationBehavior' => ['nullable', 'string', 'in:create_prorations,none'],
        ];
    }
}
