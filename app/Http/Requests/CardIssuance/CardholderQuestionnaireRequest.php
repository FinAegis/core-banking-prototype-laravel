<?php

declare(strict_types=1);

namespace App\Http\Requests\CardIssuance;

use App\Domain\CardIssuance\Providers\Lepo\LepoCardholderForm;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The identity-check questionnaire alone: what a cardholder sends to correct
 * the answers before the identity check.
 */
class CardholderQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::questionnaireRules();
    }

    /**
     * Rules shared with the full cardholder form. The occupation must be one
     * of Lepo's codes whenever Lepo could list them.
     *
     * @return array<string, list<mixed>>
     */
    public static function questionnaireRules(): array
    {
        $occupations = app(LepoCardholderForm::class)->occupationCodes();

        return [
            'occupation' => $occupations === []
                ? ['required', 'string', 'max:100']
                : ['required', 'string', Rule::in($occupations)],
            'annual_salary'           => ['required', 'string', Rule::in(LepoCardOnboarding::ANNUAL_SALARY_BANDS)],
            'account_purpose'         => ['required', 'string', Rule::in(LepoCardholderForm::ACCOUNT_PURPOSES)],
            'expected_monthly_volume' => ['required', 'string', Rule::in(LepoCardOnboarding::MONTHLY_VOLUME_BANDS)],
            'document_issue_date'     => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * The answers keyed as Lepo names them.
     *
     * @return array<string, string>
     */
    public function questionnaire(): array
    {
        $answers = [];
        foreach (LepoCardholderForm::QUESTIONNAIRE_KEYS as $field => $key) {
            $answers[$key] = trim((string) $this->validated($field));
        }

        return $answers;
    }
}
