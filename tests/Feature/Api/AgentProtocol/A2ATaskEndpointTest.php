<?php

declare(strict_types=1);

use App\Domain\AgentProtocol\Enums\A2ATaskState;
use App\Domain\AgentProtocol\Models\A2ATask;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('A2A Task Endpoints', function (): void {
    it('creates a task via tasks/send', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/agent-protocol/tasks/send', [
            'sender_did' => 'did:finaegis:agent:sender123',
            'skill_id'   => 'payment.initiate',
            'input'      => ['amount' => 100, 'currency' => 'USD'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.state', 'submitted');
        $response->assertJsonPath('data.sender_did', 'did:finaegis:agent:sender123');
        $response->assertJsonPath('data.skill_id', 'payment.initiate');

        $this->assertDatabaseHas('a2a_tasks', [
            'sender_did' => 'did:finaegis:agent:sender123',
            'skill_id'   => 'payment.initiate',
            'state'      => 'submitted',
        ]);
    });

    it('retrieves a task via tasks/get', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $task = A2ATask::create([
            'sender_did'   => 'did:finaegis:agent:retrieve-sender',
            'receiver_did' => 'did:finaegis:agent:receiver',
            'state'        => A2ATaskState::WORKING,
            'skill_id'     => 'data.query',
        ]);

        $response = $this->getJson("/api/agent-protocol/tasks/{$task->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $task->id);
        $response->assertJsonPath('data.state', 'working');
        $response->assertJsonPath('data.sender_did', 'did:finaegis:agent:retrieve-sender');
    });

    it('cancels a task', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $task = A2ATask::create([
            'sender_did'   => 'did:finaegis:agent:cancel-sender',
            'receiver_did' => 'did:finaegis:agent:receiver',
            'state'        => A2ATaskState::WORKING,
            'skill_id'     => 'compute.run',
        ]);

        $response = $this->postJson("/api/agent-protocol/tasks/{$task->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.state', 'canceled');

        $this->assertDatabaseHas('a2a_tasks', [
            'id'    => $task->id,
            'state' => 'canceled',
        ]);
    });

    it('rejects cancel on terminal task', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $task = A2ATask::create([
            'sender_did'   => 'did:finaegis:agent:terminal-sender',
            'receiver_did' => 'did:finaegis:agent:receiver',
            'state'        => A2ATaskState::COMPLETED,
            'skill_id'     => 'report.generate',
        ]);

        $response = $this->postJson("/api/agent-protocol/tasks/{$task->id}/cancel");

        $response->assertUnprocessable();
        $response->assertJsonPath('success', false);
    });

    it('requires authentication', function (): void {
        $response = $this->postJson('/api/agent-protocol/tasks/send', [
            'sender_did' => 'did:finaegis:agent:sender123',
            'skill_id'   => 'payment.initiate',
        ]);

        $response->assertUnauthorized();
    });
});
