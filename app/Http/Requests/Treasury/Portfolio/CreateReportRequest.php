<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury\Portfolio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user has treasury permissions or treasury API scope
        return $this->user()->can('view-treasury-reports') ||
               $this->user()->hasRole(['admin', 'treasury-manager', 'treasury-analyst', 'compliance-officer']) ||
               $this->user()->tokenCan('treasury');
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    'performance',
                    'risk_analysis',
                    'compliance',
                    'allocation_summary',
                    'rebalancing_history',
                    'attribution_analysis',
                    'benchmark_comparison',
                    'comprehensive',
                ]),
            ],
            'period' => [
                'required',
                'string',
                Rule::in([
                    '1d', '7d', '30d', '90d', '6m', '1y', 'ytd', 'all',
                    'custom',
                ]),
            ],
            'start_date' => [
                'required_if:period,custom',
                'date',
                'before_or_equal:end_date',
            ],
            'end_date' => [
                'required_if:period,custom',
                'date',
                'after_or_equal:start_date',
                'before_or_equal:today',
            ],
            'format' => [
                'sometimes',
                'string',
                Rule::in(['pdf', 'excel', 'json', 'csv']),
            ],
            'include_charts' => [
                'sometimes',
                'boolean',
            ],
            'include_benchmarks' => [
                'sometimes',
                'boolean',
            ],
            'benchmark_indices' => [
                'sometimes',
                'array',
            ],
            'benchmark_indices.*' => [
                'string',
                'max:50',
            ],
            'granularity' => [
                'sometimes',
                'string',
                Rule::in(['daily', 'weekly', 'monthly', 'quarterly']),
            ],
            'include_attribution' => [
                'sometimes',
                'boolean',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'recipients' => [
                'sometimes',
                'array',
            ],
            'recipients.*.email' => [
                'required_with:recipients',
                'email',
                'max:255',
            ],
            'recipients.*.name' => [
                'sometimes',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'                    => 'Report type is required.',
            'type.in'                          => 'Invalid report type. Valid types are: performance, risk_analysis, compliance, allocation_summary, rebalancing_history, attribution_analysis, benchmark_comparison, comprehensive.',
            'period.required'                  => 'Report period is required.',
            'period.in'                        => 'Invalid period. Valid periods are: 1d, 7d, 30d, 90d, 6m, 1y, ytd, all, custom.',
            'start_date.required_if'           => 'Start date is required when period is custom.',
            'start_date.before_or_equal'       => 'Start date must be before or equal to end date.',
            'end_date.required_if'             => 'End date is required when period is custom.',
            'end_date.after_or_equal'          => 'End date must be after or equal to start date.',
            'end_date.before_or_equal'         => 'End date cannot be in the future.',
            'format.in'                        => 'Invalid format. Valid formats are: pdf, excel, json, csv.',
            'granularity.in'                   => 'Invalid granularity. Valid values are: daily, weekly, monthly, quarterly.',
            'currency.size'                    => 'Currency must be a 3-letter ISO code.',
            'currency.regex'                   => 'Currency must be a valid ISO 4217 code (e.g., USD, EUR, GBP).',
            'recipients.*.email.email'         => 'Each recipient must have a valid email address.',
            'recipients.*.email.required_with' => 'Email is required for each recipient.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate date range for custom period
            if ($this->input('period') === 'custom') {
                $startDate = $this->input('start_date');
                $endDate = $this->input('end_date');

                if ($startDate && $endDate) {
                    $start = \Carbon\Carbon::parse($startDate);
                    $end = \Carbon\Carbon::parse($endDate);

                    // Check maximum date range (e.g., 5 years)
                    if ($start->diffInYears($end) > 5) {
                        $validator->errors()->add('period', 'Custom date range cannot exceed 5 years.');
                    }
                }
            }

            // Validate benchmark indices for benchmark comparison report
            if (
                $this->input('type') === 'benchmark_comparison' &&
                $this->input('include_benchmarks', true) &&
                empty($this->input('benchmark_indices'))
            ) {
                $validator->errors()->add('benchmark_indices', 'Benchmark indices are required for benchmark comparison reports.');
            }

            // Validate that comprehensive reports have reasonable constraints
            if ($this->input('type') === 'comprehensive') {
                $period = $this->input('period');
                if (in_array($period, ['1d', '7d'])) {
                    $validator->errors()->add('period', 'Comprehensive reports require longer periods (30d minimum).');
                }
            }

            // Validate recipient count
            $recipients = $this->input('recipients', []);
            if (count($recipients) > 10) {
                $validator->errors()->add('recipients', 'Cannot have more than 10 recipients.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'format'              => $this->input('format', 'pdf'),
            'include_charts'      => $this->boolean('include_charts', true),
            'include_benchmarks'  => $this->boolean('include_benchmarks', false),
            'granularity'         => $this->input('granularity', $this->getDefaultGranularity()),
            'include_attribution' => $this->boolean('include_attribution', false),
            'currency'            => strtoupper($this->input('currency', 'USD')),
        ]);
    }

    private function getDefaultGranularity(): string
    {
        return match ($this->input('period')) {
            '1d', '7d' => 'daily',
            '30d' => 'weekly',
            '90d', '6m' => 'monthly',
            '1y', 'ytd', 'all' => 'quarterly',
            default => 'monthly',
        };
    }
}
