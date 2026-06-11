<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Domain\SMS\Services\SmsPricingService;

final class SmsRates
{
    public function __construct(
        private readonly SmsPricingService $pricing,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{country: string, country_code: string, rate_eur: string, rate_usdc: string}|null
     */
    public function __invoke(mixed $rootValue, array $args): ?array
    {
        return $this->pricing->getRateForDisplay((string) $args['country']);
    }
}
