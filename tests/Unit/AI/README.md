# AI Framework Unit Tests

These tests were moved from Feature to Unit tests to avoid database dependency and memory issues in CI.

## Test Organization

- `AIInteractionAggregateTest.php` - Tests for event sourcing aggregate
- `MCPServerTest.php` - Tests for MCP server implementation
- `AgentWorkflowTest.php` - Tests for agent workflow orchestration
- `ChildWorkflows/` - Tests for specific workflow implementations

## Notes

These tests do not require database access as they test domain logic with mocks and stubs.
They were originally in tests/Feature/AI but were moved to prevent RefreshDatabase trait from being automatically applied, which was causing memory exhaustion in CI.