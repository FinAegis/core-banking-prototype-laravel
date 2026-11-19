<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\OpenBanking;

use App\Domain\OpenBanking\Services\ConsentService;
use Illuminate\Support\Facades\Auth;

final class ConsentQuery
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|null
     */
    public function __invoke(mixed $rootValue, array $args): ?array
    {
        // Owner only: another user's consent resolves to null like an unknown id.
        $consent = $this->consentService->getConsent((string) $args['id'], (int) Auth::id());

        return $consent?->toArray();
    }
}
