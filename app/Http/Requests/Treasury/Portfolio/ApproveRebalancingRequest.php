<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRebalancingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user has treasury approval permissions or treasury API scope
        return $this->user()->can('approve-treasury-rebalancing') ||
               $this->user()->hasRole(['admin', 'treasury-manager', 'senior-treasury-officer']) ||
               $this->user()->tokenCan('treasury');
    }

    public function rules(): array
    {
        return [
            'plan' => [
                'required',
                'array',
            ],
            'plan.portfolio_id' => [
                'required',
                'string',
                'uuid',
            ],
            'plan.actions' => [
                'required',
                'array',
                'min:1',
            ],
            'plan.actions.*.asset_class' => [
                'required',
                'string',
            ],
            'plan.actions.*.action_type' => [
                'required',
                'string',
                'in:buy,sell',
            ],
            'plan.actions.*.amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'plan.actions.*.target_weight' => [
                'required',
                'numeric',
                'between:0.0,100.0',
            ],
            'plan.total_transaction_cost' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'approval_comments' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'risk_acknowledgment' => [
                'required',
                'boolean',
                'accepted',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required'                         => 'Rebalancing plan is required.',
            'plan.portfolio_id.required'            => 'Portfolio ID is required in the plan.',
            'plan.portfolio_id.uuid'                => 'Portfolio ID must be a valid UUID.',
            'plan.actions.required'                 => 'Rebalancing actions are required.',
            'plan.actions.min'                      => 'At least one rebalancing action is required.',
            'plan.actions.*.asset_class.required'   => 'Asset class is required for each action.',
            'plan.actions.*.action_type.required'   => 'Action type is required for each action.',
            'plan.actions.*.action_type.in'         => 'Action type must be either buy or sell.',
            'plan.actions.*.amount.required'        => 'Amount is required for each action.',
            'plan.actions.*.amount.min'             => 'Action amount must be at least 0.01.',
            'plan.actions.*.target_weight.required' => 'Target weight is required for each action.',
            'plan.actions.*.target_weight.between'  => 'Target weight must be between 0 and 100.',
            'approval_comments.max'                 => 'Approval comments cannot exceed 1000 characters.',
            'risk_acknowledgment.required'          => 'Risk acknowledgment is required.',
            'risk_acknowledgment.accepted'          => 'You must acknowledge the risks associated with rebalancing.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $plan = $this->input('plan', []);

            if (empty($plan['actions'])) {
                return;
            }

            // Validate that portfolio_id in plan matches route parameter
            $routePortfolioId = $this->route('id');
            if (isset($plan['portfolio_id']) && $plan['portfolio_id'] !== $routePortfolioId) {
                $validator->errors()->add('plan.portfolio_id', 'Portfolio ID in plan must match the route parameter.');
            }

            // Check that total target weights sum to approximately 100%
            $totalTargetWeight = array_sum(array_column($plan['actions'], 'target_weight'));
            if (abs($totalTargetWeight - 100.0) > 0.01) {
                $validator->errors()->add('plan.actions', 'Total target weights must sum to 100%.');
            }

            // Validate that buy and sell actions are balanced
            $totalBuyAmount = 0;
            $totalSellAmount = 0;

            foreach ($plan['actions'] as $action) {
                if ($action['action_type'] === 'buy') {
                    $totalBuyAmount += $action['amount'];
                } elseif ($action['action_type'] === 'sell') {
                    $totalSellAmount += $action['amount'];
                }
            }

            // Allow for small discrepancies due to transaction costs
            $discrepancy = abs($totalBuyAmount - $totalSellAmount);
            $transactionCost = $plan['total_transaction_cost'] ?? 0;

            if ($discrepancy > ($transactionCost + 100)) { // Allow for $100 buffer
                $validator->errors()->add('plan.actions', 'Buy and sell amounts must be approximately balanced.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Ensure risk_acknowledgment is boolean
        if ($this->has('risk_acknowledgment')) {
            $this->merge([
                'risk_acknowledgment' => $this->boolean('risk_acknowledgment'),
            ]);
        }
    }
}
