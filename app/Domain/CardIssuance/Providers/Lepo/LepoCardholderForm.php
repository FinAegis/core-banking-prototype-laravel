<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The cardholder form the app draws: its fields, the values the select fields
 * offer and the values already known. The field names are the request keys and
 * the keys of 422 validation errors, so the form can change without an app
 * release.
 */
final class LepoCardholderForm
{
    public const OCCUPATIONS_CACHE_KEY = 'cards:lepo:occupation-codes';

    /** What the card is for: free text at Lepo, offered as a fixed list. */
    public const ACCOUNT_PURPOSES = ['Everyday spending', 'Online shopping', 'Travel', 'Subscriptions and bills', 'Business expenses'];

    /** Countries and nationalities without cards in v1. */
    public const EXCLUDED_COUNTRIES = ['US'];

    public const MIN_AGE = 18;

    /** Questionnaire field → the key Lepo uses for it. */
    public const QUESTIONNAIRE_KEYS = [
        'occupation'              => 'occupation',
        'annual_salary'           => 'annualSalary',
        'account_purpose'         => 'accountPurpose',
        'expected_monthly_volume' => 'expectedMonthlyVolume',
        'document_issue_date'     => 'documentIssueDate',
    ];

    public function __construct(private readonly LepoClient $client)
    {
    }

    /**
     * The whole form for a new cardholder; only the questionnaire for a
     * cardholder whose answers can still be corrected.
     *
     * @return array{fields: list<array<string, mixed>>, options: array<string, list<array{value: string, label: string}>>, prefill: array<string, string>}
     */
    public function describe(User $user, ?Cardholder $cardholder): array
    {
        $options = $this->options();

        return [
            'fields'  => $cardholder === null ? [...$this->personalFields(), ...$this->questionnaireFields($options)] : $this->questionnaireFields($options),
            'options' => $options,
            'prefill' => $cardholder === null ? $this->prefillFor($user) : $this->answersOf($cardholder),
        ];
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function options(): array
    {
        return [
            'occupation'              => $this->occupations(),
            'annual_salary'           => self::bands(LepoCardOnboarding::ANNUAL_SALARY_BANDS),
            'account_purpose'         => array_map(static fn (string $purpose): array => ['value' => $purpose, 'label' => $purpose], self::ACCOUNT_PURPOSES),
            'expected_monthly_volume' => self::bands(LepoCardOnboarding::MONTHLY_VOLUME_BANDS),
        ];
    }

    /**
     * The occupation codes Lepo accepts; empty when Lepo could not list them.
     *
     * @return list<string>
     */
    public function occupationCodes(): array
    {
        return array_column($this->occupations(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function occupations(): array
    {
        /** @var list<array{value: string, label: string}>|null $cached */
        $cached = Cache::get(self::OCCUPATIONS_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $rows = $this->client->getOccupationCodes();
        } catch (LepoApiException $e) {
            // The form still works with a free-text occupation; retry soon.
            Log::warning('Lepo occupation codes could not be read', ['code' => $e->lepoCode()]);
            Cache::put(self::OCCUPATIONS_CACHE_KEY, [], now()->addMinutes(5));

            return [];
        }

        $occupations = [];
        foreach ($rows as $row) {
            $value = is_array($row) ? trim((string) ($row['value'] ?? '')) : '';
            $label = is_array($row) ? trim((string) ($row['title'] ?? '')) : '';
            if ($value !== '' && $label !== '') {
                $occupations[] = ['value' => $value, 'label' => $label];
            }
        }

        Cache::put(self::OCCUPATIONS_CACHE_KEY, $occupations, $occupations === [] ? now()->addMinutes(5) : now()->addDay());

        return $occupations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function personalFields(): array
    {
        $nameLength = LepoCardOnboarding::NAME_MAX_LENGTH;

        return [
            ['name' => 'first_name', 'type' => 'text', 'required' => true, 'max_length' => $nameLength],
            ['name' => 'last_name', 'type' => 'text', 'required' => true, 'max_length' => $nameLength],
            ['name' => 'email', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'type' => 'phone', 'required' => true, 'format' => 'e164'],
            ['name' => 'birthday', 'type' => 'date', 'required' => true, 'min_age' => self::MIN_AGE],
            ['name' => 'address', 'type' => 'text', 'required' => true, 'max_length' => 255],
            ['name' => 'address_line2', 'type' => 'text', 'required' => false, 'max_length' => 255],
            ['name' => 'city', 'type' => 'text', 'required' => true, 'max_length' => 100],
            ['name' => 'state', 'type' => 'text', 'required' => false, 'max_length' => 100],
            ['name' => 'zip_code', 'type' => 'text', 'required' => true, 'max_length' => 20],
            ['name' => 'country', 'type' => 'country', 'required' => true, 'excluded' => self::EXCLUDED_COUNTRIES],
            ['name' => 'nationality', 'type' => 'country', 'required' => true, 'excluded' => self::EXCLUDED_COUNTRIES],
        ];
    }

    /**
     * @param  array<string, list<array{value: string, label: string}>>  $options
     * @return list<array<string, mixed>>
     */
    private function questionnaireFields(array $options): array
    {
        return [
            $options['occupation'] === []
                ? ['name' => 'occupation', 'type' => 'text', 'required' => true, 'max_length' => 100]
                : ['name' => 'occupation', 'type' => 'select', 'required' => true],
            ['name' => 'annual_salary', 'type' => 'select', 'required' => true],
            ['name' => 'account_purpose', 'type' => 'select', 'required' => true],
            ['name' => 'expected_monthly_volume', 'type' => 'select', 'required' => true],
            ['name' => 'document_issue_date', 'type' => 'date', 'required' => true, 'max_date' => 'today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function prefillFor(User $user): array
    {
        $name = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        return array_filter([
            'first_name' => mb_substr($name[0] ?? '', 0, LepoCardOnboarding::NAME_MAX_LENGTH),
            'last_name'  => mb_substr($name[1] ?? '', 0, LepoCardOnboarding::NAME_MAX_LENGTH),
            'email'      => trim((string) $user->email),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @return array<string, string>
     */
    private function answersOf(Cardholder $cardholder): array
    {
        $data = is_array($cardholder->verification_data) ? $cardholder->verification_data : [];
        $answers = is_array($data['questionnaire'] ?? null) ? $data['questionnaire'] : [];

        $prefill = [];
        foreach (self::QUESTIONNAIRE_KEYS as $field => $key) {
            if (is_string($answers[$key] ?? null) && $answers[$key] !== '') {
                $prefill[$field] = $answers[$key];
            }
        }

        return $prefill;
    }

    /**
     * Lepo's USD bands ("10001-30000", "250001+") as select options.
     *
     * @param  list<string>  $bands
     * @return list<array{value: string, label: string}>
     */
    private static function bands(array $bands): array
    {
        $money = static fn (string $amount): string => '$' . number_format((int) $amount);

        return array_map(static function (string $band) use ($money): array {
            if (str_ends_with($band, '+')) {
                // "250001+" means more than 250,000.
                $label = 'More than ' . $money((string) ((int) rtrim($band, '+') - 1));
            } else {
                [$from, $to] = explode('-', $band, 2);
                $label = (int) $from === 0 ? 'Up to ' . $money($to) : $money($from) . ' to ' . $money($to);
            }

            return ['value' => $band, 'label' => $label];
        }, $bands);
    }
}
