<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class AllocateAssetsRequest extends FormRequest
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
            'allocations' => [
                'required',
                'array',
                'min:1',
                'max:20', // Reasonable limit on number of asset classes
            ],
            'allocations.*.assetClass' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_\-]+$/',
            ],
            'allocations.*.targetWeight' => [
                'required',
                'numeric',
                'between:0.1,100.0',
            ],
            'allocations.*.currentWeight' => [
                'sometimes',
                'numeric',
                'between:0.0,100.0',
            ],
            'allocations.*.amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'allocations.*.drift' => [
                'sometimes',
                'numeric',
                'between:-100.0,100.0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'allocations.required'                => 'Asset allocations are required.',
            'allocations.array'                   => 'Asset allocations must be an array.',
            'allocations.min'                     => 'At least one asset allocation is required.',
            'allocations.max'                     => 'Cannot have more than 20 asset allocations.',
            'allocations.*.assetClass.required'   => 'Asset class is required for each allocation.',
            'allocations.*.assetClass.regex'      => 'Asset class contains invalid characters.',
            'allocations.*.targetWeight.required' => 'Target weight is required for each allocation.',
            'allocations.*.targetWeight.between'  => 'Target weight must be between 0.1 and 100.',
            'allocations.*.currentWeight.between' => 'Current weight must be between 0 and 100.',
            'allocations.*.amount.min'            => 'Allocation amount cannot be negative.',
            'allocations.*.drift.between'         => 'Drift must be between -100 and 100.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allocations = $this->input('allocations', []);

            if (empty($allocations)) {
                return;
            }

            // Check that target weights sum to approximately 100%
            $totalWeight = array_sum(array_column($allocations, 'targetWeight'));
            if (abs($totalWeight - 100.0) > 0.01) {
                $validator->errors()->add('allocations', 'Target weights must sum to 100%.');
            }

            // Check for duplicate asset classes
            $assetClasses = array_column($allocations, 'assetClass');
            if (count($assetClasses) !== count(array_unique($assetClasses))) {
                $validator->errors()->add('allocations', 'Asset classes must be unique.');
            }

            // Validate known asset classes
            $validAssetClasses = [
                'cash', 'bonds', 'equities', 'commodities', 'real_estate',
                'alternatives', 'cryptocurrencies', 'precious_metals',
                'corporate_bonds', 'government_bonds', 'international_equities',
                'domestic_equities', 'emerging_markets', 'developed_markets',
            ];

            foreach ($allocations as $index => $allocation) {
                $assetClass = strtolower($allocation['assetClass'] ?? '');
                if (! in_array($assetClass, $validAssetClasses, true)) {
                    $validator->errors()->add(
                        "allocations.{$index}.assetClass",
                        "Unknown asset class '{$allocation['assetClass']}'. Valid classes are: " .
                        implode(', ', $validAssetClasses)
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $allocations = $this->input('allocations', []);

        // Normalize asset class names and ensure proper structure
        $normalizedAllocations = array_map(function ($allocation) {
            if (isset($allocation['assetClass'])) {
                $allocation['assetClass'] = strtolower(trim($allocation['assetClass']));
            }

            // Set defaults for optional fields
            $allocation['currentWeight'] = $allocation['currentWeight'] ?? 0.0;
            $allocation['drift'] = $allocation['drift'] ?? 0.0;

            return $allocation;
        }, $allocations);

        $this->merge(['allocations' => $normalizedAllocations]);
    }
}
