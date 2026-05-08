<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for /invest form submissions.
 *
 * Honeypot strategy: the `website` field is rendered hidden and humans can't
 * fill it. If a bot does, the controller silently swallows the submission
 * (returns thanks redirect without writing). We do NOT add a `max:0` rule on
 * `website` here, because that would surface a validation error and signal
 * to the bot that the form has bot-detection — defeating the purpose.
 */
class InvestorInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'min:2', 'max:120'],
            'email'            => ['required', 'email:rfc,dns', 'max:255'],
            'linkedin_url'     => ['required', 'url', 'max:500', 'regex:#linkedin\.com/in/#i'],
            'investing_as'     => ['required', Rule::in(['angel', 'vc', 'family_office', 'other'])],
            'path_of_interest' => ['required', Rule::in(['licensed', 'non_custodial', 'both'])],
            'check_size_range' => ['required', Rule::in(['under_25k', '25k_100k', '100k_500k', '500k_plus'])],
            'questions'        => ['nullable', 'string', 'max:500'],
            'gdpr_consent'     => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'linkedin_url.regex'    => 'Please paste a LinkedIn profile URL (must contain linkedin.com/in/).',
            'gdpr_consent.accepted' => 'Please confirm you consent to us storing this submission.',
        ];
    }
}
