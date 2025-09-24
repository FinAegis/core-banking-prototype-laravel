<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCapabilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action'                              => 'required|string|in:add,update,remove',
            'capabilities'                        => 'required|array|min:1',
            'capabilities.*.id'                   => 'required|string|max:100',
            'capabilities.*.endpoints'            => 'nullable|array',
            'capabilities.*.parameters'           => 'nullable|array',
            'capabilities.*.required_permissions' => 'nullable|array',
            'capabilities.*.supported_protocols'  => 'nullable|array',
        ];
    }
}
