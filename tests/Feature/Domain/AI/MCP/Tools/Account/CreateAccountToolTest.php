<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\AI\MCP\Tools\Account;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\AccountService;
use App\Domain\AI\MCP\MCPServer;
use App\Domain\AI\MCP\ResourceManager;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\MCP\Tools\Account\CreateAccountTool;
use App\Domain\AI\ValueObjects\MCPRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateAccountToolTest extends TestCase
{
    use RefreshDatabase;

    private MCPServer $server;

    private ToolRegistry $registry;

    private CreateAccountTool $tool;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        // Set up MCP infrastructure
        $this->registry = new ToolRegistry();

        // Register the tool BEFORE creating the server
        $this->tool = new CreateAccountTool(app(AccountService::class));
        $this->registry->register($this->tool);

        // Now create the server which will pick up the registered tool
        $this->server = new MCPServer(
            $this->registry,
            app(ResourceManager::class)
        );
    }

    #[Test]
    public function it_creates_account_successfully_with_valid_input(): void
    {
        // Arrange
        $accountName = 'Test Savings Account';
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'     => $accountName,
                'type'     => 'savings',
                'currency' => 'USD',
            ],
        ]);
        $request->setUserId((string) $this->user->id);

        // Act
        $response = $this->server->handle($request);

        // Assert
        $this->assertTrue($response->isSuccess());
        $this->assertArrayHasKey('toolResult', $response->getData());

        $result = $response->getData()['toolResult'];

        $this->assertArrayHasKey('account_uuid', $result);
        $this->assertArrayHasKey('account_number', $result);
        $this->assertEquals($accountName, $result['name']);
        $this->assertEquals('savings', $result['type']);
        $this->assertEquals('active', $result['status']);

        // Verify account was created in database
        $this->assertDatabaseHas('accounts', [
            'name'      => $accountName,
            'user_uuid' => $this->user->uuid,
            'frozen'    => false,  // Equivalent to active status
        ]);

        // Verify event sourcing
        $this->assertDatabaseHas('stored_events', [
            'event_class' => 'ai_tool_executed',
        ]);
    }

    #[Test]
    public function it_validates_required_account_name(): void
    {
        // Arrange
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                // Missing 'name' field
                'type' => 'checking',
            ],
        ]);
        $request->setUserId((string) $this->user->id);

        // Act
        $response = $this->server->handle($request);

        // Assert
        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('required', strtolower($response->getError()));
        $this->assertStringContainsString('name', strtolower($response->getError()));
    }

    #[Test]
    public function it_validates_account_type_enum(): void
    {
        // Arrange
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'     => 'Test Account',
                'type'     => 'invalid_type', // Invalid enum value
                'currency' => 'USD', // Add required currency field
            ],
        ]);
        $request->setUserId((string) $this->user->id);

        // Act
        $response = $this->server->handle($request);

        // Assert
        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('must be one of', strtolower($response->getError()));
    }

    #[Test]
    public function it_validates_currency_code_format(): void
    {
        // Arrange
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'     => 'Test Account',
                'type'     => 'checking',
                'currency' => 'US', // Invalid currency code (should be 3 letters)
            ],
        ]);
        $request->setUserId((string) $this->user->id);

        // Act
        $response = $this->server->handle($request);

        // Assert
        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('must be one of', strtolower($response->getError()));
    }

    #[Test]
    public function it_handles_initial_balance_correctly(): void
    {
        // Arrange
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'            => 'Account with Balance',
                'type'            => 'checking',
                'currency'        => 'USD',
                'initial_deposit' => 1000.50,
            ],
        ]);
        $request->setUserId((string) $this->user->id);

        // Act
        $response = $this->server->handle($request);

        // Assert
        $this->assertTrue($response->isSuccess());

        $result = $response->getData()['toolResult'];
        $this->assertEquals(1000.50, $result['balance']);

        // Verify in database
        $account = Account::where('uuid', $result['account_uuid'])->first();
        $this->assertNotNull($account);
        $account->refresh(); // Refresh from database
        // Balance is stored in cents in the database
        $this->assertEquals(100050, $account->balance);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        // Clear the authentication from setUp
        \Illuminate\Support\Facades\Auth::guard('sanctum')->forgetUser();

        // Arrange - Create a new server without authentication
        $newRegistry = new ToolRegistry();
        $tool = new CreateAccountTool(app(AccountService::class));
        $newRegistry->register($tool);

        $newServer = new MCPServer(
            $newRegistry,
            app(ResourceManager::class)
        );

        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'     => 'Test Account',
                'type'     => 'checking',
                'currency' => 'USD',
            ],
        ]);
        // No user ID set and no authentication

        // Act
        $response = $newServer->handle($request);

        // Assert
        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('not found', strtolower($response->getError()));
    }

    #[Test]
    public function it_tracks_conversation_context(): void
    {
        // Arrange
        $conversationId = 'test-conv-' . uniqid();
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'     => 'Tracked Account',
                'type'     => 'savings',
                'currency' => 'USD',
            ],
        ]);
        $request->setUserId((string) $this->user->id);
        $request->setConversationId($conversationId);

        // Act
        $response = $this->server->handle($request);

        // Assert
        $this->assertTrue($response->isSuccess());

        // Verify conversation tracking
        $this->assertDatabaseHas('stored_events', [
            'event_class'    => 'ai_tool_executed',
            'aggregate_uuid' => $conversationId,
        ]);
    }

    #[Test]
    public function it_includes_metadata_in_response(): void
    {
        // Arrange
        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'     => 'Metadata Test Account',
                'type'     => 'checking',
                'currency' => 'USD',
            ],
        ]);
        $request->setUserId((string) $this->user->id);

        // Act
        $response = $this->server->handle($request);

        // Assert
        $this->assertTrue($response->isSuccess());
        $this->assertArrayHasKey('metadata', $response->getData());

        $metadata = $response->getData()['metadata'];
        $this->assertArrayHasKey('duration_ms', $metadata);
        $this->assertArrayHasKey('cache_hit', $metadata);
        $this->assertFalse($metadata['cache_hit']); // Create operations should not be cached
        $this->assertLessThan(1000, $metadata['duration_ms']); // Should complete within 1 second
    }

    #[Test]
    public function it_handles_service_exceptions_gracefully(): void
    {
        // Arrange - Mock AccountService to throw exception
        $mockService = $this->createMock(AccountService::class);
        $mockService->method('create')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        // Create a new registry and server for this test to avoid conflicts
        $newRegistry = new ToolRegistry();
        $tool = new CreateAccountTool($mockService);
        $newRegistry->register($tool);

        $newServer = new MCPServer(
            $newRegistry,
            app(ResourceManager::class)
        );

        $request = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => [
                'name'     => 'Test Account',
                'type'     => 'checking',
                'currency' => 'USD',
            ],
        ]);
        $request->setUserId((string) $this->user->id);

        // Act
        $response = $newServer->handle($request);

        // Assert
        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('database connection failed', strtolower($response->getError()));
    }

    #[Test]
    public function it_generates_unique_account_numbers(): void
    {
        // Arrange
        $request1 = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => ['name' => 'Account 1', 'type' => 'checking', 'currency' => 'USD'],
        ]);
        $request1->setUserId((string) $this->user->id);

        $request2 = MCPRequest::create('tools/call', [
            'name'      => 'account.create',
            'arguments' => ['name' => 'Account 2', 'type' => 'savings', 'currency' => 'USD'],
        ]);
        $request2->setUserId((string) $this->user->id);

        // Act
        $response1 = $this->server->handle($request1);
        $response2 = $this->server->handle($request2);

        // Assert
        $this->assertTrue($response1->isSuccess());
        $this->assertTrue($response2->isSuccess());

        $accountNumber1 = $response1->getData()['toolResult']['account_number'];
        $accountNumber2 = $response2->getData()['toolResult']['account_number'];

        $this->assertNotEquals($accountNumber1, $accountNumber2);
        $this->assertStringStartsWith('ACC', $accountNumber1);
        $this->assertStringStartsWith('ACC', $accountNumber2);
    }
}
