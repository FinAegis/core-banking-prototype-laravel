# Custodian Domain Documentation

## Overview

The Custodian Domain handles integration with external banking partners (Paysera, Deutsche Bank, Santander, etc.) and manages the bridge between the FinAegis platform's event-sourced core and external banking systems.

## Event Sourcing Approach

### Why Regular Laravel Events?

The Custodian Domain intentionally uses regular Laravel events (`Illuminate\Foundation\Events\Dispatchable`) instead of stored events (`Spatie\EventSourcing\StoredEvents\ShouldBeStored`) for the following reasons:

1. **External System Integration**: Custodian events represent state changes in external systems that we observe but don't control. These are notifications of external events, not commands that change our internal state.

2. **No Replay Requirements**: Unlike account operations that benefit from event replay and reconstruction, custodian events represent real-time synchronization with external systems. Replaying these events would be meaningless or potentially harmful.

3. **Idempotency Concerns**: External bank operations must be idempotent. Storing and potentially replaying custodian events could lead to duplicate transaction attempts.

4. **Performance Optimization**: By not storing these high-frequency synchronization events, we reduce database load and improve performance.

## Event Types

### Regular Laravel Events (Current Implementation)
- `AccountBalanceUpdated`: Fired when external bank balance changes are detected
- `TransactionStatusUpdated`: Fired when external transaction status changes
- `CustodianConnectionStatusChanged`: Fired when bank API connection status changes

These events are used for:
- Real-time notifications
- Cache invalidation
- Monitoring and alerting
- Webhook dispatching

### Stored Events (Used by Other Domains)
The actual financial operations triggered by custodian interactions are recorded as stored events in the Account domain:
- `AssetBalanceAdded`
- `AssetBalanceSubtracted`
- `AssetTransferred`

This separation ensures:
- Complete audit trail of financial operations
- External system changes don't pollute our event store
- Clear boundary between our domain and external systems

## Integration Pattern

```
External Bank API → Custodian Connector → Regular Event → Handler → Domain Aggregate → Stored Event
```

1. External bank sends webhook or we poll for changes
2. Custodian connector processes the external data
3. Regular Laravel event is fired for immediate handling
4. Event handlers update our domain aggregates
5. Domain aggregates record stored events for the actual state changes

## Future Considerations

If requirements change and we need to store custodian events, we could:
1. Create a separate `custodian_events` table for external system tracking
2. Implement a hybrid approach with both regular and stored events
3. Add optional event storage for specific high-value operations

The current approach provides the right balance of performance, clarity, and maintainability for integration with external banking systems.