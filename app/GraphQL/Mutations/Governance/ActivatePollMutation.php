<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Governance;

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Services\GovernanceService;
use App\Domain\Governance\Support\PollAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class ActivatePollMutation
{
    public function __construct(
        private readonly GovernanceService $governanceService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Poll
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        /** @var Poll $poll */
        $poll = Poll::findOrFail($args['id']);

        // The poll's creator or an operator; operators only when the poll
        // has an execution workflow.
        if (! PollAccess::mayManage($user, $poll)) {
            throw new AuthorizationException('You are not authorized to manage this poll.');
        }

        $this->governanceService->activatePoll($poll);

        return $poll->refresh();
    }
}
