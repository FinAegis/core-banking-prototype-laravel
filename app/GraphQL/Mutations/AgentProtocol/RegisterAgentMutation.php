<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\AgentProtocol;

use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RegisterAgentMutation
{
    public function __construct(
        private readonly AgentRegistryService $agentRegistryService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Agent
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $agentId = Str::uuid()->toString();

        $agent = $this->agentRegistryService->registerAgent([
            'agentId'      => $agentId,
            'did'          => 'did:finaegis:' . $agentId,
            'name'         => $args['name'],
            'type'         => $args['type'] ?? 'standard',
            'capabilities' => $args['capabilities'] ?? [],
            'ownerUserId'  => (int) $user->getAuthIdentifier(),
            'metadata'     => [
                'registered_by' => (string) $user->id,
            ],
        ]);

        // Return the read-model projection or create a fallback record.
        /** @var Agent $result */
        $result = Agent::where('agent_id', $agentId)->first() ?? $agent;

        return $result;
    }
}
