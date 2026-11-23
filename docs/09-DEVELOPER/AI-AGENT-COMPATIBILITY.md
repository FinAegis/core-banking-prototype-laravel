# AI Agent Compatibility Guide

This guide explains how AI coding agents find guidance in the FinAegis codebase. The project follows the [AGENTS.md format](https://agents.md/), so agents can read structured instructions next to the code they change.

This page is about agents that write code. AI agents that use the platform as customers connect through the MCP server at `https://mcp.zelta.app/mcp` or the `@finaegis/mcp` package instead. [SDK-GUIDE.md](SDK-GUIDE.md) describes those.

## AGENTS.md Support

### What is AGENTS.md?

AGENTS.md is an open format for instructions to AI coding agents. It works like a README written for an agent. It gives the context, the commands and the rules the agent needs to work on the code.

### Our Implementation

The repository has AGENTS.md files at several levels:

1. **Root level** (`/AGENTS.md`) gives a project overview, the development setup, testing and code quality rules, security notes and the commit conventions.
2. **Domain level** (`/app/Domain/AGENTS.md`) explains the Domain-Driven Design rules, event sourcing, sagas and the service layer.
3. **Selected domains** have their own file: `app/Domain/Exchange/AGENTS.md`, `app/Domain/Lending/AGENTS.md` and `app/Domain/Stablecoin/AGENTS.md`.
4. **Test level** (`/tests/AGENTS.md`) covers testing conventions, Pest, event and database tests and common test problems.

The root `CLAUDE.md` holds the same kind of guidance for Claude Code. It lists the essential commands, the CI pitfalls and the rules for each major feature.

### How AI Agents Use These Files

An agent that supports the format looks for AGENTS.md files in the directory tree. It reads the file closest to the code it is changing. A more specific file takes precedence over a general one.

## AI-Friendly Architecture

### Event Sourcing

Event sourcing helps an agent understand the code. Each event class describes one thing that happened. Aggregates hold the business rules of one bounded context. Sagas and workflows make multi-step processes explicit. The stored events give a complete history of every change.

### Domain-Driven Design

The code is split into 61 domain folders under `app/Domain/`. Each one is a bounded context with its own models, events and services. Value objects enforce business rules, and domain services hold the logic. This keeps the context an agent needs small.

### Structured Patterns

The same patterns repeat across the code. Commands and queries are separated (CQRS). Repositories handle data access, factories create objects and listeners react to events.

## Working with AI Assistants

### AI Tools

Several tools can work with this repository:

1. **GitHub Copilot** helps with autocomplete and knows common Laravel patterns.
2. **Claude Code** reads `CLAUDE.md` and handles larger refactoring tasks.
3. **ChatGPT** helps with documentation and test generation.
4. **Cursor** reads AGENTS.md files and gives context-aware suggestions.

### Best Practices for AI Collaboration

#### 1. Provide Clear Context
```markdown
# Good prompt
"Create a new saga in app/Domain/Exchange that handles order matching with compensation for failed trades, following our event sourcing patterns"

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

#### 5. State the Money Rules
Agents often use floating-point numbers for money. Ask for `bcmath` (`bcadd`, `bcsub`, `bcmul`, `bcdiv`) on numeric strings. Ask for `DB::transaction()` with `lockForUpdate()` around balance changes.

## Integration Examples

### Example 1: Creating a New Domain

When you ask an AI to create a new domain, a prompt like this works well:

```markdown
Create a new Compliance domain following app/Domain/AGENTS.md with:
- ComplianceCheckAggregate for event sourcing
- Events: CheckInitiated, CheckPassed, CheckFailed
- ComplianceService for business logic
- ComplianceSaga for multi-step KYC workflow
- Tests with >50% coverage following tests/AGENTS.md
```

A new domain also needs its GraphQL schema imported in `graphql/schema.graphql`. The domain count in the public pages and in `CLAUDE.md` must be updated too.

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

1. **Planning.** The agent reads the existing code and finds the patterns to follow.
2. **Implementation.** The agent writes the code within the rules of the nearest AGENTS.md file.
3. **Testing.** The agent writes tests, including edge cases and failure paths.
4. **Documentation.** The agent updates the docs and the OpenAPI annotations that the change touches.
5. **Review.** A person reviews the result. The code quality tools also run: `php-cs-fixer`, PHPStan at level 8 and Pest.

## Continuous Improvement

### Updating AGENTS.md Files

Keep the AGENTS.md files current as the project changes. When you add a feature, add its new patterns, conventions and security notes. When the architecture changes, update the guidelines and explain the migration path. When an agent repeats a mistake, add the pitfall and its fix.

### Feedback Loop

If an agent makes a mistake that better guidance would prevent, update the AGENTS.md file. Share prompts that work well with the team.

## Security Considerations

When you work with AI assistants, follow three rules:

1. **Never share secrets.** Do not paste real API keys or credentials, and redact sensitive data.
2. **Review generated code.** Check it for security flaws, input validation and authentication.
3. **Check dependencies.** Review any package the agent suggests for known vulnerabilities and a compatible licence.

## Resources

- [AGENTS.md Specification](https://agents.md/)
- [GitHub Copilot Documentation](https://docs.github.com/en/copilot)
- [Anthropic Claude Documentation](https://docs.anthropic.com/)

## Contributing

To improve AI agent compatibility, keep the AGENTS.md files up to date and use clear, consistent names. Document complex logic and keep the tests comprehensive. Better AGENTS.md files help agents do better work.
