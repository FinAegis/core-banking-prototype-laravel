<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for enabling biometric authentication on a device.
 *
 * @property string $device_id
 * @property string $public_key Base64 encoded ECDSA P-256 public key
 * @property string|null $key_id Optional key identifier
 */
class EnableBiometricRequest extends BaseMobileRequest
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
            'device_id'  => ['required', 'string'],
            'public_key' => ['required', 'string'],
            'key_id'     => ['nullable', 'string', 'max:100'],
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
            'device_id.required'  => 'Device ID is required.',
            'public_key.required' => 'Public key is required for biometric enrollment.',
        ];
    }
}
