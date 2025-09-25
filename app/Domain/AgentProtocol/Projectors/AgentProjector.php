<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Projectors;

use App\Domain\AgentProtocol\Events\AgentCapabilityAdded;
use App\Domain\AgentProtocol\Events\AgentCapabilityRemoved;
use App\Domain\AgentProtocol\Events\AgentEndpointUpdated;
use App\Domain\AgentProtocol\Events\AgentRegistered;
use App\Domain\AgentProtocol\Events\AgentStatusChanged;
use App\Domain\AgentProtocol\Events\AgentVerified;
use App\Domain\AgentProtocol\Models\Agent;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Projector to maintain Agent read models from events.
 */
class AgentProjector extends Projector
{
    /**
     * Handle agent registration event.
     */
    public function onAgentRegistered(AgentRegistered $event): void
    {
        Agent::create([
            'agent_id'         => $event->agentId,
            'did'              => $event->did,
            'name'             => $event->name,
            'type'             => $event->type,
            'status'           => 'pending',
            'network_id'       => $event->networkId,
            'organization'     => $event->organization,
            'endpoints'        => $event->endpoints,
            'capabilities'     => $event->capabilities,
            'metadata'         => $event->metadata,
            'relay_score'      => 0.0,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Handle agent status change event.
     */
    public function onAgentStatusChanged(AgentStatusChanged $event): void
    {
        $agent = Agent::where('agent_id', $event->agentId)->first();
        if ($agent) {
            $agent->status = $event->newStatus;
            $agent->metadata = array_merge($agent->metadata ?? [], [
                'status_changed_at'     => $event->changedAt->toIso8601String(),
                'status_changed_reason' => $event->reason,
            ]);
            $agent->save();
        }
    }

    /**
     * Handle agent capability added event.
     */
    public function onAgentCapabilityAdded(AgentCapabilityAdded $event): void
    {
        $agent = Agent::where('agent_id', $event->agentId)->first();
        if ($agent) {
            $capabilities = $agent->capabilities ?? [];
            if (! in_array($event->capability, $capabilities, true)) {
                $capabilities[] = $event->capability;
                $agent->capabilities = $capabilities;
                $agent->save();
            }
        }
    }

    /**
     * Handle agent capability removed event.
     */
    public function onAgentCapabilityRemoved(AgentCapabilityRemoved $event): void
    {
        $agent = Agent::where('agent_id', $event->agentId)->first();
        if ($agent) {
            $capabilities = $agent->capabilities ?? [];
            $capabilities = array_values(array_filter(
                $capabilities,
                fn ($cap) => $cap !== $event->capability
            ));
            $agent->capabilities = $capabilities;
            $agent->save();
        }
    }

    /**
     * Handle agent endpoint update event.
     */
    public function onAgentEndpointUpdated(AgentEndpointUpdated $event): void
    {
        $agent = Agent::where('agent_id', $event->agentId)->first();
        if ($agent) {
            $endpoints = $agent->endpoints ?? [];
            $endpoints[$event->endpointType] = $event->endpointUrl;
            $agent->endpoints = $endpoints;
            $agent->save();
        }
    }

    /**
     * Handle agent verification event.
     */
    public function onAgentVerified(AgentVerified $event): void
    {
        $agent = Agent::where('agent_id', $event->agentId)->first();
        if ($agent) {
            $agent->status = 'active';
            $agent->metadata = array_merge($agent->metadata ?? [], [
                'verified_at'         => $event->verifiedAt->toIso8601String(),
                'verification_method' => $event->verificationMethod,
                'verification_level'  => $event->verificationLevel,
            ]);
            $agent->save();
        }
    }
}
