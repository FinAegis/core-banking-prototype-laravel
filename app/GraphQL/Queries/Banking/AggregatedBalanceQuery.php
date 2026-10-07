<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Banking;

use App\Domain\Banking\Models\BankAccountModel;
use App\Domain\Banking\Services\BankIntegrationService;
use App\Models\User;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class AggregatedBalanceQuery
{
    public function __construct(
        private readonly BankIntegrationService $bankIntegrationService,
    ) {
    }

    /**
     * Resolve aggregated balance across all bank accounts for a user.
     *
     * @param  array<string, mixed>  $args
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Only the caller's own bank accounts can be aggregated.
        if (! hash_equals((string) $user->uuid, (string) $args['user_uuid'])) {
            throw new AuthorizationException('You are not authorized to view these balances.');
        }

        // Aggregate from bank accounts directly
        $accounts = BankAccountModel::query()
            ->where('user_uuid', (string) $user->uuid)
            ->where('status', 'active')
            ->get();

        return $accounts->groupBy('currency')->map(function ($group, $currency) use ($user) {
            $balance = 0.0;

            try {
                $balance = $this->bankIntegrationService->getAggregatedBalance($user, (string) $currency);
            } catch (Exception $e) {
                Log::warning('Failed to fetch aggregated balance from service', [
                    'user_uuid' => $user->uuid,
                    'currency'  => $currency,
                    'error'     => $e->getMessage(),
                ]);
            }

            return [
                'currency'      => (string) $currency,
                'total_balance' => $balance,
                'account_count' => $group->count(),
            ];
        })->values()->all();
    }
}
