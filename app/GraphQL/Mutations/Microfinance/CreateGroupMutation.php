<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Microfinance;

use App\Domain\Microfinance\Models\Group;
use App\Domain\Microfinance\Services\GroupLendingService;
use App\Domain\Microfinance\Support\MicrofinanceAccess;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class CreateGroupMutation
{
    public function __construct(
        private readonly GroupLendingService $groupLendingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Group
    {
        // Groups are set up by platform operators.
        if (! MicrofinanceAccess::isOperator(Auth::user())) {
            throw new AuthorizationException('You are not authorized to create groups.');
        }

        return $this->groupLendingService->createGroup(
            name: $args['name'],
            meetingFrequency: $args['meeting_frequency'],
            centerName: $args['center_name'] ?? null,
            meetingDay: $args['meeting_day'] ?? null,
        );
    }
}
