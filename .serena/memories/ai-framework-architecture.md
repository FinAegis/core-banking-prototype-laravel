# AI Framework Architecture - FinAegis

## Overview
The FinAegis platform includes a comprehensive AI Agent Framework built with Domain-Driven Design (DDD), Event Sourcing, and the Model Context Protocol (MCP). This framework enables intelligent automation for banking operations while maintaining complete auditability.

## Key Components

### 1. MCP Server (`app/Domain/AI/MCP/`)
- Full Model Context Protocol v1.0 implementation
- Tool registry and discovery system
- Resource management for document exposure
- Performance monitoring and caching

### 2. AI Aggregates (`app/Domain/AI/Aggregates/`)
- **AIInteractionAggregate**: Event-sourced aggregate for all AI interactions
- Records every decision, tool execution, and human intervention
- Maintains complete audit trail for regulatory compliance

### 3. AI Events (`app/Domain/AI/Events/`)
Core domain events:
- ConversationStartedEvent
- AIDecisionMadeEvent
- ToolExecutedEvent
- HumanInterventionRequestedEvent
- HumanOverrideEvent
- CompensationExecutedEvent

### 4. Workflows (`app/Domain/AI/Workflows/`)
Laravel Workflow-based orchestration:
- **CustomerServiceWorkflow**: Natural language processing and intent recognition
- **ComplianceWorkflow**: KYC/AML verification and monitoring
- **RiskAssessmentSaga**: Multi-factor risk analysis with compensation
- **TradingAgentWorkflow**: Market analysis and automated trading
- **HumanApprovalWorkflow**: Human-in-the-loop for high-value operations

### 5. Child Workflows (`app/Domain/AI/Workflows/Children/`)
Specialized workflows:
- FraudDetectionWorkflow
- CreditRiskWorkflow
- MarketAnalysisWorkflow

### 6. Activities (`app/Domain/AI/Activities/`)
Atomic operations:
- IntentRecognitionActivity
- ToolSelectionActivity
- MLPredictionActivity
- BehavioralAnalysisActivity
- CalculateRSIActivity
- CalculateMACDActivity
- VaRCalculationActivity

### 7. Banking Tools (`app/Domain/AI/Tools/`)
20+ specialized tools across domains:
- **Account**: CreateAccount, CheckBalance, GetTransactionHistory, FreezeAccount
- **Payment**: InitiatePayment, PaymentStatus, CancelPayment, SchedulePayment
- **Trading**: GetExchangeRates, PlaceOrder, GetMarketData, ExecuteTrade
- **Lending**: LoanApplication, CheckLoanStatus, CalculateInterest, AssessCreditRisk
- **Compliance**: KYCVerification, AMLScreening, TransactionMonitoring, RegulatoryReporting
- **Stablecoin**: TransferTokens, CheckTokenBalance, MintTokens, BurnTokens

## Testing Structure

### Test Files (`tests/Feature/AI/`)
- AIInteractionAggregateTest.php - Event sourcing and aggregate testing
- MCPServerTest.php - MCP server and tool registry testing
- AgentWorkflowTest.php - Workflow orchestration testing

### Test Coverage Requirements
- Minimum 80% coverage for all AI components
- PHPStan Level 5 compliance
- PHPCS PSR-12 standards
- PHP CS Fixer formatting

## API Endpoints

### AI Chat (`/api/ai/`)
- POST /chat - Send message to AI agent
- GET /conversations - List user conversations
- GET /conversations/{id} - Get conversation details
- DELETE /conversations/{id} - Delete conversation
- POST /feedback - Submit feedback

### MCP Tools (`/api/ai/mcp/`)
- GET /tools - List available tools
- GET /tools/{tool} - Get tool details
- POST /tools/{tool}/execute - Execute tool
- POST /tools/register - Register custom tool

## Configuration

### Environment Variables
```env
AI_PROVIDER=openai
OPENAI_API_KEY=your-key
CLAUDE_API_KEY=your-key
PINECONE_API_KEY=your-key
PINECONE_ENVIRONMENT=your-env
AI_CONFIDENCE_THRESHOLD=0.8
AI_HUMAN_APPROVAL_THRESHOLD=10000
```

### Config Files
- config/ai.php - Main AI configuration
- config/mcp.php - MCP server settings
- config/workflow.php - Workflow orchestration

## Development Guidelines

1. **Event Sourcing**: Every AI interaction must be recorded as an event
2. **Tool Development**: All tools must implement MCPToolInterface
3. **Workflow Pattern**: Use Laravel Workflow for multi-step operations
4. **Saga Pattern**: Implement compensation for all critical operations
5. **Testing**: Maintain >80% test coverage for all components
6. **Documentation**: Update docs/13-AI-FRAMEWORK/ for any changes

## Documentation Location
Complete documentation available at: `docs/13-AI-FRAMEWORK/`
- 00-Overview.md - Architecture and getting started
- 01-MCP-Integration.md - Tool development guide
- 02-Agent-Creation.md - Creating custom agents
- 03-Workflows.md - Workflow development patterns
- 04-Event-Sourcing.md - Event sourcing implementation
- 05-API-Reference.md - Complete API documentation