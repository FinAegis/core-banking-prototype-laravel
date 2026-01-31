<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for operations requiring only a device ID.
 *
 * Used for: disabling biometric, getting biometric challenge.
 *
 * @property string $device_id
 */
class DeviceIdRequest extends BaseMobileRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For challenge request, no auth required
        // For disable biometric, auth is required
        // Authorization is handled at the controller level
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
        ];
    }
}
