<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentProtocol;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submitter_did'     => 'required|string|max:500',
            'feedback_type'     => 'required|string|in:transaction,dispute,endorsement,general',
            'transaction_id'    => 'nullable|string|uuid',
            'dispute_id'        => 'nullable|string|uuid',
            'outcome'           => 'nullable|string|in:success,failed,cancelled,timeout',
            'transaction_value' => 'nullable|numeric|min:0',
            'severity'          => 'nullable|string|in:minor,moderate,major,critical',
            'reason'            => 'nullable|string|max:500',
            'evidence'          => 'nullable|array',
            'rating'            => 'nullable|integer|min:1|max:5',
            'comment'           => 'nullable|string|max:1000',
            'boost_amount'      => 'nullable|numeric|min:0|max:10',
        ];
    }
}
