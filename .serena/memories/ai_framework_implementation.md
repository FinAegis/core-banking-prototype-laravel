# AI Framework Implementation Details

## Overview
The FinAegis platform now includes a comprehensive AI Agent Framework built on Domain-Driven Design (DDD) principles with event sourcing and saga orchestration.

## Core Components

### Infrastructure Layer (app/Infrastructure/AI/)
- **LLM Providers**: OpenAIProvider, ClaudeProvider - handle communication with LLM APIs
- **Storage**: ConversationStore - Redis-based conversation persistence with 100-conversation limit
- **Vector DB**: PineconeProvider - Vector database for semantic search and embeddings
- **MCP Server**: Full Model Context Protocol implementation exposing banking tools

### Domain Layer (app/Domain/AI/)
- **Aggregates**: AIInteractionAggregate - Event sourcing for all AI interactions
- **Events**: LLMRequestMadeEvent, LLMResponseReceivedEvent, LLMErrorEvent
- **ValueObjects**: LLMResponse, ConversationContext - Immutable data structures
- **Workflows**: CustomerServiceWorkflow, ComplianceWorkflow - Multi-step AI processes
- **Services**: AIAgentService - Main service interface for AI operations

## Key Features

### Event Sourcing
Every AI interaction is recorded as an event:
- Complete audit trail for compliance
- Debugging and improvement capabilities
- Usage tracking and analytics

### Multi-LLM Support
- OpenAI GPT-4 integration
- Anthropic Claude integration
- Dynamic provider selection based on task
- Fallback mechanisms for provider failures

### Conversation Management
- Redis-based storage with TTL
- Automatic limiting to 100 conversations per user
- Conversation context preservation
- Message history tracking

### Caching Strategy
- 1-hour cache for LLM responses
- 5-minute cache for vector searches
- Cache key generation using request hash

## MCP Tools Available

### Account Domain
- account.create - Create new bank account
- account.balance - Check account balance
- account.transactions - Get transaction history

### Payment Domain
- payment.transfer - Transfer funds between accounts
- payment.schedule - Schedule recurring payments

### Exchange Domain
- exchange.trade - Execute currency exchange
- exchange.rates - Get current exchange rates

### Compliance Domain
- compliance.kyc - Perform KYC verification
- compliance.aml - Check AML compliance

## Testing Coverage
- Comprehensive unit tests for all providers
- Integration tests for MCP server
- Event sourcing verification
- Performance monitoring
- PHPStan Level 5 compliance

## Configuration
Environment variables:
- AI_LLM_PROVIDER - Default LLM provider (openai/claude)
- OPENAI_API_KEY - OpenAI API credentials
- ANTHROPIC_API_KEY - Claude API credentials
- PINECONE_API_KEY - Pinecone vector DB credentials
- AI_CONVERSATION_LIMIT - Max conversations per user (default: 100)

## Website Integration
Created comprehensive AI Framework website pages:
- /ai-framework - Main AI Framework page with features and capabilities
- /ai-framework/demo - Interactive demo interface
- /ai-framework/docs - Developer documentation

Updated navigation to include AI Framework in main menu and added feature card on homepage.

## Documentation
Created comprehensive documentation in docs/13-AI-FRAMEWORK/:
- README.md - Complete overview and getting started guide
- MCP_INTEGRATION.md - Detailed MCP server implementation guide

## Important Patterns
1. Always use event sourcing for AI interactions
2. Implement fallback strategies for provider failures
3. Cache responses to reduce API costs
4. Maintain conversation limits for resource management
5. Record all tool access for compliance
6. Use value objects for immutability
7. Implement proper error handling and logging