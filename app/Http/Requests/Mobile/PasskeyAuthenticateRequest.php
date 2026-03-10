<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

/**
 * Form request for verifying passkey/WebAuthn authentication.
 *
 * device_id is optional: when absent, the device is looked up by credential_id
 * (standard WebAuthn flow where the RP identifies the key by credential).
 *
 * @property string|null $device_id
 * @property string $challenge       The challenge string issued by the server
 * @property string $credential_id   Base64url-encoded credential ID
 * @property string $authenticator_data  Base64url-encoded authenticator data
 * @property string $client_data_json    Base64url-encoded client data JSON
 * @property string $signature       Base64url-encoded signature
 */
class PasskeyAuthenticateRequest extends BaseMobileRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_id'          => ['nullable', 'string'],
            'challenge'          => ['required', 'string'],
            'credential_id'      => ['required', 'string'],
            'authenticator_data' => ['required', 'string'],
            'client_data_json'   => ['required', 'string'],
            'signature'          => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'challenge.required'          => 'Challenge is required.',
            'credential_id.required'      => 'Credential ID is required.',
            'authenticator_data.required' => 'Authenticator data is required.',
            'client_data_json.required'   => 'Client data JSON is required.',
            'signature.required'          => 'Signature is required for verification.',
        ];
    }
}
