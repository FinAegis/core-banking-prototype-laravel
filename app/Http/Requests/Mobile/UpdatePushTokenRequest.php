<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for updating a device's push notification token.
 *
 * @property string $push_token
 */
class UpdatePushTokenRequest extends BaseMobileRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'push_token' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'push_token.required' => 'Push token is required.',
            'push_token.max'      => 'Push token must not exceed 500 characters.',
        ];
    }
}
