<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\Enums\A2ATaskState;
use App\Domain\AgentProtocol\Events\EscrowCreated;
use App\Domain\AgentProtocol\Models\A2ATask;
use App\Domain\AgentProtocol\Services\AgentAuthenticationService;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\MCP\Tools\Account\AccountBalanceTool;
use App\Domain\AI\MCP\Tools\Exchange\TradeTool;
use App\Domain\AI\MCP\Tools\Payment\PaymentStatusTool;
use App\Domain\AI\MCP\Tools\Payment\TransferTool;
use App\Domain\AI\MCP\Tools\SMS\SmsSendTool;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Asset\Models\Asset;
use App\Domain\Exchange\Services\ExchangeService;
use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;
use App\Domain\MCP\Server\McpToolCatalog;
use App\Domain\Payment\Services\TransferService;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Domain\SMS\Services\SmsService;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\AgentSession;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

/*
| GHSA-385w-q6j4-5qw6, Agent Protocol and MCP cluster:
|  - C12: Agent Protocol agents are bound to the user who registered them
|    (API keys, sessions, A2A tasks) and agent-authenticated routes bind
|    the acting agent ({did}, escrow parties, reputation reviewer);
|  - C59: MCP account tools are owner-only;
|  - C28/C60: MCP exchange.trade follows TRADING_ENABLED, ops:verify-env
|    names stale explicit MCP_TOOL_* values;
|  - C61: MCP sms.send applies the REST input rules and a per-minute limit;
|  - C49: the registry metadata advertises the tools production exposes.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

function ghsaAgentsUser(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);
    }

    return $user;
}

/**
 * An active Agent Protocol agent with a well-formed DID.
 *
 * @param  array<model-property<Agent>, mixed>  $attributes
 */
function ghsaAgentsAgent(?User $owner = null, array $attributes = []): Agent
{
    $agent = Agent::factory()->create([
        'did'           => 'did:finaegis:agent:' . bin2hex(random_bytes(16)),
        'status'        => 'active',
        'capabilities'  => ['payments', 'escrow', 'messages', 'reputation', 'profile_management'],
        'owner_user_id' => $owner?->id,
    ]);

    if ($attributes !== []) {
        $agent->forceFill($attributes)->save();
    }

    return $agent;
}

/**
 * @param  list<string>  $scopes
 * @return array<string, string>
 */
function ghsaAgentsKeyHeader(Agent $agent, array $scopes): array
{
    $key = app(AgentAuthenticationService::class)->generateApiKey($agent, 'test key', $scopes);

    return ['Authorization' => 'AgentKey ' . $key['api_key']];
}

/**
 * Stored events of the Agent Protocol aggregates (the testing setup writes
 * them to stored_events; agent_protocol_events is the production table).
 * Request tracing spans are left out.
 */
function ghsaAgentsEventCount(): int
{
    return DB::table('stored_events')->where('event_class', 'not like', 'span%')->count()
        + DB::table('agent_protocol_events')->count();
}

/**
 * A funded escrow between the buyer and the seller, optionally naming a
 * dispute resolver.
 */
function ghsaAgentsEscrow(Agent $buyer, Agent $seller, ?Agent $resolver = null): string
{
    $escrowId = 'escrow-' . Str::uuid()->toString();

    $aggregate = EscrowAggregate::retrieve($escrowId);
    $aggregate->recordThat(new EscrowCreated(
        escrowId: $escrowId,
        transactionId: 'txn-' . Str::uuid()->toString(),
        senderAgentId: $buyer->agent_id,
        receiverAgentId: $seller->agent_id,
        amount: 100.0,
        currency: 'USD',
        conditions: [],
        expiresAt: now()->addDay()->toIso8601String(),
        metadata: [
            'buyer_did'              => $buyer->did,
            'seller_did'             => $seller->did,
            'dispute_resolution_did' => $resolver?->did,
        ],
    ));
    $aggregate->persist();

    return $escrowId;
}

