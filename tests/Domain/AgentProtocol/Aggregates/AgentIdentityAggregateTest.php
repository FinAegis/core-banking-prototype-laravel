<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentIdentityAggregateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_can_register_agent(): void
    {
        $agentId = Str::uuid()->toString();
        $did = 'did:finaegis:key:' . substr(hash('sha256', $agentId), 0, 32);
        $name = 'Test Agent';
        $type = 'autonomous';
        $metadata = ['description' => 'Test agent for unit testing'];

        $aggregate = AgentIdentityAggregate::register(
            $agentId,
            $did,
            $name,
            $type,
            $metadata
        );

        $aggregate->persist();

        $this->assertInstanceOf(AgentIdentityAggregate::class, $aggregate);
        $this->assertEquals($agentId, $aggregate->getAgentId());
        $this->assertEquals($did, $aggregate->getDid());
        $this->assertEquals($name, $aggregate->getName());
        $this->assertEquals($type, $aggregate->getType());
        $this->assertTrue($aggregate->isActive());
        $this->assertEquals(50.0, $aggregate->getReputationScore());
    }

    public function test_can_advertise_capability(): void
    {
        $agentId = Str::uuid()->toString();
        $did = 'did:finaegis:key:' . substr(hash('sha256', $agentId), 0, 32);

        $aggregate = AgentIdentityAggregate::register($agentId, $did, 'Test Agent');
        $aggregate->advertiseCapability(
            'payment.transfer',
            [], // endpoints
            ['max_amount' => 10000], // parameters
            [], // requiredPermissions
            ['AP2', 'A2A'] // supportedProtocols
        );

        $aggregate->persist();

        $capabilities = $aggregate->getCapabilities();
        $this->assertArrayHasKey('payment.transfer', $capabilities);
        $this->assertEquals(['max_amount' => 10000], $capabilities['payment.transfer']['parameters']);
        $this->assertEquals(['AP2', 'A2A'], $capabilities['payment.transfer']['supported_protocols']);
        $this->assertTrue($aggregate->hasCapability('payment.transfer'));
        $this->assertFalse($aggregate->hasCapability('payment.escrow'));
    }

    public function test_can_create_wallet(): void
    {
        $agentId = Str::uuid()->toString();
        $did = 'did:finaegis:key:' . substr(hash('sha256', $agentId), 0, 32);
        $walletId = Str::uuid()->toString();

        $aggregate = AgentIdentityAggregate::register($agentId, $did, 'Test Agent');
        $aggregate->createWallet($walletId, 'USD', 1000.0, ['type' => 'primary']);

        $aggregate->persist();

        $wallets = $aggregate->getWallets();
        $this->assertArrayHasKey($walletId, $wallets);
        $this->assertEquals('USD', $wallets[$walletId]['currency']);
        $this->assertEquals(1000.0, $wallets[$walletId]['balance']);
        $this->assertTrue($aggregate->hasWallet($walletId));
        $this->assertFalse($aggregate->hasWallet('non-existent'));
    }

    public function test_can_chain_operations(): void
    {
        $agentId = Str::uuid()->toString();
        $did = 'did:finaegis:key:' . substr(hash('sha256', $agentId), 0, 32);
        $walletId1 = Str::uuid()->toString();
        $walletId2 = Str::uuid()->toString();

        $aggregate = AgentIdentityAggregate::register($agentId, $did, 'Test Agent')
            ->advertiseCapability('payment.transfer', [], [], [], ['AP2', 'A2A'])
            ->advertiseCapability('payment.escrow', [], [], [], ['AP2', 'A2A'])
            ->createWallet($walletId1, 'USD', 5000.0)
            ->createWallet($walletId2, 'EUR', 2000.0);

        $aggregate->persist();

        $this->assertEquals(2, count($aggregate->getCapabilities()));
        $this->assertEquals(2, count($aggregate->getWallets()));
        $this->assertTrue($aggregate->hasCapability('payment.transfer'));
        $this->assertTrue($aggregate->hasCapability('payment.escrow'));
        $this->assertTrue($aggregate->hasWallet($walletId1));
        $this->assertTrue($aggregate->hasWallet($walletId2));
    }

    public function test_can_retrieve_and_reconstitute_aggregate(): void
    {
        $agentId = Str::uuid()->toString();
        $did = 'did:finaegis:key:' . substr(hash('sha256', $agentId), 0, 32);
        $walletId = Str::uuid()->toString();

        // Create and persist aggregate
        $aggregate = AgentIdentityAggregate::register($agentId, $did, 'Test Agent')
            ->advertiseCapability('payment.transfer', [], [], [], ['AP2', 'A2A'])
            ->createWallet($walletId, 'USD', 1000.0);
        $aggregate->persist();

        // Retrieve and verify
        $retrievedAggregate = AgentIdentityAggregate::retrieve($agentId);

        $this->assertEquals($agentId, $retrievedAggregate->getAgentId());
        $this->assertEquals($did, $retrievedAggregate->getDid());
        $this->assertEquals('Test Agent', $retrievedAggregate->getName());
        $this->assertTrue($retrievedAggregate->hasCapability('payment.transfer'));
        $this->assertTrue($retrievedAggregate->hasWallet($walletId));
    }

    public function test_multiple_capabilities_with_versions(): void
    {
        $agentId = Str::uuid()->toString();
        $did = 'did:finaegis:key:' . substr(hash('sha256', $agentId), 0, 32);

        $aggregate = AgentIdentityAggregate::register($agentId, $did, 'Test Agent')
            ->advertiseCapability('payment.transfer.v1', [], ['max_amount' => 10000], [], ['AP2', 'A2A'])
            ->advertiseCapability('payment.transfer.v2', [], ['max_amount' => 50000], [], ['AP2', 'A2A'])
            ->advertiseCapability('messaging.a2a', [], [], [], ['AP2', 'A2A']);

        $aggregate->persist();

        $capabilities = $aggregate->getCapabilities();

        // Each capability is stored with its full ID
        $this->assertArrayHasKey('payment.transfer.v1', $capabilities);
        $this->assertArrayHasKey('payment.transfer.v2', $capabilities);
        $this->assertEquals(10000, $capabilities['payment.transfer.v1']['parameters']['max_amount']);
        $this->assertEquals(50000, $capabilities['payment.transfer.v2']['parameters']['max_amount']);
        $this->assertArrayHasKey('messaging.a2a', $capabilities);
    }
}
