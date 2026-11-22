<?php

declare(strict_types=1);

namespace App\Http\Requests\CardIssuance;

use App\Domain\CardIssuance\Providers\CardholderProfile;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardholderForm;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cardholder form for the provider-neutral onboarding. Field names follow the
 * FinCard form where they overlap (birthday, address, zip_code) so the app can
 * reuse it.
 */
class CreateCardholderRequest extends FormRequest
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
        return [...$this->personalRules(), ...CardholderQuestionnaireRequest::questionnaireRules()];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function personalRules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:' . LepoCardOnboarding::NAME_MAX_LENGTH],
            'last_name'     => ['required', 'string', 'max:' . LepoCardOnboarding::NAME_MAX_LENGTH],
            'email'         => ['required', 'email', 'max:255'],
            'phone'         => ['required', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'birthday'      => ['required', 'date_format:Y-m-d', 'before_or_equal:' . now()->subYears(LepoCardholderForm::MIN_AGE)->format('Y-m-d')],
            'address'       => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['required', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'zip_code'      => ['required', 'string', 'max:20'],
            'country'       => ['required', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'nationality'   => ['required', 'string', 'regex:/^[A-Za-z]{2}$/'],
        ];
    }

    public function toProfile(): CardholderProfile
    {
        $value = fn (string $key): string => trim((string) $this->validated($key));
        $optional = fn (string $key): ?string => ($this->validated($key) ?? null) !== null && trim((string) $this->validated($key)) !== ''
            ? trim((string) $this->validated($key))
            : null;

        return new CardholderProfile(
            firstName: $value('first_name'),
            lastName: $value('last_name'),
            email: $value('email'),
            phone: $value('phone'),
            dateOfBirth: $value('birthday'),
            addressLine1: $value('address'),
            addressLine2: $optional('address_line2'),
            city: $value('city'),
            state: $optional('state'),
            postalCode: $value('zip_code'),
            country: strtoupper($value('country')),
            nationality: strtoupper($value('nationality')),
            occupation: $value('occupation'),
            annualSalary: $value('annual_salary'),
            accountPurpose: $value('account_purpose'),
            expectedMonthlyVolume: $value('expected_monthly_volume'),
            documentIssueDate: $value('document_issue_date'),
        );
    }
}
