<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for updating notification preferences.
 *
 * @property array<string, array{push_enabled?: bool, email_enabled?: bool}> $preferences
 * @property string|null $device_id Optional device ID for device-specific preferences
 */
class UpdateNotificationPreferencesRequest extends BaseMobileRequest
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
            'preferences'                 => ['required', 'array'],
            'preferences.*'               => ['array'],
            'preferences.*.push_enabled'  => ['sometimes', 'boolean'],
            'preferences.*.email_enabled' => ['sometimes', 'boolean'],
            'device_id'                   => ['sometimes', 'uuid'],
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
            'preferences.required' => 'Preferences are required.',
            'preferences.array'    => 'Preferences must be an array.',
            'device_id.uuid'       => 'Device ID must be a valid UUID.',
        ];
    }

    /**
     * Get the validated preferences.
     *
     * @return array<string, array{push_enabled?: bool, email_enabled?: bool}>
     */
    public function getPreferences(): array
    {
        /** @var array<string, array{push_enabled?: bool, email_enabled?: bool}> $preferences */
        $preferences = $this->input('preferences', []);

        return $preferences;
    }
}
