# BIAN-Compliant API Documentation

## Overview

This document describes the BIAN (Banking Industry Architecture Network) compliant API implementation for the FinAegis Core Banking Platform. BIAN provides standardized banking service definitions that enable interoperability and consistency across banking systems.

## BIAN Concepts

### Service Domains
Service Domains represent major banking capabilities that group related functionality. Each domain follows a specific functional pattern.

### Control Records (CR)
Control Records track the lifecycle and state of service domain instances. They are identified by unique CR Reference IDs.

### Behavior Qualifiers (BQ)
Behavior Qualifiers represent specific aspects or features within a service domain, such as "Payment", "Deposit", or "Account Balance".

### Standard Operations
BIAN defines standard operations for interacting with services:
- **Initiate** - Create a new instance
- **Update** - Modify an existing instance
- **Retrieve** - Get information about an instance
- **Control** - Manage the state (freeze, suspend, etc.)
- **Execute** - Perform an action
- **Request** - Request specific information
- **Grant** - Approve or authorize
- **Capture** - Record information
- **Exchange** - Exchange information between parties

## Implemented Service Domains

### 1. Current Account Service Domain

**Functional Pattern**: Fulfill  
**Asset Type**: Current Account Fulfillment Arrangement  
**Base Path**: `/api/bian/current-account`

#### Control Record Operations

##### Initiate Current Account
```http
POST /api/bian/current-account/initiate
Content-Type: application/json
Authorization: Bearer {token}

{
  "customerReference": "550e8400-e29b-41d4-a716-446655440000",
  "accountName": "Personal Current Account",
  "accountType": "current",
  "initialDeposit": 1000,
  "currency": "USD"
}
```

**Response**:
```json
{
  "currentAccountFulfillmentArrangement": {
    "crReferenceId": "550e8400-e29b-41d4-a716-446655440001",
    "customerReference": "550e8400-e29b-41d4-a716-446655440000",
    "accountName": "Personal Current Account",
    "accountType": "current",
    "accountStatus": "active",
    "accountBalance": {
      "amount": 1000,
      "currency": "USD"
    },
    "dateType": {
      "date": "2024-01-15T10:30:00Z",
      "dateTypeName": "AccountOpeningDate"
    }
  }
}
```

##### Retrieve Current Account
```http
GET /api/bian/current-account/{crReferenceId}/retrieve
Authorization: Bearer {token}
```

##### Update Current Account
```http
PUT /api/bian/current-account/{crReferenceId}/update
Content-Type: application/json
Authorization: Bearer {token}

{
  "accountName": "Updated Account Name",
  "accountStatus": "active"
}
```

##### Control Current Account
```http
PUT /api/bian/current-account/{crReferenceId}/control
Content-Type: application/json
Authorization: Bearer {token}

{
  "controlAction": "freeze",
  "controlReason": "Suspicious activity detected"
}
```

#### Behavior Qualifier Operations

##### Execute Payment (BQ: Payment)
```http
POST /api/bian/current-account/{crReferenceId}/payment/execute
Content-Type: application/json
Authorization: Bearer {token}

{
  "paymentAmount": 250,
  "paymentType": "withdrawal",
  "paymentDescription": "ATM withdrawal"
}
```

##### Execute Deposit (BQ: Deposit)
```http
POST /api/bian/current-account/{crReferenceId}/deposit/execute
Content-Type: application/json
Authorization: Bearer {token}

{
  "depositAmount": 500,
  "depositType": "cash",
  "depositDescription": "Branch deposit"
}
```

##### Retrieve Account Balance (BQ: AccountBalance)
```http
GET /api/bian/current-account/{crReferenceId}/account-balance/retrieve
Authorization: Bearer {token}
```

##### Retrieve Transaction Report (BQ: TransactionReport)
```http
GET /api/bian/current-account/{crReferenceId}/transaction-report/retrieve?fromDate=2024-01-01&toDate=2024-01-31&transactionType=all
Authorization: Bearer {token}
```

### 2. Payment Initiation Service Domain

**Functional Pattern**: Transact  
**Asset Type**: Payment Transaction  
**Base Path**: `/api/bian/payment-initiation`

#### Control Record Operations

##### Initiate Payment
```http
POST /api/bian/payment-initiation/initiate
Content-Type: application/json
Authorization: Bearer {token}

{
  "payerReference": "550e8400-e29b-41d4-a716-446655440001",
  "payeeReference": "550e8400-e29b-41d4-a716-446655440002",
  "paymentAmount": 1000,
  "paymentCurrency": "USD",
  "paymentPurpose": "Invoice #12345",
  "paymentType": "internal",
  "valueDate": "2024-01-20"
}
```