describe('C12: agent API keys and sessions belong to the agent owner', function (): void {
    it('does not let another user mint an API key for an agent it does not own', function (): void {
        $agent = ghsaAgentsAgent(ghsaAgentsUser());
        Sanctum::actingAs(ghsaAgentsUser(), ['read', 'write', 'delete']);

        $this->postJson("/api/agent-protocol/agents/{$agent->did}/api-keys", ['name' => 'stolen', 'scopes' => ['payments:read']])
            ->assertNotFound()
            ->assertJsonPath('code', 'AGENT_NOT_FOUND');

        expect(AgentApiKey::query()->where('agent_id', $agent->agent_id)->count())->toBe(0);
    });

    it('lets the owner mint, list and revoke API keys', function (): void {
        $owner = ghsaAgentsUser();
        $agent = ghsaAgentsAgent($owner);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $keyId = $this->postJson("/api/agent-protocol/agents/{$agent->did}/api-keys", ['name' => 'mine', 'scopes' => ['payments:read']])
            ->assertCreated()
            ->json('data.key_id');

        $this->getJson("/api/agent-protocol/agents/{$agent->did}/api-keys")
            ->assertOk()
            ->assertJsonPath('meta.count', 1);

        $this->deleteJson("/api/agent-protocol/agents/{$agent->did}/api-keys/{$keyId}")->assertOk();

        expect(AgentApiKey::query()->where('key_id', $keyId)->value('is_active'))->toBeFalsy();
    });

    it('does not let another user list or revoke the owner\'s keys', function (): void {
        $owner = ghsaAgentsUser();
        $agent = ghsaAgentsAgent($owner);
        $key = app(AgentAuthenticationService::class)->generateApiKey($agent, 'owner key', ['payments:read']);
        Sanctum::actingAs(ghsaAgentsUser(), ['read', 'write', 'delete']);

        $this->getJson("/api/agent-protocol/agents/{$agent->did}/api-keys")
            ->assertNotFound()
            ->assertJsonMissingPath('data.keys');
        $this->deleteJson("/api/agent-protocol/agents/{$agent->did}/api-keys/{$key['key_id']}")->assertNotFound();

        expect(AgentApiKey::query()->where('key_id', $key['key_id'])->value('is_active'))->toBeTruthy();
    });

    it('does not let another user list or revoke the agent sessions', function (): void {
        $owner = ghsaAgentsUser();
        $agent = ghsaAgentsAgent($owner);
        $service = app(AgentAuthenticationService::class);
        $service->authenticateWithApiKey($service->generateApiKey($agent, 'owner key', ['payments:read'])['api_key']);
        Sanctum::actingAs(ghsaAgentsUser(), ['read', 'write', 'delete']);

        $this->getJson("/api/agent-protocol/agents/{$agent->did}/sessions")
            ->assertNotFound()
            ->assertJsonMissingPath('data.sessions');
        $this->deleteJson("/api/agent-protocol/agents/{$agent->did}/sessions")->assertNotFound();

        expect(AgentSession::query()->where('agent_id', $agent->agent_id)->where('is_revoked', false)->count())->toBe(1);
    });

    it('lets the owner list and revoke the agent sessions', function (): void {
        $owner = ghsaAgentsUser();
        $agent = ghsaAgentsAgent($owner);
        $service = app(AgentAuthenticationService::class);
        $service->authenticateWithApiKey($service->generateApiKey($agent, 'owner key', ['payments:read'])['api_key']);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson("/api/agent-protocol/agents/{$agent->did}/sessions")
            ->assertOk()
            ->assertJsonPath('meta.count', 1);
        $this->deleteJson("/api/agent-protocol/agents/{$agent->did}/sessions")
            ->assertOk()
            ->assertJsonPath('data.revoked_count', 1);

        expect(AgentSession::query()->where('agent_id', $agent->agent_id)->where('is_revoked', false)->count())->toBe(0);
    });

    // Legacy and platform agents have no owner_user_id: only operators may
    // manage them (fail closed for everyone else).
    it('keeps platform agents without an owner to staff', function (): void {
        $agent = ghsaAgentsAgent();
        $service = app(AgentAuthenticationService::class);
        $service->authenticateWithApiKey($service->generateApiKey($agent, 'platform key', ['payments:read'])['api_key']);

        Sanctum::actingAs(ghsaAgentsUser(), ['read', 'write', 'delete']);
        $this->postJson("/api/agent-protocol/agents/{$agent->did}/api-keys", ['name' => 'k'])->assertNotFound();
        $this->deleteJson("/api/agent-protocol/agents/{$agent->did}/sessions")->assertNotFound();
        expect(AgentApiKey::query()->where('agent_id', $agent->agent_id)->count())->toBe(1)
            ->and(AgentSession::query()->where('agent_id', $agent->agent_id)->where('is_revoked', false)->count())->toBe(1);

        Sanctum::actingAs(ghsaAgentsUser('bank_admin'), ['read', 'write', 'delete']);
        $this->postJson("/api/agent-protocol/agents/{$agent->did}/api-keys", ['name' => 'k'])->assertCreated();
        $this->deleteJson("/api/agent-protocol/agents/{$agent->did}/sessions")->assertOk();
        expect(AgentApiKey::query()->where('agent_id', $agent->agent_id)->count())->toBe(2)
            ->and(AgentSession::query()->where('agent_id', $agent->agent_id)->where('is_revoked', false)->count())->toBe(0);
    });

    it('keeps the wildcard and admin scopes to staff', function (string $scope): void {
        $owner = ghsaAgentsUser();
        $agent = ghsaAgentsAgent($owner);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson("/api/agent-protocol/agents/{$agent->did}/api-keys", ['name' => 'k', 'scopes' => [$scope]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scopes.0']);
        expect(AgentApiKey::query()->where('agent_id', $agent->agent_id)->count())->toBe(0);

        Sanctum::actingAs(ghsaAgentsUser('admin'), ['read', 'write', 'delete']);
        $this->postJson("/api/agent-protocol/agents/{$agent->did}/api-keys", ['name' => 'k', 'scopes' => [$scope]])
            ->assertCreated();
        expect(AgentApiKey::query()->where('agent_id', $agent->agent_id)->count())->toBe(1);
    })->with(['*', 'admin:*', 'admin:manage']);

    it('records the registering user as the owner of a GraphQL-registered agent', function (): void {
        $owner = ghsaAgentsUser();

        $this->actingAs($owner, 'sanctum')->postJson('/graphql', [
            'query' => 'mutation { registerAgent(input: {name: "Mine"}) { agent_id } }',
        ])->assertOk()->assertJsonMissingPath('errors');

        $agent = Agent::query()->where('name', 'Mine')->first();
        expect($agent?->owner_user_id)->toBe($owner->id);

        Sanctum::actingAs(ghsaAgentsUser(), ['read', 'write', 'delete']);
        $this->postJson("/api/agent-protocol/agents/{$agent?->did}/api-keys", ['name' => 'k'])->assertNotFound();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson("/api/agent-protocol/agents/{$agent?->did}/api-keys", ['name' => 'k'])->assertCreated();
    });

    it('backfills owners from metadata.registered_by, but not for AI bridge agents', function (): void {
        $owner = ghsaAgentsUser();
        $registered = ghsaAgentsAgent(null, ['metadata' => ['registered_by' => (string) $owner->id]]);
        $bridged = ghsaAgentsAgent(null, ['metadata' => ['registered_by' => (string) $owner->id, 'ai_domain_source' => true]]);

        $migration = require base_path('database/migrations/2026_10_21_000001_add_owner_user_id_to_agents_table.php');
        $migration->up();

        expect($registered->fresh()?->owner_user_id)->toBe($owner->id)
            ->and($bridged->fresh()?->owner_user_id)->toBeNull();
    });
});

describe('C12: agent-authenticated routes bind {did} to the authenticated agent', function (): void {
    it('does not let an agent send a payment as another agent', function (): void {
        $attacker = ghsaAgentsAgent(ghsaAgentsUser());
        $victim = ghsaAgentsAgent(ghsaAgentsUser());
        $headers = ghsaAgentsKeyHeader($attacker, ['payments:read', 'payments:write']);
        $before = ghsaAgentsEventCount();

        $this->withHeaders($headers)->postJson("/api/agent-protocol/agents/{$victim->did}/payments", [
            'to_agent_did'    => $attacker->did,
            'amount'          => 10,
            'currency'        => 'USD',
            'escrow_required' => false,
        ])->assertForbidden()->assertJsonPath('code', 'AGENT_DID_MISMATCH');

        expect(ghsaAgentsEventCount())->toBe($before);
    });

    it('lets an agent send a payment as itself', function (): void {
        $sender = ghsaAgentsAgent(ghsaAgentsUser());
        $receiver = ghsaAgentsAgent(ghsaAgentsUser());
        $headers = ghsaAgentsKeyHeader($sender, ['payments:read', 'payments:write']);
        $before = ghsaAgentsEventCount();

        $this->withHeaders($headers)->postJson("/api/agent-protocol/agents/{$sender->did}/payments", [
            'to_agent_did'    => $receiver->did,
            'amount'          => 10,
            'currency'        => 'USD',
            'escrow_required' => false,
        ])->assertCreated()->assertJsonPath('data.from_agent_did', $sender->did);

        expect(ghsaAgentsEventCount())->toBeGreaterThan($before);
    });

    it('does not let an agent read another agent\'s inbox or rewrite its capabilities', function (): void {
        $attacker = ghsaAgentsAgent(ghsaAgentsUser());
        $victim = ghsaAgentsAgent(ghsaAgentsUser());
        $headers = ghsaAgentsKeyHeader($attacker, ['messages:read', 'messages:write']);
        $before = ghsaAgentsEventCount();

        $this->withHeaders($headers)->getJson("/api/agent-protocol/agents/{$victim->did}/messages")
            ->assertForbidden()->assertJsonPath('code', 'AGENT_DID_MISMATCH');
        $this->withHeaders($headers)->putJson("/api/agent-protocol/agents/{$victim->did}/capabilities", ['capabilities' => ['payments']])
            ->assertForbidden()->assertJsonPath('code', 'AGENT_DID_MISMATCH');
        $this->withHeaders($headers)->postJson("/api/agent-protocol/agents/{$victim->did}/protocol/negotiate", ['target_did' => $attacker->did])
            ->assertForbidden()->assertJsonPath('code', 'AGENT_DID_MISMATCH');

        expect(ghsaAgentsEventCount())->toBe($before)
            ->and($victim->fresh()?->capabilities)->toBe($victim->capabilities);
    });

    it('lets an agent read its own inbox', function (): void {
        $agent = ghsaAgentsAgent(ghsaAgentsUser());

        $this->withHeaders(ghsaAgentsKeyHeader($agent, ['messages:read', 'messages:write']))
            ->getJson("/api/agent-protocol/agents/{$agent->did}/messages")
            ->assertOk();
    });

    it('does not let an agent submit feedback in another agent\'s name', function (): void {
        $reviewer = ghsaAgentsAgent(ghsaAgentsUser());
        $impersonated = ghsaAgentsAgent(ghsaAgentsUser());
        $target = ghsaAgentsAgent(ghsaAgentsUser());
        $headers = ghsaAgentsKeyHeader($reviewer, ['reputation:read', 'reputation:write']);
        $before = ghsaAgentsEventCount();

        $this->withHeaders($headers)->postJson("/api/agent-protocol/agents/{$target->did}/reputation/feedback", [
            'reviewer_did'   => $impersonated->did,
            'transaction_id' => 'txn-1',
            'outcome'        => 'failed',
        ])->assertForbidden();

        expect(ghsaAgentsEventCount())->toBe($before);

        $this->withHeaders($headers)->postJson("/api/agent-protocol/agents/{$target->did}/reputation/feedback", [
            'reviewer_did'   => $reviewer->did,
            'transaction_id' => 'txn-1',
            'outcome'        => 'success',
        ])->assertOk();

        expect(ghsaAgentsEventCount())->toBeGreaterThan($before);
    });
});

describe('C12: escrow actions are limited to the escrow parties', function (): void {
    it('does not let a third agent read, release or resolve an escrow', function (): void {
        $buyer = ghsaAgentsAgent(ghsaAgentsUser());
        $seller = ghsaAgentsAgent(ghsaAgentsUser());
        $resolver = ghsaAgentsAgent(ghsaAgentsUser());
        $intruder = ghsaAgentsAgent(ghsaAgentsUser());
        $escrowId = ghsaAgentsEscrow($buyer, $seller, $resolver);
        $headers = ghsaAgentsKeyHeader($intruder, ['escrow:read', 'escrow:write']);
        $before = ghsaAgentsEventCount();

        $this->withHeaders($headers)->getJson("/api/agent-protocol/escrow/{$escrowId}")->assertNotFound();
        $this->withHeaders($headers)->postJson("/api/agent-protocol/escrow/{$escrowId}/fund", ['funder_did' => $buyer->did])->assertForbidden();
        $this->withHeaders($headers)->postJson("/api/agent-protocol/escrow/{$escrowId}/release", ['releaser_did' => $buyer->did])->assertForbidden();
        $this->withHeaders($headers)->postJson("/api/agent-protocol/escrow/{$escrowId}/resolve", [
            'resolver_did'    => $intruder->did,
            'resolution_type' => 'return_to_sender',
        ])->assertForbidden();

        expect(ghsaAgentsEventCount())->toBe($before)
            ->and(EscrowAggregate::retrieve($escrowId)->getStatus())->toBe('created');
    });

    it('does not let the seller fund the escrow or the buyer name itself as funder for someone else', function (): void {
        $buyer = ghsaAgentsAgent(ghsaAgentsUser());
        $seller = ghsaAgentsAgent(ghsaAgentsUser());
        $escrowId = ghsaAgentsEscrow($buyer, $seller);
        $before = ghsaAgentsEventCount();

        $this->withHeaders(ghsaAgentsKeyHeader($seller, ['escrow:read', 'escrow:write']))
            ->postJson("/api/agent-protocol/escrow/{$escrowId}/fund", ['funder_did' => $seller->did])
            ->assertForbidden();
        $this->withHeaders(ghsaAgentsKeyHeader($buyer, ['escrow:read', 'escrow:write']))
            ->postJson("/api/agent-protocol/escrow/{$escrowId}/fund", ['funder_did' => $seller->did])
            ->assertForbidden();

        expect(ghsaAgentsEventCount())->toBe($before)
            ->and(EscrowAggregate::retrieve($escrowId)->getFundedAmount())->toBe(0.0);
    });

    it('lets the buyer read, fund and release the escrow', function (): void {
        $buyer = ghsaAgentsAgent(ghsaAgentsUser());
        $seller = ghsaAgentsAgent(ghsaAgentsUser());
        $escrowId = ghsaAgentsEscrow($buyer, $seller);
        $headers = ghsaAgentsKeyHeader($buyer, ['escrow:read', 'escrow:write']);

        $this->withHeaders($headers)->getJson("/api/agent-protocol/escrow/{$escrowId}")->assertOk();
        $this->withHeaders($headers)->postJson("/api/agent-protocol/escrow/{$escrowId}/fund", ['funder_did' => $buyer->did])
            ->assertOk()->assertJsonPath('data.status', 'funded');
        $this->withHeaders($headers)->postJson("/api/agent-protocol/escrow/{$escrowId}/release", [])
            ->assertOk()->assertJsonPath('data.status', 'released');

        expect(EscrowAggregate::retrieve($escrowId)->getReleasedBy())->toBe($buyer->did);
    });

    it('lets only the named resolver resolve a dispute', function (): void {
        $buyer = ghsaAgentsAgent(ghsaAgentsUser());
        $seller = ghsaAgentsAgent(ghsaAgentsUser());
        $resolver = ghsaAgentsAgent(ghsaAgentsUser());
        $escrowId = ghsaAgentsEscrow($buyer, $seller, $resolver);
        $aggregate = EscrowAggregate::retrieve($escrowId);
        $aggregate->deposit(100.0, $buyer->did);
        $aggregate->dispute($buyer->agent_id, 'not delivered');
        $aggregate->persist();

        $this->withHeaders(ghsaAgentsKeyHeader($seller, ['escrow:read', 'escrow:write']))
            ->postJson("/api/agent-protocol/escrow/{$escrowId}/resolve", ['resolver_did' => $seller->did, 'resolution_type' => 'release_to_receiver'])
            ->assertForbidden();
        expect(EscrowAggregate::retrieve($escrowId)->getStatus())->toBe('disputed');

        $this->withHeaders(ghsaAgentsKeyHeader($resolver, ['escrow:read', 'escrow:write']))
            ->postJson("/api/agent-protocol/escrow/{$escrowId}/resolve", ['resolver_did' => $resolver->did, 'resolution_type' => 'release_to_receiver'])
            ->assertOk();
        expect(EscrowAggregate::retrieve($escrowId)->getStatus())->toBe('resolved');
    });

    it('does not let an agent create an escrow between two other agents, or name a party as resolver', function (): void {
        $buyer = ghsaAgentsAgent(ghsaAgentsUser());
        $seller = ghsaAgentsAgent(ghsaAgentsUser());
        $intruder = ghsaAgentsAgent(ghsaAgentsUser());
        $before = ghsaAgentsEventCount();
        $body = ['buyer_did' => $buyer->did, 'seller_did' => $seller->did, 'amount' => 50, 'currency' => 'USD'];

        $this->withHeaders(ghsaAgentsKeyHeader($intruder, ['escrow:read', 'escrow:write']))
            ->postJson('/api/agent-protocol/escrow', $body)
            ->assertForbidden();
        $this->withHeaders(ghsaAgentsKeyHeader($seller, ['escrow:read', 'escrow:write']))
            ->postJson('/api/agent-protocol/escrow', $body + ['dispute_resolution_did' => $seller->did])
            ->assertStatus(400);

        expect(ghsaAgentsEventCount())->toBe($before);

        $this->withHeaders(ghsaAgentsKeyHeader($buyer, ['escrow:read', 'escrow:write']))
            ->postJson('/api/agent-protocol/escrow', $body)
            ->assertCreated();
    });

    // Review A3: a resolver owned by the buyer's or the seller's owner is not
    // a third party either.
    it('does not accept a dispute resolver that has the same owner as the buyer or the seller', function (string $side): void {
        $buyerOwner = ghsaAgentsUser();
        $sellerOwner = ghsaAgentsUser();
        $buyer = ghsaAgentsAgent($buyerOwner);
        $seller = ghsaAgentsAgent($sellerOwner);
        $resolver = ghsaAgentsAgent($side === 'buyer' ? $buyerOwner : $sellerOwner);
        $before = ghsaAgentsEventCount();

        $this->withHeaders(ghsaAgentsKeyHeader($buyer, ['escrow:read', 'escrow:write']))
            ->postJson('/api/agent-protocol/escrow', [
                'buyer_did'              => $buyer->did,
                'seller_did'             => $seller->did,
                'amount'                 => 50,
                'currency'               => 'USD',
                'dispute_resolution_did' => $resolver->did,
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'The dispute resolver cannot have the same owner as the buyer or the seller');

        expect(ghsaAgentsEventCount())->toBe($before);
    })->with(['buyer', 'seller']);

    it('accepts a dispute resolver owned by a third user, or a platform resolver without an owner', function (string $kind): void {
        $buyer = ghsaAgentsAgent(ghsaAgentsUser());
        $seller = ghsaAgentsAgent(ghsaAgentsUser());
        $resolver = ghsaAgentsAgent($kind === 'third user' ? ghsaAgentsUser() : null);

        $this->withHeaders(ghsaAgentsKeyHeader($buyer, ['escrow:read', 'escrow:write']))
            ->postJson('/api/agent-protocol/escrow', [
                'buyer_did'              => $buyer->did,
                'seller_did'             => $seller->did,
                'amount'                 => 50,
                'currency'               => 'USD',
                'dispute_resolution_did' => $resolver->did,
            ])
            ->assertCreated();
    })->with(['third user', 'platform']);
});

describe('C12: A2A tasks belong to the owner of the sender agent', function (): void {
    it('does not let a user send, read, list or cancel tasks of an agent it does not own', function (): void {
        $owner = ghsaAgentsUser();
        $agent = ghsaAgentsAgent($owner);
        $task = A2ATask::create([
            'sender_did'   => $agent->did,
            'receiver_did' => 'did:finaegis:agent:receiver',
            'state'        => A2ATaskState::WORKING,
            'skill_id'     => 'data.query',
        ]);
        Sanctum::actingAs(ghsaAgentsUser(), ['read', 'write', 'delete']);

        $this->postJson('/api/agent-protocol/tasks/send', ['sender_did' => $agent->did, 'skill_id' => 'x'])->assertForbidden();
        $this->getJson("/api/agent-protocol/tasks/{$task->id}")->assertNotFound();
        $this->getJson('/api/agent-protocol/tasks?sender_did=' . urlencode($agent->did))->assertForbidden();
        $this->postJson("/api/agent-protocol/tasks/{$task->id}/cancel")->assertNotFound();

        expect(A2ATask::query()->count())->toBe(1)
            ->and($task->fresh()?->state)->toBe(A2ATaskState::WORKING);
    });

    it('lets the owner send, read, list and cancel its tasks', function (): void {
        $owner = ghsaAgentsUser();
        $agent = ghsaAgentsAgent($owner);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $taskId = $this->postJson('/api/agent-protocol/tasks/send', ['sender_did' => $agent->did, 'skill_id' => 'x'])
            ->assertCreated()
            ->json('data.id');
        $this->getJson("/api/agent-protocol/tasks/{$taskId}")->assertOk();
        $this->getJson('/api/agent-protocol/tasks?sender_did=' . urlencode($agent->did))->assertOk()->assertJsonPath('meta.count', 1);
        $this->postJson("/api/agent-protocol/tasks/{$taskId}/cancel")->assertOk()->assertJsonPath('data.state', 'canceled');
    });
});

describe('C59: MCP account tools are owner-only', function (): void {
    it('does not let a team member or an admin read another user\'s account balance', function (string $who): void {
        $owner = User::factory()->withPersonalTeam()->create();
        /** @var Team $team */
        $team = $owner->currentTeam;
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'team_id' => $team->id]);
        AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 123456]);

        $caller = ghsaAgentsUser($who === 'admin' ? 'admin' : null);
        if ($who === 'team member') {
            $team->users()->attach($caller, ['role' => 'editor']);
            $caller->forceFill(['current_team_id' => $team->id])->save();
        }
        $this->actingAs($caller);

        $result = app(AccountBalanceTool::class)->execute(['account_uuid' => $account->uuid]);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe("Account not found: {$account->uuid}")
            ->and(json_encode($result->getData()))->not->toContain('123456');
    })->with(['team member', 'admin']);

    it('lets the owner read the account balance', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 123456]);
        $this->actingAs($owner);

        $result = app(AccountBalanceTool::class)->execute(['account_uuid' => $account->uuid]);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getData()['balances'][0]['balance'] ?? null)->toBe(123456);
    });

    it('does not let an admin move money out of, or trade on, another user\'s account', function (): void {
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        $victim = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $destination = Account::factory()->create();
        AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 100000]);
        $this->actingAs(ghsaAgentsUser('admin'));

        $transfers = Mockery::mock(TransferService::class);
        $transfers->shouldNotReceive('transfer');
        $exchange = Mockery::mock(ExchangeService::class);
        $exchange->shouldNotReceive('placeOrder');

        $transfer = (new TransferTool($transfers))->execute([
            'from_account_uuid' => $account->uuid,
            'to_account_uuid'   => $destination->uuid,
            'amount'            => 10,
            'currency'          => 'USD',
        ]);
        $trade = (new TradeTool($exchange))->execute([
            'account_uuid'   => $account->uuid,
            'type'           => 'sell',
            'order_type'     => 'limit',
            'base_currency'  => 'USD',
            'quote_currency' => 'EUR',
            'amount'         => '1',
            'price'          => '1',
        ]);

        expect($transfer->isSuccess())->toBeFalse()
            ->and($transfer->getError())->toBe("Source account not found: {$account->uuid}")
            ->and($trade->isSuccess())->toBeFalse()
            ->and($trade->getError())->toBe("Account not found: {$account->uuid}")
            ->and((int) AccountBalance::query()->where('account_uuid', $account->uuid)->value('balance'))->toBe(100000);
    });

    it('lets the owner transfer from and trade on its own account', function (): void {
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true, 'is_tradeable' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true, 'is_tradeable' => true]);
        Asset::query()->whereIn('code', ['USD', 'EUR'])->update(['is_tradeable' => true]);
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $destination = Account::factory()->create();
        AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 100000]);
        $this->actingAs($owner);

        $transfers = Mockery::mock(TransferService::class);
        $transfers->shouldReceive('transfer')->once();
        $exchange = Mockery::mock(ExchangeService::class);
        $exchange->shouldReceive('placeOrder')->once()->andReturn(['order_id' => 'order-1', 'status' => 'pending']);

        $transfer = (new TransferTool($transfers))->execute([
            'from_account_uuid' => $account->uuid,
            'to_account_uuid'   => $destination->uuid,
            'amount'            => 10,
            'currency'          => 'USD',
        ]);
        $trade = (new TradeTool($exchange))->execute([
            'account_uuid'   => $account->uuid,
            'type'           => 'sell',
            'order_type'     => 'limit',
            'base_currency'  => 'USD',
            'quote_currency' => 'EUR',
            'amount'         => '1',
            'price'          => '1',
        ]);

        expect($transfer->isSuccess())->toBeTrue()
            ->and($trade->isSuccess())->toBeTrue()
            ->and($trade->getData()['order_id'] ?? null)->toBe('order-1');
    });
});

