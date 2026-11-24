# BIAN Service Domain API

## Overview

This document describes the endpoints that FinAegis models on two service domains of the Banking Industry Architecture Network (BIAN). BIAN publishes standard definitions of banking services. Following them makes an API easier to map onto other banking systems.

The endpoints follow BIAN naming for service domains, control records and behavior qualifiers. They are a reference implementation and are not certified by BIAN.

The whole `/api/bian` surface moves ledger balances directly. It therefore sits behind `LEDGER_DIRECT_FUNDING_ENABLED`, and every request answers `404` while the flag is off. The flag is on by default only when `APP_ENV` is `local`, `testing` or `demo`. The production Zelta configuration keeps it off.

## BIAN Concepts

### Service Domains
A service domain is one major banking capability. Each domain follows a functional pattern, such as Fulfill or Transact.

### Control Records (CR)
A control record tracks the lifecycle and state of one instance of a service domain. Each control record has a unique CR Reference ID.

### Behavior Qualifiers (BQ)
A behavior qualifier is one aspect of a service domain, such as "Payment", "Deposit" or "Account Balance".

### Standard Operations
BIAN defines standard operations on a service:
- **Initiate** creates a new instance.
- **Update** changes an existing instance.
- **Retrieve** reads information about an instance.
- **Control** changes the state, for example to freeze or suspend it.
- **Execute** performs an action.
- **Request** asks for specific information.
- **Grant** approves or authorises something.
- **Capture** records information.
- **Exchange** passes information between parties.

FinAegis uses Initiate, Update, Retrieve, Control, Execute and Request.

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
  "initialDeposit": 0,
  "currency": "USD"
}
```

An account opens empty. `initialDeposit` is deprecated and accepts only `0`. Any other value is rejected with `422`. `accountType` is `current` or `checking`.

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
      "amount": 0,
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

##### Retrieve Payment
```http
GET /api/bian/payment-initiation/{crReferenceId}/retrieve
Authorization: Bearer {token}
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
A successful response returns the complete record or arrangement, as BIAN does:

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
An error response carries structured error details:

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

## BIAN Conventions

### Control Record Reference IDs
- Every service domain instance has a unique CR Reference ID.
- FinAegis uses UUIDs for these IDs.
- An ID stays the same for the whole lifecycle of the instance.

### Behavior Qualifier Reference IDs
- Each behavior qualifier operation creates its own BQ Reference ID.
- The ID tracks that one operation within the service domain.

### Date Handling
- All dates use the ISO 8601 format.
- Timestamps include the time zone.
- Each date carries a named date type, for example "AccountOpeningDate".

### Amount Representation
- Amounts are integers in minor units, such as cents.
- The currency is always given as an ISO 4217 code.

### Status Values
- Status values follow the BIAN standard values where they apply.
- An account status is `active`, `dormant`, `closed` or `frozen`.
- A payment status is `initiated`, `scheduled`, `completed`, `failed` or `cancelled`.
- A control status is `active`, `frozen` or `suspended`.

## Mapping from the Core API

The list below shows which BIAN-style endpoint matches each core API endpoint:

1. **Account Creation**:
   - Core API: `POST /api/accounts`
   - BIAN: `POST /api/bian/current-account/initiate`

2. **Deposits**:
   - Core API: `POST /api/accounts/{uuid}/deposit`
   - BIAN: `POST /api/bian/current-account/{crReferenceId}/deposit/execute`

3. **Withdrawals**:
   - Core API: `POST /api/accounts/{uuid}/withdraw`
   - BIAN: `POST /api/bian/current-account/{crReferenceId}/payment/execute`

4. **Transfers**:
   - Core API: `POST /api/transfers`
   - BIAN: `POST /api/bian/payment-initiation/initiate`

5. **Balance Inquiry**:
   - Core API: `GET /api/accounts/{uuid}/balance`
   - BIAN: `GET /api/bian/current-account/{crReferenceId}/account-balance/retrieve`

## Future Service Domains

These BIAN service domains are not implemented. They are candidates for later work:

- **Savings Account** would work like Current Account and add interest calculations.
- **Customer Offer** would cover product offers and eligibility.
- **Party Reference Data Directory** would manage customer information.
- **Regulatory Reporting** would cover compliance and reporting functions.
- **Financial Accounting** would cover the general ledger and accounting entries.
