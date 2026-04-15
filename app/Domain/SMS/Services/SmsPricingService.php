<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use App\Domain\SMS\Clients\VertexSmsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Converts VertexSMS EUR rates to atomic USDC pricing.
 *
 * Pricing math is bcmath-based throughout (CLAUDE.md: never `(float)` for money).
 * Floats from JSON deserialization are converted to fixed-precision strings at
 * the boundary via `floatToNumericString()`.
 */
class SmsPricingService
{
    /**
     * Atomic USDC has 6 decimals: $1.00 = 1_000_000.
     */
    private const USDC_DECIMALS = 6;

    /**
     * Minimum price floor in atomic USDC ($0.001).
     */
    private const USDC_FLOOR = '1000';

    public function __construct(
        private readonly VertexSmsClient $client,
    ) {
    }

    /**
     * Authoritative price for sending a message. Tries Vertex's `/sms/cost`
     * first; falls back to country-level estimation if the cost endpoint is
     * unreachable. Always returns the same shape so the service layer doesn't
     * have to care which path was taken.
     *
     * @return array{amount_usdc: string, parts: int, country_code: string, mcc: ?string, mnc: ?string, source: 'cost-estimate'|'fallback'}
     */
    public function priceFor(string $to, string $from, string $message): array
    {
        try {
            $cost = $this->client->estimateCost($to, $from, $message);

            return [
                'amount_usdc' => $this->eurTotalToAtomicUsdc(
                    $this->resolveTotalEur($cost['total_price_eur'], $cost['price_per_part_eur'], $cost['parts']),
                    $cost['parts'],
                ),
                'parts'        => $cost['parts'],
                'country_code' => $cost['country_iso'] !== '' ? $cost['country_iso'] : 'US',
                'mcc'          => $cost['mcc'],
                'mnc'          => $cost['mnc'],
                'source'       => 'cost-estimate',
            ];
        } catch (Throwable $e) {
            Log::warning('SmsPricing: /sms/cost failed, falling back to country-level pricing', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            $local = $this->getPriceForNumber($to);

            return [
                'amount_usdc'  => $local['amount_usdc'],
                'parts'        => $local['parts'],
                'country_code' => $local['country_code'],
                'mcc'          => null,
                'mnc'          => null,
                'source'       => 'fallback',
            ];
        }
    }

    /**
     * Country-level price estimate using Vertex's cached rate card. Used both
     * by `priceFor()` as fallback and by the public `/api/v1/sms/rates` endpoint.
     *
     * @return array{amount_usdc: string, rate_eur: string, country_code: string, parts: int}
     */
    public function getPriceForNumber(string $phoneNumber, int $parts = 1): array
    {
        $countryCode = $this->extractCountryCode($phoneNumber);
        $rateEur = $this->getRateForCountry($countryCode);

        if (bccomp($rateEur, '0', self::USDC_DECIMALS) <= 0) {
            $rateEur = '0.01';
        }

        $totalEur = bcmul($rateEur, (string) max(1, $parts), self::USDC_DECIMALS);

        return [
            'amount_usdc'  => $this->eurTotalToAtomicUsdc($totalEur, $parts),
            'rate_eur'     => bcadd($rateEur, '0', 4),
            'country_code' => $countryCode,
            'parts'        => max(1, $parts),
        ];
    }

    /**
     * @return array{country: string, country_code: string, rate_eur: string, rate_usdc: string}|null
     */
    public function getRateForDisplay(string $countryCode): ?array
    {
        $rates = $this->getCachedRates();

        foreach ($rates as $rate) {
            if (($rate['CountryCode'] ?? '') === strtoupper($countryCode)) {
                $rateEur = $this->floatToNumericString($rate['Rate'] ?? 0);
                $pricing = $this->getPriceForNumber('+' . $this->countryCodeToDialCode($countryCode) . '000000000');

                return [
                    'country'      => (string) ($rate['Country'] ?? $countryCode),
                    'country_code' => strtoupper($countryCode),
                    'rate_eur'     => bcadd($rateEur, '0', 4),
                    'rate_usdc'    => $pricing['amount_usdc'],
                ];
            }
        }

        return null;
    }

    /**
     * Resolve total EUR. Vertex sometimes returns `totalPrice = 0` on degraded
     * routes; in that case derive from `pricePerPart × parts`.
     *
     * @param  numeric-string  $totalEur
     * @param  numeric-string  $pricePerPartEur
     * @return numeric-string
     */
    private function resolveTotalEur(string $totalEur, string $pricePerPartEur, int $parts): string
    {
        if (bccomp($totalEur, '0', self::USDC_DECIMALS) > 0) {
            return $totalEur;
        }

        return bcmul($pricePerPartEur, (string) max(1, $parts), self::USDC_DECIMALS);
    }

    /**
     * Convert an EUR amount into atomic USDC, applying the configured FX
     * rate, margin multiplier, and minimum floor — all in bcmath.
     *
     * @param  numeric-string  $totalEur
     * @return numeric-string
     */
    private function eurTotalToAtomicUsdc(string $totalEur, int $parts): string
    {
        if (bccomp($totalEur, '0', self::USDC_DECIMALS) <= 0) {
            $fallbackUsdc = (string) max(1, (int) config('sms.pricing.fallback_usdc', 50000));
            $scaled = bcmul($fallbackUsdc, (string) max(1, $parts), 0);

            return bccomp($scaled, self::USDC_FLOOR) < 0 ? self::USDC_FLOOR : $scaled;
        }

        $eurUsdRate = $this->floatToNumericString(max(0.5, (float) config('sms.pricing.eur_usd_rate', 1.08)));
        $marginMultiplier = $this->floatToNumericString(max(1.0, (float) config('sms.pricing.margin_multiplier', 1.15)));

        $usd = bcmul($totalEur, $eurUsdRate, self::USDC_DECIMALS);
        $usd = bcmul($usd, $marginMultiplier, self::USDC_DECIMALS);

        $scale = (string) (10 ** self::USDC_DECIMALS);
        $atomic = bcmul($usd, $scale, 0);

        // Round up sub-microcent fractional remainders so the operator never under-charges.
        $atomicWithFraction = bcmul($usd, $scale, self::USDC_DECIMALS);
        if (bccomp($atomicWithFraction, $atomic, self::USDC_DECIMALS) > 0) {
            $atomic = bcadd($atomic, '1', 0);
        }

        return bccomp($atomic, self::USDC_FLOOR) < 0 ? self::USDC_FLOOR : $atomic;
    }

    /**
     * @return numeric-string
     */
    private function getRateForCountry(string $countryCode): string
    {
        $rates = $this->getCachedRates();

        foreach ($rates as $rate) {
            if (($rate['CountryCode'] ?? '') === strtoupper($countryCode)) {
                return $this->floatToNumericString($rate['Rate'] ?? 0);
            }
        }

        Log::debug('SmsPricing: No rate found for country, using fallback', [
            'country_code' => $countryCode,
        ]);

        // Derive an EUR rate from the configured fallback USDC: reverse the
        // FX × margin × atomic-conversion to get back to EUR.
        $fallbackUsdc = (string) max(1, (int) config('sms.pricing.fallback_usdc', 50000));
        $eurUsdRate = $this->floatToNumericString(max(0.5, (float) config('sms.pricing.eur_usd_rate', 1.08)));
        $margin = $this->floatToNumericString(max(1.0, (float) config('sms.pricing.margin_multiplier', 1.15)));

        $usd = bcdiv($fallbackUsdc, (string) (10 ** self::USDC_DECIMALS), self::USDC_DECIMALS);
        $eur = bcdiv($usd, $eurUsdRate, self::USDC_DECIMALS);

        return bcdiv($eur, $margin, self::USDC_DECIMALS);
    }

    /**
     * @return array<int, array{CountryCode: string, Country: string, Operator: string, Rate: string}>
     */
    private function getCachedRates(): array
    {
        $ttl = (int) config('sms.pricing.rate_cache_ttl', 3600);

        /** @var array<int, array{CountryCode: string, Country: string, Operator: string, Rate: string}> $rates */
        $rates = Cache::remember('sms:vertexsms:rates', $ttl, fn (): array => $this->client->getRates());

        return $rates;
    }

    /**
     * Convert a JSON-decoded numeric (float|int|string) into a
     * fixed-precision numeric string suitable for bcmath.
     *
     * @return numeric-string
     */
    private function floatToNumericString(mixed $value): string
    {
        if (is_string($value) && is_numeric($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, self::USDC_DECIMALS, '.', '');
        }

        return '0';
    }

    private function extractCountryCode(string $phoneNumber): string
    {
        $number = preg_replace('/[^0-9+]/', '', $phoneNumber);

        if ($number === null || $number === '') {
            return 'US';
        }

        if (str_starts_with($number, '+')) {
            $number = substr($number, 1);
        } elseif (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        }

        // Ordered longest-first so '370' matches before '37'
        $dialCodes = [
            ['370', 'LT'], ['371', 'LV'], ['372', 'EE'], ['358', 'FI'],
            ['353', 'IE'], ['351', 'PT'], ['420', 'CZ'], ['421', 'SK'],
            ['359', 'BG'], ['385', 'HR'], ['386', 'SI'], ['381', 'RS'],
            ['380', 'UA'], ['375', 'BY'], ['373', 'MD'], ['995', 'GE'],
            ['971', 'AE'], ['966', 'SA'], ['972', 'IL'],
            ['44', 'GB'], ['49', 'DE'], ['33', 'FR'], ['34', 'ES'],
            ['39', 'IT'], ['48', 'PL'], ['31', 'NL'], ['32', 'BE'],
            ['43', 'AT'], ['46', 'SE'], ['47', 'NO'], ['45', 'DK'],
            ['36', 'HU'], ['40', 'RO'], ['30', 'GR'], ['90', 'TR'],
            ['61', 'AU'], ['81', 'JP'], ['82', 'KR'], ['86', 'CN'],
            ['91', 'IN'], ['55', 'BR'], ['52', 'MX'], ['27', 'ZA'],
            ['65', 'SG'], ['60', 'MY'], ['66', 'TH'], ['62', 'ID'],
            ['63', 'PH'], ['84', 'VN'], ['41', 'CH'],
            ['7', 'RU'], ['1', 'US'],
        ];

        foreach ($dialCodes as [$dial, $country]) {
            if (str_starts_with($number, $dial)) {
                return $country;
            }
        }

        return 'US';
    }

    private function countryCodeToDialCode(string $countryCode): string
    {
        $map = [
            'LT' => '370', 'LV' => '371', 'EE' => '372',
            'GB' => '44',  'DE' => '49',  'FR' => '33',
            'US' => '1',   'ES' => '34',  'IT' => '39',
            'PL' => '48',  'NL' => '31',  'BE' => '32',
            'SE' => '46',  'NO' => '47',  'DK' => '45',
            'FI' => '358', 'IE' => '353', 'PT' => '351',
            'CH' => '41',  'AT' => '43',
        ];

        return $map[strtoupper($countryCode)] ?? '1';
    }
}