/**
 * A registry stub for the exchange.trade tool that records executions.
 *
 * @return MCPToolInterface&MockInterface
 */
describe('Review A1: MCP payment.status answers only for the signed-in user', function (): void {
    it('does not let a token for one user read another user\'s transaction by uuid or reference', function (string $lookup): void {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $transaction = TransactionProjection::factory()->create([
            'account_uuid' => $victimAccount->uuid,
            'amount'       => 987654,
            'status'       => 'completed',
            'reference'    => 'VICTIM-REF-1',
            'description'  => 'victim payment',
        ]);
        $this->actingAs(User::factory()->create());

        $result = app(PaymentStatusTool::class)->execute([
            'transaction_id' => $lookup === 'uuid' ? (string) $transaction->uuid : 'VICTIM-REF-1',
            'user_uuid'      => $victim->uuid,
        ]);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getData()['status'] ?? null)->toBe('not_found')
            ->and(json_encode($result->getData()))->not->toContain('987654')
            ->and(json_encode($result->getData()))->not->toContain('victim payment');
    })->with(['uuid', 'reference']);

    it('does not offer user_uuid in the tool input schema', function (): void {
        expect((new PaymentStatusTool())->getInputSchema()['properties'])->not->toHaveKey('user_uuid');
    });

    it('does not list another user\'s grouped transactions as related', function (): void {
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $victimAccount = Account::factory()->create(['user_uuid' => User::factory()->create()->uuid]);
        $group = (string) Str::uuid();
        $own = TransactionProjection::factory()->create(['account_uuid' => $ownerAccount->uuid, 'transaction_group_uuid' => $group]);
        $ownFee = TransactionProjection::factory()->create(['account_uuid' => $ownerAccount->uuid, 'transaction_group_uuid' => $group]);
        $theirs = TransactionProjection::factory()->create(['account_uuid' => $victimAccount->uuid, 'transaction_group_uuid' => $group]);
        $this->actingAs($owner);

        $result = app(PaymentStatusTool::class)->execute([
            'transaction_id'  => (string) $own->uuid,
            'include_related' => true,
        ]);

        $related = collect($result->getData()['related_transactions'] ?? [])->pluck('transaction_id')->all();
        expect($result->isSuccess())->toBeTrue()
            ->and($related)->toBe([(string) $ownFee->uuid])
            ->and($related)->not->toContain((string) $theirs->uuid);
    });

    it('lets the signed-in user read its own transaction by uuid and reference, even when it names itself', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $transaction = TransactionProjection::factory()->create([
            'account_uuid' => $account->uuid,
            'amount'       => 4321,
            'status'       => 'completed',
            'reference'    => 'OWNER-REF-1',
        ]);
        $this->actingAs($owner);

        $byUuid = app(PaymentStatusTool::class)->execute(['transaction_id' => (string) $transaction->uuid]);
        $byReference = app(PaymentStatusTool::class)->execute(['transaction_id' => 'OWNER-REF-1', 'user_uuid' => $owner->uuid]);

        expect($byUuid->isSuccess())->toBeTrue()
            ->and($byUuid->getData()['transaction_id'] ?? null)->toBe((string) $transaction->uuid)
            ->and($byUuid->getData()['status'] ?? null)->toBe('completed')
            ->and($byReference->getData()['transaction_id'] ?? null)->toBe((string) $transaction->uuid);
    });

    it('answers not authenticated without a signed-in user, whatever user_uuid says', function (): void {
        $victim = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $victim->uuid]);
        $transaction = TransactionProjection::factory()->create(['account_uuid' => $account->uuid]);

        $result = app(PaymentStatusTool::class)->execute([
            'transaction_id' => (string) $transaction->uuid,
            'user_uuid'      => $victim->uuid,
        ]);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('User not found or not authenticated');
    });
});

