<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class RegisterAgentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Additional authorization can be added here
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                                => 'required|string|max:255',
            'did'                                 => 'nullable|string|max:500|unique:agents,did',
            'type'                                => 'nullable|string|in:autonomous,assistant,service,gateway',
            'network_id'                          => 'nullable|string|max:100',
            'organization'                        => 'nullable|string|max:255',
            'endpoints'                           => 'nullable|array',
            'endpoints.*'                         => 'url',
            'capabilities'                        => 'nullable|array',
            'capabilities.*.id'                   => 'required|string|max:100',
            'capabilities.*.endpoints'            => 'nullable|array',
            'capabilities.*.parameters'           => 'nullable|array',
            'capabilities.*.required_permissions' => 'nullable|array',
            'capabilities.*.supported_protocols'  => 'nullable|array',
            'default_currency'                    => 'nullable|string|size:3',
            'metadata'                            => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'              => 'Agent name is required',
            'did.unique'                 => 'This DID is already registered',
            'type.in'                    => 'Agent type must be one of: autonomous, assistant, service, gateway',
            'endpoints.*.url'            => 'Each endpoint must be a valid URL',
            'capabilities.*.id.required' => 'Each capability must have an ID',
            'default_currency.size'      => 'Currency must be a 3-letter code',
        ];
    }
}
