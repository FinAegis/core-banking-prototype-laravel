<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Cgo;

use App\Domain\Cgo\Models\CgoInvestment;
use App\Domain\Cgo\Services\CgoKycService;
use App\Infrastructure\Domain\DomainManager;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateInvestmentMutation
{
    public function __construct(
        private readonly CgoKycService $cgoKycService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): CgoInvestment
    {
        if (DomainManager::isDisabledByConfig('Cgo')) {
            throw new Error('This operation is not available.');
        }

        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $investmentUuid = Str::uuid()->toString();

        // Create investment record (event sourced via projectors).
        /** @var CgoInvestment $investment */
        $investment = CgoInvestment::create([
            'uuid'           => $investmentUuid,
            'user_id'        => $user->id,
            'amount'         => $args['amount'],
            'currency'       => $args['currency'] ?? 'USD',
            'payment_method' => $args['payment_method'] ?? 'stripe',
            'status'         => CgoInvestment::STATUS_PENDING,
        ]);

        // Verify KYC requirements via the service layer.
        $this->cgoKycService->checkKycRequirements($investment);

        return $investment->fresh() ?? $investment;
    }
}
