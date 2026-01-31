<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for registering a mobile device.
 *
 * @property string $device_id
 * @property string $platform
 * @property string $app_version
 * @property string|null $push_token
 * @property string|null $device_name
 * @property string|null $device_model
 * @property string|null $os_version
 */
class RegisterDeviceRequest extends BaseMobileRequest
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
            'device_id'    => ['required', 'string', 'max:100'],
            'platform'     => ['required', 'in:ios,android'],
            'app_version'  => ['required', 'string', 'max:20'],
            'push_token'   => ['nullable', 'string', 'max:500'],
            'device_name'  => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'os_version'   => ['nullable', 'string', 'max:50'],
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
            'device_id.required'   => 'Device ID is required.',
            'device_id.max'        => 'Device ID must not exceed 100 characters.',
            'platform.required'    => 'Platform is required.',
            'platform.in'          => 'Platform must be either ios or android.',
            'app_version.required' => 'App version is required.',
            'app_version.max'      => 'App version must not exceed 20 characters.',
        ];
    }
}
