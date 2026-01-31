<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for blocking a mobile device.
 *
 * @property string|null $reason Optional reason for blocking
 */
class BlockDeviceRequest extends BaseMobileRequest
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
            'reason' => ['sometimes', 'string', 'max:255'],
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
            'reason.max' => 'Block reason must not exceed 255 characters.',
        ];
    }

    /**
     * Get the reason for blocking, with a default value.
     */
    public function getBlockReason(): string
    {
        return $this->input('reason', 'User requested block');
    }
}
