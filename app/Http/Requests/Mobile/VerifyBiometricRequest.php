<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for verifying biometric authentication.
 *
 * @property string $device_id
 * @property string $challenge The challenge string to verify
 * @property string $signature Base64 encoded ECDSA signature
 */
class VerifyBiometricRequest extends BaseMobileRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * This is a public endpoint - no auth required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string'],
            'challenge' => ['required', 'string'],
            'signature' => ['required', 'string'],
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
            'device_id.required' => 'Device ID is required.',
            'challenge.required' => 'Challenge is required.',
            'signature.required' => 'Signature is required for verification.',
        ];
    }
}