function ghsaAgentsTradeStub(): MCPToolInterface&MockInterface
{
    /** @var MCPToolInterface&MockInterface $stub */
    $stub = Mockery::mock(MCPToolInterface::class);
    $stub->shouldReceive('getName')->andReturn('exchange.trade');
    $stub->shouldReceive('getCategory')->andReturn('exchange');
    $stub->shouldReceive('getDescription')->andReturn('trade');
    $stub->shouldReceive('getInputSchema')->andReturn(['type' => 'object', 'properties' => []]);
    $stub->shouldReceive('getOutputSchema')->andReturn([]);

    return $stub;
}

function ghsaAgentsSetTool(string $tool, bool $enabled): void
{
    /** @var array<string, array<string, mixed>> $tools */
    $tools = config('mcp.tools');
    $tools[$tool]['enabled'] = $enabled;
    config(['mcp.tools' => $tools]);
}

describe('C28/C60: MCP exchange.trade follows TRADING_ENABLED', function (): void {
    beforeEach(function (): void {
        DB::table('mcp_token_policies')->insert([
            'token_id'              => 'tok_ghsa',
            'daily_limit_minor'     => 50000,
            'daily_limit_currency'  => 'USD',
            'daily_spend_minor'     => 0,
            'daily_window_start_at' => now(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
        config(['mcp.idempotency.cache_store' => 'array']);
        app()->forgetInstance(ToolRegistry::class);
        app()->singleton(ToolRegistry::class, fn () => new ToolRegistry());
        ghsaAgentsSetTool('exchange.trade', true);
    });

    it('hides and refuses exchange.trade while trading is off, even with MCP_TOOL_EXCHANGE_TRADE on', function (): void {
        config(['trading.enabled' => false]);
        $stub = ghsaAgentsTradeStub();
        $stub->shouldNotReceive('execute');
        app(ToolRegistry::class)->register($stub);
        $router = app(JsonRpcRouter::class);
        $ctx = new McpRequestContext('tok_ghsa', 'cli', 1, ['exchange:write']);

        $list = $router->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $ctx);
        $call = $router->dispatch([
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params'  => ['name' => 'exchange.trade', 'arguments' => ['idempotency_key' => (string) Str::uuid()]],
        ], $ctx);

        expect(collect($list['result']['tools'])->pluck('name')->all())->not->toContain('exchange.trade')
            ->and($call['error']['code'] ?? null)->toBe(-32004)
            ->and(McpToolCatalog::enabledTools())->not->toHaveKey('exchange.trade');
    });

    it('lists and runs exchange.trade while trading is on', function (): void {
        config(['trading.enabled' => true]);
        $stub = ghsaAgentsTradeStub();
        $stub->shouldReceive('execute')->once()->andReturn(ToolExecutionResult::success(['order_id' => 'o-1']));
        app(ToolRegistry::class)->register($stub);
        $router = app(JsonRpcRouter::class);
        $ctx = new McpRequestContext('tok_ghsa', 'cli', 1, ['exchange:write']);

        $list = $router->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $ctx);
        $call = $router->dispatch([
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params'  => ['name' => 'exchange.trade', 'arguments' => ['idempotency_key' => (string) Str::uuid()]],
        ], $ctx);

        expect(collect($list['result']['tools'])->pluck('name')->all())->toContain('exchange.trade')
            ->and($call['result']['isError'] ?? null)->toBeFalse()
            ->and(McpToolCatalog::enabledTools())->toHaveKey('exchange.trade');
    });

    it('refuses to place an order from the tool itself while trading is off', function (): void {
        config(['trading.enabled' => false]);
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $this->actingAs($owner);
        $exchange = Mockery::mock(ExchangeService::class);
        $exchange->shouldNotReceive('placeOrder');

        $result = (new TradeTool($exchange))->execute([
            'account_uuid'   => $account->uuid,
            'type'           => 'sell',
            'order_type'     => 'limit',
            'base_currency'  => 'USD',
            'quote_currency' => 'EUR',
            'amount'         => '1',
            'price'          => '1',
        ]);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('Trading is not enabled')
            ->and((new TradeTool($exchange))->authorize((string) $owner->id))->toBeFalse();
    });
});

/**
 * @return array<string, array{name: string, category: string, result: string, detail: string}>
 */
function ghsaAgentsOpsChecks(): array
{
    Artisan::call('ops:verify-env', ['--json' => true]);

    /** @var array{checks: list<array{name: string, category: string, result: string, detail: string}>} $decoded */
    $decoded = json_decode(Artisan::output(), true);

    return collect($decoded['checks'])->keyBy('name')->all();
}

describe('C28: ops:verify-env names stale explicit MCP money tool values', function (): void {
    it('warns that exchange.trade is held off by TRADING_ENABLED and that old templates set it to true', function (): void {
        config(['trading.enabled' => false]);
        ghsaAgentsSetTool('exchange.trade', true);
        ghsaAgentsSetTool('payment.transfer', true);
        app()->detectEnvironment(fn () => 'production');

        $checks = ghsaAgentsOpsChecks();

        expect($checks['mcp.tools.exchange.trade']['result'])->toBe('WARN')
            ->and($checks['mcp.tools.exchange.trade']['detail'])->toContain('stays unavailable while trading.enabled is off')
            ->and($checks['mcp.tools.exchange.trade']['detail'])->toContain('v7.16.0 and earlier')
            ->and($checks['mcp.tools.payment.transfer']['result'])->toBe('WARN')
            ->and($checks['mcp.tools.payment.transfer']['detail'])->toContain('MCP_TOOL_PAYMENT_TRANSFER is ON')
            ->and($checks['mcp.tools.payment.transfer']['detail'])->toContain('v7.16.0 and earlier');
    });

    // Review A2: sms.send is billed to the platform, so in production it
    // needs an explicit acknowledgement (same pattern as direct ledger
    // funding: <SWITCH>_ACKNOWLEDGED=true).
    it('blocks the deploy in production while MCP sms.send is on without the acknowledgement', function (): void {
        ghsaAgentsSetTool('sms.send', true);
        config(['mcp.sms_send_acknowledged' => false]);
        app()->detectEnvironment(fn () => 'production');

        $checks = ghsaAgentsOpsChecks();

        expect($checks['mcp.tools.sms.send']['result'])->toBe('FAIL')
            ->and($checks['mcp.tools.sms.send']['detail'])->toContain('MCP_TOOL_SMS_SEND is ON in production')
            ->and($checks['mcp.tools.sms.send']['detail'])->toContain('MCP_TOOL_SMS_SEND_ACKNOWLEDGED=true');
    });

    it('only warns in production about MCP sms.send once the operator acknowledged it', function (): void {
        ghsaAgentsSetTool('sms.send', true);
        config(['mcp.sms_send_acknowledged' => true]);
        app()->detectEnvironment(fn () => 'production');

        $checks = ghsaAgentsOpsChecks();

        expect($checks['mcp.tools.sms.send']['result'])->toBe('WARN')
            ->and($checks['mcp.tools.sms.send']['detail'])->toContain('MCP_TOOL_SMS_SEND is ON');
    });

    it('keeps the MCP sms.send acknowledgement off by default', function (): void {
        expect(config('mcp.sms_send_acknowledged'))->toBeFalse();
    });

    it('passes the MCP money tools when they are off', function (): void {
        config(['trading.enabled' => false]);
        foreach (['account.create', 'payment.transfer', 'exchange.trade', 'sms.send'] as $tool) {
            ghsaAgentsSetTool($tool, false);
        }
        app()->detectEnvironment(fn () => 'production');

        $checks = ghsaAgentsOpsChecks();

        foreach (['account.create', 'payment.transfer', 'exchange.trade', 'sms.send'] as $tool) {
            expect($checks['mcp.tools.' . $tool]['result'])->not->toBe('FAIL')
                ->and($checks['mcp.tools.' . $tool]['detail'])->not->toContain(' is ON');
        }
    });
});

/**
 * @return array{0: SmsSendTool, 1: SmsService&MockInterface}
 */
function ghsaAgentsSmsTool(int $sends): array
{
    /** @var SmsService&MockInterface $sms */
    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')->times($sends)->andReturn([
        'message_id'  => 'm-1',
        'status'      => 'sent',
        'parts'       => 1,
        'destination' => '+37069912345',
        'price_usdc'  => '50000',
    ]);
    /** @var SmsPricingService&MockInterface $pricing */
    $pricing = Mockery::mock(SmsPricingService::class);
    $pricing->shouldReceive('getPriceForNumber')->andReturn([
        'amount_usdc' => '50000', 'rate_eur' => '0.04', 'country_code' => 'LT', 'parts' => 1,
    ]);

    return [new SmsSendTool($sms, $pricing, new SmsSendBudget()), $sms];
}

