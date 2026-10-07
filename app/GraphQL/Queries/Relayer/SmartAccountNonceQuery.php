<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Relayer;

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Relayer\Services\SmartAccountService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

final class SmartAccountNonceQuery
{
    public function __construct(
        private readonly SmartAccountService $smartAccountService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        /** @var SmartAccount $account */
        $account = SmartAccount::query()
            ->whereKey($args['account_id'])
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $nonceInfo = $this->smartAccountService->getNonceInfo(
            $account->owner_address,
            $account->network,
        );

        return [
            'nonce'       => $nonceInfo['nonce'],
            'pending_ops' => $nonceInfo['pending_ops'],
        ];
    }
}
