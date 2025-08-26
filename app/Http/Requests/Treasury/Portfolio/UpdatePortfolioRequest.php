<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury\Portfolio;

use App\Domain\Treasury\ValueObjects\InvestmentStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePortfolioRequest extends FormRequest
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
            'strategy' => [
                'required',
                'array',
            ],
            'strategy.riskProfile' => [
                'required',
                'string',
                Rule::in([
                    InvestmentStrategy::RISK_CONSERVATIVE,
                    InvestmentStrategy::RISK_MODERATE,
                    InvestmentStrategy::RISK_AGGRESSIVE,
                    InvestmentStrategy::RISK_SPECULATIVE,
                ]),
            ],
            'strategy.rebalanceThreshold' => [
                'required',
                'numeric',
                'between:0.1,50.0',
            ],
            'strategy.targetReturn' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'strategy.constraints' => [
                'sometimes',
                'array',
            ],
            'strategy.constraints.*' => [
                'sometimes',
                'numeric',
            ],
            'strategy.metadata' => [
                'sometimes',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'strategy.required'                    => 'Investment strategy is required.',
            'strategy.riskProfile.required'        => 'Risk profile is required.',
            'strategy.riskProfile.in'              => 'Risk profile must be one of: conservative, moderate, aggressive, speculative.',
            'strategy.rebalanceThreshold.required' => 'Rebalance threshold is required.',
            'strategy.rebalanceThreshold.between'  => 'Rebalance threshold must be between 0.1 and 50.',
            'strategy.targetReturn.required'       => 'Target return is required.',
            'strategy.targetReturn.min'            => 'Target return cannot be negative.',
            'strategy.targetReturn.max'            => 'Target return cannot exceed 100%.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure strategy is properly structured
        if ($this->has('strategy') && is_array($this->strategy)) {
            $strategy = $this->strategy;

            // Convert percentage values if needed
            if (isset($strategy['rebalanceThreshold']) && $strategy['rebalanceThreshold'] > 50) {
                $strategy['rebalanceThreshold'] = $strategy['rebalanceThreshold'] / 100;
            }

            if (isset($strategy['targetReturn']) && $strategy['targetReturn'] > 1) {
                $strategy['targetReturn'] = $strategy['targetReturn'] / 100;
            }

            $this->merge(['strategy' => $strategy]);
        }
    }
}
