Feature: Error Recovery Mechanisms
  In order to maintain system reliability
  As a financial platform
  I need comprehensive error recovery capabilities

  Background:
    Given error recovery services are configured
    And circuit breakers are enabled
    And retry policies are defined
    And fallback mechanisms are available

  Scenario: Database connection failure recovery
    Given the primary database is operational
    When the database connection is lost during a transaction
    Then the system should:
      | Step | Action                          | Expected Result            |
      | 1    | Detect connection loss          | Within 5 seconds           |
      | 2    | Attempt reconnection            | 3 retries with backoff     |
      | 3    | Switch to read replica          | If available               |
      | 4    | Queue write operations          | In durable storage         |
      | 5    | Alert operations team           | Immediate notification     |
    And no data loss should occur
    And user should see graceful error message

  Scenario: Payment gateway timeout with retry
    Given a payment is being processed
    When the payment gateway times out after 30 seconds
    Then the retry mechanism should:
      | Attempt | Delay      | Action                    |
      | 1       | 1 second   | Retry with same request   |
      | 2       | 2 seconds  | Retry with backoff        |
      | 3       | 4 seconds  | Final retry attempt       |
      | 4       | -          | Fail and compensate       |
    And duplicate payment prevention should be active
    And idempotency key should be used

  Scenario: Circuit breaker activation and recovery
    Given the exchange rate service is healthy
    When the service fails 5 consecutive times
    Then the circuit breaker should:
      | State      | Duration    | Behavior                    |
      | Closed     | Initial     | Normal operation            |
      | Open       | 60 seconds  | Fail fast, use cache        |
      | Half-open  | Test period | Allow single test request   |
      | Closed     | On success  | Resume normal operation     |
    And cached rates should be used when circuit is open
    And monitoring should track circuit state changes

  Scenario: Distributed transaction partial failure
    Given a multi-bank transfer is initiated
    When one bank API fails after partial completion
    Then the recovery process should:
      | Action                   | Details                          |
      | Identify failure point   | Bank B deposit failed            |
      | Check reversibility      | Bank A withdrawal reversible     |
      | Execute compensation     | Reverse Bank A withdrawal        |
      | Verify final state       | All accounts at original balance |
      | Create incident report   | With full transaction timeline   |
    And maintain ACID properties across systems

  Scenario: Queue processing failure recovery
    Given 1000 messages are in the processing queue
    When the queue worker crashes at message 500
    Then the recovery should:
      | Step | Action                    | Result                    |
      | 1    | Detect worker failure     | Health check timeout      |
      | 2    | Mark in-flight as failed  | Messages 498-502         |
      | 3    | Start new worker          | Auto-scaling             |
      | 4    | Reprocess failed messages | With duplicate detection |
      | 5    | Resume from message 503   | Continue processing      |
    And message ordering should be preserved where required

  Scenario: Cascading service failure containment
    Given service A depends on service B and C
    When service B fails completely
    Then the system should:
      | Service | Action                      | Fallback              |
      | B       | Circuit breaker opens       | Use cached data       |
      | A       | Degrade gracefully          | Limited functionality |
      | C       | Continue normal operation   | Unaffected            |
      | System  | Maintain partial service    | Core features work    |
    And prevent cascade failure to other services
    And provide clear service status to users

  Scenario: Data corruption detection and recovery
    Given transaction data is being processed
    When data corruption is detected via checksum
    Then the recovery process should:
      | Phase     | Action                         |
      | Detect    | Checksum validation fails      |
      | Isolate   | Quarantine corrupted records   |
      | Analyze   | Identify corruption source     |
      | Recover   | Use event sourcing to rebuild  |
      | Verify    | Recompute checksums            |
      | Resume    | Continue with clean data       |
    And maintain audit trail of recovery actions

  Scenario: Network partition recovery
    Given a distributed system with multiple nodes
    When a network partition occurs
    Then each partition should:
      | Partition | Behavior                    | Recovery                |
      | Primary   | Continue with quorum        | Accept writes           |
      | Secondary | Read-only mode              | Queue writes locally    |
      | Healing   | Detect partition resolved   | Merge queued writes     |
      | Conflict  | Apply resolution rules      | Last-write wins / CRDT  |
    And maintain eventual consistency

  Scenario: Resource exhaustion prevention
    Given system resources are being monitored
    When memory usage approaches 90%
    Then the system should:
      | Threshold | Action                       |
      | 80%       | Alert and log               |
      | 85%       | Reduce batch sizes          |
      | 90%       | Reject non-critical requests|
      | 95%       | Emergency garbage collection|
      | 98%       | Graceful shutdown initiation|
    And preserve critical operations
    And prevent out-of-memory crashes

  Scenario: Scheduled job failure recovery
    Given daily reconciliation is scheduled at 2 AM
    When the job fails due to temporary issue
    Then the scheduler should:
      | Time      | Action                         |
      | 2:00 AM   | Initial execution fails        |
      | 2:15 AM   | First retry attempt            |
      | 2:45 AM   | Second retry with diagnostics  |
      | 3:30 AM   | Final retry or escalate        |
      | 4:00 AM   | Alert if still failing         |
    And prevent duplicate executions
    And maintain job execution history

  Scenario: API rate limit recovery
    Given external API has rate limits
    When rate limit is exceeded
    Then the client should:
      | Response        | Action                      |
      | 429 Too Many    | Back off exponentially      |
      | Retry-After     | Honor server timing         |
      | Queue requests  | Store for later processing  |
      | Distribute load | Across time windows         |
      | Monitor usage   | Prevent future violations   |
    And maintain request priority ordering

  Scenario: Disaster recovery activation
    Given primary data center fails
    When disaster recovery is triggered
    Then the failover should:
      | Step   | Action                        | Time Target |
      | 1      | Detect primary failure        | < 1 minute  |
      | 2      | Verify data replication lag   | < 5 minutes |
      | 3      | Promote secondary to primary  | < 10 minutes|
      | 4      | Update DNS and routing        | < 15 minutes|
      | 5      | Verify full functionality     | < 20 minutes|
      | 6      | Notify stakeholders           | Immediate   |
    And achieve RTO of 30 minutes
    And maintain RPO of 5 minutes