**Response**:
```json
{
  "paymentInitiationTransaction": {
    "crReferenceId": "550e8400-e29b-41d4-a716-446655440003",
    "paymentStatus": "completed",
    "paymentDetails": {
      "payerReference": "550e8400-e29b-41d4-a716-446655440001",
      "payerName": "John Doe",
      "payeeReference": "550e8400-e29b-41d4-a716-446655440002",
      "payeeName": "Jane Smith",
      "paymentAmount": 1000,
      "paymentCurrency": "USD",
      "paymentPurpose": "Invoice #12345",
      "paymentType": "internal"
    },
    "paymentSchedule": {
      "initiationDate": "2024-01-15T10:30:00Z",
      "valueDate": "2024-01-20"
    },
    "balanceAfterPayment": {
      "payerBalance": 4000,
      "payeeBalance": 2000
    }
  }
}
```

##### Update Payment
```http
PUT /api/bian/payment-initiation/{crReferenceId}/update
Content-Type: application/json
Authorization: Bearer {token}

{
  "paymentStatus": "cancelled",
  "statusReason": "Customer requested cancellation"
}
```

##### Execute Payment
```http
POST /api/bian/payment-initiation/{crReferenceId}/execute
Content-Type: application/json
Authorization: Bearer {token}

{
  "executionMode": "immediate"
}
```

#### Behavior Qualifier Operations

##### Request Payment Status (BQ: PaymentStatus)
```http
POST /api/bian/payment-initiation/{crReferenceId}/payment-status/request
Authorization: Bearer {token}
```

##### Retrieve Payment History (BQ: PaymentHistory)
```http
GET /api/bian/payment-initiation/{accountReference}/payment-history/retrieve?fromDate=2024-01-01&toDate=2024-01-31&paymentDirection=all
Authorization: Bearer {token}
```

## Response Structure

### Success Response
All successful responses follow the BIAN pattern of returning the complete record/arrangement:

```json
{
  "{serviceDomain}{AssetType}": {
    "crReferenceId": "unique-identifier",
    "bqReferenceId": "behavior-qualifier-id",
    // ... other fields specific to the operation
  }
}
```

### Error Response
Error responses include structured error information:

```json
{
  "{serviceDomain}{AssetType}": {
    "crReferenceId": "unique-identifier",
    "executionStatus": "rejected",
    "executionReason": "Detailed reason for rejection",
    // ... additional context fields
  }
}
```

## Authentication
All endpoints require Bearer token authentication using Laravel Sanctum:

```http
Authorization: Bearer {your-api-token}
```

## Status Codes
- `201 Created` - Resource successfully created (Initiate operations)
- `200 OK` - Successful retrieval or update
- `422 Unprocessable Entity` - Business rule violation (e.g., insufficient funds)
- `404 Not Found` - Resource not found
- `401 Unauthorized` - Missing or invalid authentication
- `400 Bad Request` - Invalid request format

## Pagination
List endpoints support pagination through query parameters:
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 50, max: 100)

## Filtering and Sorting
Transaction and payment history endpoints support:
- `fromDate` - Start date filter (ISO 8601 format)
- `toDate` - End date filter (ISO 8601 format)
- `transactionType` - Filter by type (credit/debit/all)
- `paymentDirection` - Filter by direction (sent/received/all)

## BIAN Compliance Notes

### Control Record Reference IDs
- All service domain instances are identified by unique CR Reference IDs
- These IDs are UUIDs in our implementation
- They persist throughout the lifecycle of the instance

### Behavior Qualifier Reference IDs
- Each BQ operation generates its own BQ Reference ID
- These track specific aspects or operations within a service domain

### Date Handling
- All dates follow ISO 8601 format
- Timestamps include timezone information
- Date types are explicitly named (e.g., "AccountOpeningDate")

### Amount Representation
- Amounts are represented as integers (cents/minor units)
- Currency is explicitly specified using ISO 4217 codes

### Status Values
- Follow BIAN standard status values where applicable
- Account Status: active, dormant, closed, frozen
- Payment Status: initiated, scheduled, completed, failed, cancelled
- Control Status: active, frozen, suspended

## Migration from Legacy API

To migrate from the legacy API to BIAN-compliant endpoints:

1. **Account Creation**:
   - Legacy: `POST /api/accounts`
   - BIAN: `POST /api/bian/current-account/initiate`

2. **Deposits**:
   - Legacy: `POST /api/accounts/{uuid}/deposit`
   - BIAN: `POST /api/bian/current-account/{crReferenceId}/deposit/execute`

3. **Withdrawals**:
   - Legacy: `POST /api/accounts/{uuid}/withdraw`
   - BIAN: `POST /api/bian/current-account/{crReferenceId}/payment/execute`

4. **Transfers**:
   - Legacy: `POST /api/transfers`
   - BIAN: `POST /api/bian/payment-initiation/initiate`

5. **Balance Inquiry**:
   - Legacy: `GET /api/accounts/{uuid}/balance`
   - BIAN: `GET /api/bian/current-account/{crReferenceId}/account-balance/retrieve`

## Future Service Domains

The following BIAN service domains are planned for future implementation:

- **Savings Account** - Similar to Current Account with interest calculations
- **Customer Offer** - Product offerings and eligibility
- **Party Reference Data Directory** - Customer information management
- **Regulatory Reporting** - Compliance and reporting functions
- **Financial Accounting** - General ledger and accounting entries