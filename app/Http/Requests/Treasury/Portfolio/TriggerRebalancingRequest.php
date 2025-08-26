<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury\Portfolio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriggerRebalancingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user has treasury permissions or treasury API scope
        return $this->user()->can('manage-treasury') ||
               $this->user()->hasRole(['admin', 'treasury-manager']) ||
               $this->user()->tokenCan('treasury');
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'sometimes',
                'string',
                'max:255',
                Rule::in([
                    'manual_trigger',
                    'drift_threshold_exceeded',
                    'scheduled_rebalancing',
                    'strategy_change',
                    'market_conditions',
                    'regulatory_compliance',
                    'risk_management',
                    'performance_optimization',
                ]),
            ],
            'force' => [
                'sometimes',
                'boolean',
            ],
            'notify_stakeholders' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.in'                   => 'Invalid rebalancing reason. Valid reasons are: manual_trigger, drift_threshold_exceeded, scheduled_rebalancing, strategy_change, market_conditions, regulatory_compliance, risk_management, performance_optimization.',
            'reason.max'                  => 'Reason cannot exceed 255 characters.',
            'force.boolean'               => 'Force flag must be true or false.',
            'notify_stakeholders.boolean' => 'Notify stakeholders flag must be true or false.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default reason if not provided
        if (! $this->has('reason')) {
            $this->merge(['reason' => 'manual_trigger']);
        }

        // Set default values for optional fields
        $this->merge([
            'force'               => $this->boolean('force', false),
            'notify_stakeholders' => $this->boolean('notify_stakeholders', true),
        ]);
    }
}
