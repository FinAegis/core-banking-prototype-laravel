<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Models\Agent;
use Workflow\Activity;

class UpdateAgentStatusActivity extends Activity
{
    /**
     * Update agent status.
     */
    public function execute(string $agentId, string $status): array
    {
        $agent = Agent::where('agent_id', $agentId)->first();

        if (! $agent) {
            return [
                'success' => false,
                'message' => 'Agent not found',
            ];
        }

        $agent->update(['status' => $status]);

        return [
            'success'    => true,
            'agent_id'   => $agentId,
            'new_status' => $status,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
