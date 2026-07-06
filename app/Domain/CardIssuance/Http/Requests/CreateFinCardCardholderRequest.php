<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Requests;

use App\Domain\CardIssuance\Support\FinCardCardholderMapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates FinCard Cardholder-V2 onboarding input (snake_case).
 *
 * Field requirements follow FinCard's card-holders docs; exact enum values
 * (gender, financial brackets) are pending sandbox confirmation, so those are
 * validated as free strings rather than hard enums to avoid over-constraining
 * an unconfirmed contract. `id_type` IS a fixed FinCard enum.
 */
final class CreateFinCardCardholderRequest extends FormRequest
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
        return [
            'first_name'              => ['required', 'string', 'max:100'],
            'last_name'               => ['required', 'string', 'max:100'],
            'gender'                  => ['required', 'string', 'max:16'],
            'birthday'                => ['required', 'date_format:Y-m-d'],
            'nationality'             => ['required', 'string', 'size:2'],
            'occupation'              => ['required', 'string', 'max:100'],
            'annual_salary'           => ['required', 'string', 'max:64'],
            'expected_monthly_volume' => ['required', 'string', 'max:64'],
            'account_purpose'         => ['required', 'string', 'max:255'],
            'phone'                   => ['required', 'string', 'max:32'],
            'phone_country_code'      => ['required', 'string', 'max:8'],
            'email'                   => ['required', 'email', 'max:255'],
            'country'                 => ['required', 'string', 'size:2'],
            'state'                   => ['nullable', 'string', 'max:100'],
            'city'                    => ['required', 'string', 'max:100'],
            'address'                 => ['required', 'string', 'max:255'],
            'zip_code'                => ['required', 'string', 'max:20'],
            'id_type'                 => ['required', Rule::in(FinCardCardholderMapper::ID_TYPES)],
            'id_number'               => ['required', 'string', 'max:64'],
            'issue_date'              => ['nullable', 'date_format:Y-m-d'],
            'id_expiry_date'          => ['nullable', 'date_format:Y-m-d'],
            'id_front_file_id'        => ['required', 'string', 'max:255'],
            'id_back_file_id'         => ['nullable', 'string', 'max:255'],
            'id_selfie_file_id'       => ['required', 'string', 'max:255'],
        ];
    }
}