describe('C61: MCP sms.send applies the REST input rules and a per-minute limit', function (): void {
    it('rejects input the REST endpoint rejects, before using any budget', function (array $parameters, string $error): void {
        config(['sms.enabled' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);
        [$tool] = ghsaAgentsSmsTool(0);

        $result = $tool->execute($parameters);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toContain($error)
            ->and(Cache::get(SmsSendBudget::userCountCacheKey($user->id)))->toBeNull()
            ->and(Cache::get(SmsSendBudget::globalCountCacheKey()))->toBeNull();
    })->with([
        'message over 1600 characters' => [['to' => '+37069912345', 'message' => str_repeat('a', 1601)], 'Invalid message'],
        'to with letters'              => [['to' => '+3706991abcd', 'message' => 'hi'], 'Invalid to'],
        'to starting with 0'           => [['to' => '+0123456789', 'message' => 'hi'], 'Invalid to'],
        'from too long'                => [['to' => '+37069912345', 'from' => str_repeat('A', 21), 'message' => 'hi'], 'Invalid from'],
        'from with symbols'            => [['to' => '+37069912345', 'from' => 'Bank<script>', 'message' => 'hi'], 'Invalid from'],
    ]);

    // Review A2: the budget and the spending limit count one unit per call,
    // so a call may bill at most one SMS part.
    it('sends a message that fits in one SMS part', function (string $message): void {
        config(['sms.enabled' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);
        [$tool] = ghsaAgentsSmsTool(1);

        $result = $tool->execute(['to' => '+37069912345', 'from' => 'Zelta', 'message' => $message]);

        expect($result->isSuccess())->toBeTrue()
            ->and((int) Cache::get(SmsSendBudget::userCountCacheKey($user->id)))->toBe(1);
    })->with([
        '160 GSM characters'               => [str_repeat('a', 160)],
        '80 GSM extension characters'      => [str_repeat('€', 80)],
        '70 characters outside GSM'        => [str_repeat('ą', 70)],
        '35 emoji (two UTF-16 units each)' => [str_repeat('😀', 35)],
    ]);

    it('refuses a message that would bill more than one SMS part, before using any budget', function (string $message): void {
        config(['sms.enabled' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);
        [$tool] = ghsaAgentsSmsTool(0);

        $result = $tool->execute(['to' => '+37069912345', 'message' => $message]);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toContain('Invalid message')
            ->and($result->getError())->toContain('one SMS part')
            ->and(Cache::get(SmsSendBudget::userCountCacheKey($user->id)))->toBeNull()
            ->and(Cache::get(SmsSendBudget::globalCountCacheKey()))->toBeNull();
    })->with([
        '161 GSM characters'               => [str_repeat('a', 161)],
        '1600 GSM characters'              => [str_repeat('a', 1600)],
        '81 GSM extension characters'      => [str_repeat('€', 81)],
        '71 characters outside GSM'        => [str_repeat('ą', 71)],
        '36 emoji (two UTF-16 units each)' => [str_repeat('😀', 36)],
        '159 GSM characters and one ą'     => [str_repeat('a', 159) . 'ą'],
    ]);

    it('counts billed parts as GSM-7 160/153 and UCS-2 70/67', function (string $message, int $parts): void {
        expect(SmsSendTool::billedParts($message))->toBe($parts);
    })->with([
        [str_repeat('a', 160), 1],
        [str_repeat('a', 161), 2],
        [str_repeat('a', 306), 2],
        [str_repeat('a', 307), 3],
        [str_repeat('[', 80), 1],
        [str_repeat('[', 81), 2],
        [str_repeat('ą', 70), 1],
        [str_repeat('ą', 71), 2],
        [str_repeat('ą', 134), 2],
        [str_repeat('ą', 135), 3],
        [str_repeat('😀', 35), 1],
        [str_repeat('😀', 36), 2],
    ]);

    it('advertises the one-part limit in the input schema', function (): void {
        [$tool] = ghsaAgentsSmsTool(0);

        expect($tool->getInputSchema()['properties']['message']['maxLength'])->toBe(160)
            ->and($tool->getInputSchema()['properties']['message']['description'])->toContain('one SMS part');
    });

    it('applies a per-user per-minute limit', function (): void {
        config(['sms.enabled' => true, 'mcp.rate_limits.sms_per_minute' => 1]);
        $user = User::factory()->create();
        $this->actingAs($user);
        [$tool] = ghsaAgentsSmsTool(1);

        $first = $tool->execute(['to' => '+37069912345', 'message' => 'one']);
        $second = $tool->execute(['to' => '+37069912345', 'message' => 'two']);

        expect($first->isSuccess())->toBeTrue()
            ->and($second->isSuccess())->toBeFalse()
            ->and($second->getError())->toContain('Too many SMS sends')
            ->and((int) Cache::get(SmsSendBudget::userCountCacheKey($user->id)))->toBe(1);

        // Another user is not affected.
        $this->actingAs(User::factory()->create());
        [$other] = ghsaAgentsSmsTool(1);
        expect($other->execute(['to' => '+37069912345', 'message' => 'three'])->isSuccess())->toBeTrue();
    });
});

describe('C49: the registry metadata advertises the tools production exposes', function (): void {
    it('matches the tool count and scopes of the Zelta production template', function (): void {
        $template = (string) file_get_contents(base_path('.env.zelta.example'));
        preg_match_all('/^MCP_TOOL_([A-Z_]+)=(true|false)\s*$/m', $template, $matches, PREG_SET_ORDER);

        /** @var array<string, array<string, mixed>> $catalog */
        $catalog = config('mcp.tools');
        preg_match('/^TRADING_ENABLED=(true|false)\s*$/m', $template, $trading);

        $enabled = [];
        foreach ($matches as [, $env, $value]) {
            $name = (string) preg_replace('/_/', '.', strtolower($env), 1);
            expect($catalog)->toHaveKey($name);

            $requires = $catalog[$name]['requires_config'] ?? null;
            $requirementMet = $requires !== 'trading.enabled' || ($trading[1] ?? 'false') === 'true';

            if ($value === 'true' && $requirementMet) {
                $enabled[] = $name;
            }
        }

        // Every catalog tool has an explicit pin in the template.
        expect(count($matches))->toBe(count($catalog));

        $count = count($enabled);
        $scopes = collect($enabled)
            ->map(fn (string $name) => $catalog[$name]['scope'] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        /** @var array{description: string, _meta: array<string, array<string, array{scopes: list<string>}>>} $server */
        $server = json_decode((string) file_get_contents(base_path('packages/finaegis-mcp-stdio/server.json')), true);
        $publisherScopes = $server['_meta']['io.modelcontextprotocol.registry/publisher-provided']['io.github.FinAegis']['scopes'];
        sort($publisherScopes);

        expect($server['description'])->toContain("Exposes {$count} tools")
            ->and($publisherScopes)->toBe($scopes)
            ->and((string) file_get_contents(base_path('packages/finaegis-mcp-stdio/smithery.yaml')))->toContain("{$count} tools")
            ->and((string) file_get_contents(base_path('docs/operations/mcp-directory-submissions.md')))->toContain("exposes {$count} tools");
    });
});
