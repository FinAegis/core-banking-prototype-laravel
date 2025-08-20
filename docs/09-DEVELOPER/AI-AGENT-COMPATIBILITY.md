# AI Agent Compatibility Guide

FinAegis is designed to be fully compatible with AI coding agents and assistants. We follow the [AGENTS.md specification](https://agents.md/) to provide structured guidance throughout the codebase.

## AGENTS.md Support

### What is AGENTS.md?

AGENTS.md is an open format created by OpenAI for guiding AI coding agents. Think of it as a README specifically for AI assistants - providing context, instructions, and guidelines to help them work effectively with your code.

### Our Implementation

We have AGENTS.md files at multiple levels:

1. **Root Level** (`/AGENTS.md`)
   - Project overview and quick start
   - Development environment setup
   - Testing and code quality guidelines
   - Security considerations
   - PR and commit instructions

2. **Domain Level** (`/app/Domain/AGENTS.md`)
   - Domain-Driven Design guidelines
   - Event sourcing patterns
   - Saga implementation rules
   - Service layer best practices

3. **Test Level** (`/tests/AGENTS.md`)
   - Testing conventions
   - Pest PHP usage
   - Event and database testing
   - Common testing issues

### How AI Agents Use These Files

When an AI agent works on your code:
1. It automatically discovers AGENTS.md files in the directory tree
2. Reads the closest AGENTS.md file for context-specific guidance
3. Follows the instructions for that specific component
4. Respects nested AGENTS.md files (more specific ones take precedence)

## AI-Friendly Architecture

### Event Sourcing
Our event-sourced architecture is highly AI-friendly:
- Clear event definitions describe what happened
- Aggregates provide bounded contexts
- Sagas handle complex workflows explicitly
- Complete audit trail of all changes

### Domain-Driven Design
DDD principles make the codebase more understandable:
- Bounded contexts separate concerns
- Ubiquitous language reduces ambiguity
- Value objects enforce business rules
- Domain services encapsulate logic

### Structured Patterns
Consistent patterns throughout:
- Command/Query separation (CQRS)
- Repository pattern for data access
- Factory pattern for object creation
- Observer pattern for event handling

## Working with AI Assistants

### Recommended AI Tools

1. **GitHub Copilot**
   - Excellent for autocomplete
   - Understands Laravel patterns
   - Reads AGENTS.md for context

2. **Claude (Anthropic)**
   - Superior for complex refactoring
   - Excellent at following AGENTS.md
   - Strong Laravel/PHP knowledge

3. **ChatGPT/GPT-4**
   - Good for documentation
   - Helpful for test generation
   - Understands event sourcing

4. **Cursor IDE**
   - Native AGENTS.md support
   - Integrated AI assistance
   - Context-aware suggestions

### Best Practices for AI Collaboration

#### 1. Provide Clear Context
```markdown
# Good prompt
"Create a new saga in app/Domain/Trading that handles order matching with compensation for failed trades, following our event sourcing patterns"

# Better prompt (references AGENTS.md)
"Following the patterns in app/Domain/AGENTS.md, create an OrderMatchingSaga that implements compensation for failed trades"
```

#### 2. Reference Existing Patterns
```php
// Tell the AI to follow existing patterns
"Create a new service similar to app/Domain/Exchange/Services/OrderService.php"
```

#### 3. Use Type Hints
```php
/** @var OrderService&MockInterface */
private MockInterface $orderService;
```

#### 4. Specify Test Requirements
```markdown
"Create tests following tests/AGENTS.md guidelines with >50% coverage"
```

## Integration Examples

### Example 1: Creating a New Domain

When asking an AI to create a new domain:

```markdown
Create a new Compliance domain following app/Domain/AGENTS.md with:
- ComplianceCheckAggregate for event sourcing
- Events: CheckInitiated, CheckPassed, CheckFailed
- ComplianceService for business logic
- ComplianceSaga for multi-step KYC workflow
- Tests with >50% coverage following tests/AGENTS.md
```

### Example 2: Implementing a Feature

```markdown
Implement a withdrawal feature:
1. Follow the saga pattern from app/Domain/AGENTS.md
2. Create events: WithdrawalRequested, WithdrawalApproved, WithdrawalCompleted
3. Add compensation for failed withdrawals
4. Include tests as specified in tests/AGENTS.md
5. Follow security guidelines from root AGENTS.md
```

### Example 3: Refactoring Code

```markdown
Refactor the PaymentService:
1. Follow DDD principles from app/Domain/AGENTS.md
2. Extract value objects for payment details
3. Implement event sourcing for state changes
4. Maintain backward compatibility
5. Update tests to match new structure
```

## AI-Assisted Development Workflow

### 1. Planning Phase
- Use AI to analyze existing code
- Generate architecture diagrams
- Identify patterns and anti-patterns

### 2. Implementation Phase
- AI generates boilerplate code
- Follows AGENTS.md guidelines
- Maintains consistency with existing code

### 3. Testing Phase
- AI generates test cases
- Ensures coverage requirements
- Creates edge case scenarios

### 4. Documentation Phase
- AI updates documentation
- Generates API documentation
- Creates user guides

### 5. Review Phase
- AI checks code quality
- Identifies potential issues
- Suggests improvements

## Continuous Improvement

### Updating AGENTS.md Files

As the project evolves, keep AGENTS.md files updated:

1. **When Adding Features**
   - Update relevant AGENTS.md with new patterns
   - Document new conventions
   - Add security considerations

2. **When Changing Architecture**
   - Update architectural guidelines
   - Document migration paths
   - Explain breaking changes

3. **When Finding Issues**
   - Add common gotchas
   - Document solutions
   - Update testing guidelines

### Feedback Loop

Help improve AI assistance:
1. Report unclear AI responses
2. Update AGENTS.md when AI makes mistakes
3. Share successful prompts with the team
4. Document AI-specific workflows

## Security Considerations

When working with AI assistants:

1. **Never Share Secrets**
   - Don't paste real API keys
   - Use example credentials only
   - Redact sensitive information

2. **Review Generated Code**
   - Check for security vulnerabilities
   - Verify input validation
   - Ensure proper authentication

3. **Validate Dependencies**
   - Review any suggested packages
   - Check for known vulnerabilities
   - Verify licensing compatibility

## Resources

- [AGENTS.md Specification](https://agents.md/)
- [OpenAI Codex Best Practices](https://platform.openai.com/docs/guides/code)
- [GitHub Copilot Documentation](https://docs.github.com/en/copilot)
- [Anthropic Claude Documentation](https://docs.anthropic.com/)

## Contributing

To improve AI agent compatibility:
1. Keep AGENTS.md files updated
2. Use clear, consistent naming
3. Document complex logic
4. Provide examples in comments
5. Maintain comprehensive tests

Remember: The better our AGENTS.md files, the more effectively AI agents can help with development!