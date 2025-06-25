Feature: Saga Pattern Implementation
  In order to ensure system reliability and data consistency
  As a system handling distributed transactions
  I need robust saga patterns with compensation support

  Background:
    Given the workflow engine is configured
    And compensation tracking is enabled
    And distributed transaction monitoring is active

  Scenario: Simple transfer saga with successful completion
    Given account "A" has balance of 1000.00 USD
    And account "B" has balance of 500.00 USD
    When I initiate a transfer saga of 200.00 USD from "A" to "B"
    Then the saga should execute in sequence:
      | Step | Action               | Status    | Account A | Account B |
      | 1    | Withdraw from A      | Success   | 800.00    | 500.00    |
      | 2    | Deposit to B         | Success   | 800.00    | 700.00    |
      | 3    | Complete saga        | Success   | 800.00    | 700.00    |
    And saga state should be persisted at each step
    And event store should contain all saga events

  Scenario: Transfer saga with compensation on deposit failure
    Given account "A" has balance of 1000.00 USD
    And account "B" is frozen for compliance
    When I initiate a transfer saga of 200.00 USD from "A" to "B"
    Then the saga should execute with compensation:
      | Step | Action               | Status    | Compensation      |
      | 1    | Withdraw from A      | Success   | -                 |
      | 2    | Deposit to B         | Failed    | Required          |
      | 3    | Compensate step 1    | Success   | Refund to A       |
    And final balances should be:
      | Account | Balance |
      | A       | 1000.00 |
      | B       | 0.00    |
    And compensation events should be recorded

  Scenario: Nested saga with partial compensation
    Given multiple accounts exist with various balances
    When I execute a bulk transfer saga with nested workflows:
      | From | To | Amount | Result  |
      | A    | B  | 100.00 | Success |
      | C    | D  | 200.00 | Success |
      | E    | F  | 300.00 | Failed  |
    Then the saga compensation should occur:
      | Workflow | Status   | Compensation            |
      | A->B     | Success  | Keep                    |
      | C->D     | Success  | Keep                    |
      | E->F     | Failed   | No compensation needed  |
    And parent saga should handle child workflow failures gracefully

  Scenario: Saga with external service integration failure
    Given payment gateway integration is configured
    And account "A" has balance of 500.00 USD
    When I initiate an external payment saga
    And the external payment gateway times out
    Then the saga should:
      | Action                    | Result                          |
      | Detect timeout            | Within 30 seconds               |
      | Initiate compensation     | Automatic                       |
      | Reverse account debit     | Success                         |
      | Record failure reason     | "External gateway timeout"      |
      | Notify monitoring system  | Alert sent                      |
    And circuit breaker should be triggered for the gateway

  Scenario: Parallel saga execution with ordering constraints
    Given I need to execute multiple parallel operations
    When I initiate a complex saga with dependencies:
      | Operation | Dependencies | Type     |
      | A         | None         | Parallel |
      | B         | None         | Parallel |
      | C         | A,B          | Sequential|
      | D         | C            | Sequential|
    Then operations should execute respecting constraints
    And parallel operations should complete before dependent ones
    And compensation should respect reverse dependency order

  Scenario: Saga compensation with idempotency
    Given a saga has partially completed with compensation
    When the same compensation is triggered again
    Then the system should:
      | Check                  | Action                    |
      | Already compensated?   | Skip duplicate            |
      | Verify final state     | Matches expected          |
      | Log idempotent call    | For audit                 |
    And no double compensation should occur
    And system state should remain consistent

  Scenario: Long-running saga with checkpoint recovery
    Given a multi-day batch processing saga is configured
    When the saga execution is interrupted at step 500 of 1000
    Then on restart the saga should:
      | Action                | Details                        |
      | Load last checkpoint  | Step 500 state                 |
      | Verify data integrity | Hash validation                |
      | Resume from step 501  | Without reprocessing           |
      | Complete remaining    | Steps 501-1000                 |
    And maintain exactly-once processing guarantee

  Scenario: Saga with dynamic compensation strategies
    Given different compensation strategies are available
    When a saga fails requiring compensation
    Then the appropriate strategy should be selected:
      | Failure Type        | Strategy              | Action                |
      | Insufficient funds  | Immediate reverse     | Refund immediately    |
      | Compliance hold     | Delayed compensation  | Queue for review      |
      | Technical error     | Retry with backoff    | Exponential retry     |
      | External failure    | Alternative path      | Use backup service    |
    And strategy selection should be logged

  Scenario: Cross-domain saga coordination
    Given multiple domain services are involved
    When I execute a saga spanning:
      | Domain      | Operation              |
      | Account     | Debit funds            |
      | Payment     | Process payment        |
      | Compliance  | Verify transaction     |
      | Notification| Send confirmation      |
    Then each domain should participate correctly
    And compensation should cascade across domains
    And domain boundaries should be respected
    And event correlation should track the entire saga

  Scenario: Saga monitoring and observability
    Given saga monitoring is enabled
    When multiple sagas are executing concurrently
    Then the monitoring system should track:
      | Metric                    | Available |
      | Active saga count         | Yes       |
      | Completion rate           | Yes       |
      | Average duration          | Yes       |
      | Compensation frequency    | Yes       |
      | Failed saga details       | Yes       |
      | Performance bottlenecks   | Yes       |
    And provide real-time saga visualization
    And alert on anomalous patterns