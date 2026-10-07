<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Commerce;

use App\Domain\Commerce\Models\Merchant;
use App\Domain\Commerce\Services\MerchantOnboardingService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class SuspendMerchantMutation
{
    public function __construct(
        private readonly MerchantOnboardingService $merchantOnboardingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Merchant
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to manage merchants.');
        }

        $this->merchantOnboardingService->suspend(
            $args['id'],
            'Suspended via GraphQL',
        );

        /** @var Merchant */
        return Merchant::findOrFail($args['id']);
    }
}
