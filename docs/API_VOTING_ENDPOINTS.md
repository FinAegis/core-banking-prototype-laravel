# Voting API Endpoints Documentation

This document describes the voting and governance API endpoints implemented in Phase 4.2 for the GCU (Global Currency Unit) platform.

## Overview

The voting system provides three sets of endpoints:
1. **User Voting Interface** (`/api/voting/*`) - User-friendly endpoints for voting on basket composition
2. **Governance Polls** (`/api/polls/*`) - General poll management and voting
3. **Vote Management** (`/api/votes/*`) - Vote verification and statistics

## Authentication

All endpoints require authentication using Laravel Sanctum:
```
Authorization: Bearer {token}
```

## User Voting Interface

These endpoints provide a simplified interface specifically designed for GCU basket voting.

### Get Active Polls

```http
GET /api/voting/polls
```

Returns all active polls available for voting, including user's voting status and power.

**Response:**
```json
{
    "data": [
        {
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "title": "GCU Currency Basket Composition - July 2025",
            "description": "Vote on the currency composition for GCU for July 2025",
            "type": "weighted_choice",
            "status": "active",
            "start_date": "2025-07-01T00:00:00Z",
            "end_date": "2025-07-07T23:59:59Z",
            "current_basket": {
                "USD": 40,
                "EUR": 30,
                "GBP": 15,
                "CHF": 10,
                "JPY": 3,
                "XAU": 2
            },
            "user_has_voted": false,
            "user_voting_power": 1000,
            "total_votes": 156,
            "participation_rate": 23.5
        }
    ],
    "meta": {
        "basket_name": "Global Currency Unit",
        "basket_code": "GCU",
        "basket_symbol": "Ǥ"
    }
}
```

### Get Upcoming Polls

```http
GET /api/voting/polls/upcoming
```

Returns polls that will become active within the next 30 days.

**Response:**
```json
{
    "data": [
        {
            "uuid": "660e8400-e29b-41d4-a716-446655440001",
            "title": "GCU Currency Basket Composition - August 2025",
            "status": "draft",
            "start_date": "2025-08-01T00:00:00Z",
            "end_date": "2025-08-07T23:59:59Z",
            "days_until_active": 11
        }
    ]
}
```

### Get Voting History

```http
GET /api/voting/polls/history
```

Returns paginated list of polls the authenticated user has participated in.

**Response:**
```json
{
    "data": [
        {
            "uuid": "770e8400-e29b-41d4-a716-446655440002",
            "title": "GCU Currency Basket Composition - June 2025",
            "status": "completed",
            "end_date": "2025-06-07T23:59:59Z",
            "user_vote": {
                "allocations": {
                    "USD": 35,
                    "EUR": 25,
                    "GBP": 20,
                    "CHF": 10,
                    "JPY": 5,
                    "XAU": 5
                },
                "voting_power_used": 850,
                "voted_at": "2025-06-05T14:30:00Z"
            },
            "final_result": {
                "USD": 38,
                "EUR": 28,
                "GBP": 17,
                "CHF": 10,
                "JPY": 4,
                "XAU": 3
            }
        }
    ],
    "meta": {
        "total_votes": 24,
        "member_since": "2024-01-15"
    },
    "links": {
        "first": "/api/voting/polls/history?page=1",
        "last": "/api/voting/polls/history?page=3",
        "prev": null,
        "next": "/api/voting/polls/history?page=2"
    }
}
```

### Submit Basket Vote

```http
POST /api/voting/polls/{uuid}/vote
```

Submit a weighted allocation vote for basket composition.

**Request Body:**
```json
{
    "allocations": {
        "USD": 35,
        "EUR": 25,
        "GBP": 20,
        "CHF": 10,
        "JPY": 5,
        "XAU": 5
    }
}
```

**Validation Rules:**
- All allocations must be numeric values between 0 and 100
- Total allocations must sum to exactly 100
- User must have voting power > 0
- User cannot vote twice in the same poll

**Success Response (201):**
```json
{
    "message": "Your vote has been recorded successfully",
    "vote_id": "880e8400-e29b-41d4-a716-446655440003",
    "voting_power_used": 1000,
    "timestamp": "2025-07-03T10:15:30Z"
}
```

**Error Responses:**
- `400` - Invalid vote data or not a basket voting poll
- `403` - User has no voting power or already voted
- `404` - Poll not found
- `422` - Allocations don't sum to 100

### Get Voting Dashboard

```http
GET /api/voting/dashboard
```

Returns comprehensive voting dashboard data for the authenticated user.

**Response:**
```json
{
    "data": {
        "user_stats": {
            "total_votes_cast": 12,
            "voting_power": 1000,
            "member_since": "2024-01-15",
            "voting_streak": 3
        },
        "active_polls": 1,
        "upcoming_polls": 1,
        "current_basket": {
            "code": "GCU",
            "name": "Global Currency Unit",
            "composition": {
                "USD": 40,
                "EUR": 30,
                "GBP": 15,
                "CHF": 10,
                "JPY": 3,
                "XAU": 2
            },
            "last_rebalanced": "2025-07-01T00:00:00Z",
            "next_rebalancing": "2025-08-01T00:00:00Z"
        },
        "recent_activity": [
            {
                "type": "vote_cast",
                "poll_title": "GCU Currency Basket Composition - June 2025",
                "timestamp": "2025-06-05T14:30:00Z"
            },
            {
                "type": "poll_completed",
                "poll_title": "GCU Currency Basket Composition - June 2025",
                "timestamp": "2025-06-07T23:59:59Z",
                "result": "Basket updated"
            }
        ]
    }
}
```

## Governance Polls Endpoints

These are the general-purpose poll endpoints that support various types of governance decisions.

### List All Polls

```http
GET /api/polls
```

**Query Parameters:**
- `status` - Filter by status (draft, active, completed, cancelled)
- `type` - Filter by poll type
- `page` - Page number for pagination
- `per_page` - Items per page (default: 15)

### Get Active Polls

```http
GET /api/polls/active
```

Returns only currently active polls.

### Create Poll

```http
POST /api/polls
```

Creates a new governance poll (requires admin permissions).

**Request Body:**
```json
{
    "title": "Add support for Singapore Dollar?",
    "description": "Should we add SGD to the available currencies?",
    "type": "single_choice",
    "options": [
        {"id": "yes", "label": "Yes, add SGD"},
        {"id": "no", "label": "No, not needed"}
    ],
    "start_date": "2025-08-01",
    "end_date": "2025-08-07",
    "voting_power_strategy": "App\\Domain\\Governance\\Strategies\\AssetWeightedVotingStrategy",
    "execution_workflow": "App\\Domain\\Governance\\Workflows\\AddAssetWorkflow"
}
```

### Get Poll Details

```http
GET /api/polls/{uuid}
```

Returns detailed information about a specific poll.

### Activate Poll

```http
POST /api/polls/{uuid}/activate
```

Activates a draft poll (requires admin permissions).

### Submit Vote

```http
POST /api/polls/{uuid}/vote
```

Submit a vote to any poll (general-purpose endpoint).

**Request Body varies by poll type:**

For single/multiple choice:
```json
{
    "option_id": "yes"
}
```

For weighted choice (like basket voting):
```json
{
    "options": {
        "USD": 40,
        "EUR": 30,
        "GBP": 30
    }
}
```

### Get Poll Results

```http
GET /api/polls/{uuid}/results
```

Returns the current results of a poll.

**Response:**
```json
{
    "data": {
        "poll_uuid": "550e8400-e29b-41d4-a716-446655440000",
        "status": "active",
        "total_votes": 156,
        "total_voting_power": 125000,
        "results": {
            "USD": {
                "votes": 156,
                "voting_power": 125000,
                "average_weight": 38.5
            },
            "EUR": {
                "votes": 156,
                "voting_power": 125000,
                "average_weight": 27.8
            }
            // ... other currencies
        },
        "participation_rate": 23.5
    }
}
```

### Check Voting Power

```http
GET /api/polls/{uuid}/voting-power
```

Returns the authenticated user's voting power for a specific poll.

**Response:**
```json
{
    "data": {
        "voting_power": 1000,
        "calculation_method": "asset_weighted",
        "primary_asset_balance": 100000,
        "explanation": "1 vote per 100 GCU held"
    }
}
```

## Vote Management Endpoints

### List Votes

```http
GET /api/votes
```

Lists all votes (admin only) or user's own votes.

**Query Parameters:**
- `poll_id` - Filter by poll
- `user_uuid` - Filter by user (admin only)

### Get Vote Statistics

```http
GET /api/votes/stats
```

Returns voting statistics.

**Response:**
```json
{
    "data": {
        "total_votes": 3567,
        "unique_voters": 892,
        "total_voting_power_used": 2450000,
        "most_active_poll": {
            "title": "GCU Currency Basket Composition - June 2025",
            "votes": 234
        },
        "participation_by_month": {
            "2025-01": 18.5,
            "2025-02": 21.3,
            "2025-03": 19.8,
            "2025-04": 22.1,
            "2025-05": 24.6,
            "2025-06": 23.5
        }
    }
}
```

### Get Vote Details

```http
GET /api/votes/{id}
```

Returns details of a specific vote.

### Verify Vote

```http
POST /api/votes/{id}/verify
```

Verifies the integrity of a vote using its signature.

**Response:**
```json
{
    "data": {
        "valid": true,
        "vote_id": "880e8400-e29b-41d4-a716-446655440003",
        "signature": "SHA3-512:abc123...",
        "timestamp": "2025-07-03T10:15:30Z"
    }
}
```

## Error Responses

All endpoints return consistent error responses:

```json
{
    "error": "Error message",
    "code": "ERROR_CODE",
    "details": {}
}
```

Common error codes:
- `UNAUTHORIZED` - Missing or invalid authentication
- `FORBIDDEN` - Insufficient permissions
- `NOT_FOUND` - Resource not found
- `VALIDATION_ERROR` - Invalid input data
- `ALREADY_VOTED` - User has already voted in this poll
- `POLL_CLOSED` - Poll is no longer accepting votes
- `NO_VOTING_POWER` - User has no voting power

## Rate Limiting

Voting endpoints are rate-limited to prevent abuse:
- Vote submission: 1 request per minute per user
- Poll listing: 60 requests per minute
- Dashboard: 30 requests per minute

## Webhooks

When polls complete, webhooks can be configured to notify external systems:

```json
{
    "event": "poll.completed",
    "poll": {
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "title": "GCU Currency Basket Composition - July 2025",
        "final_results": {
            "USD": 38,
            "EUR": 28,
            "GBP": 17,
            "CHF": 10,
            "JPY": 4,
            "XAU": 3
        }
    },
    "timestamp": "2025-07-07T23:59:59Z"
}
```

## Integration Examples

### JavaScript/Vue.js
```javascript
// Get active polls
const response = await fetch('/api/voting/polls', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
    }
});

const { data } = await response.json();

// Submit vote
const voteResponse = await fetch(`/api/voting/polls/${pollUuid}/vote`, {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        allocations: {
            USD: 35,
            EUR: 25,
            GBP: 20,
            CHF: 10,
            JPY: 5,
            XAU: 5
        }
    })
});
```

### PHP/Laravel
```php
use Illuminate\Support\Facades\Http;

// Get voting dashboard
$response = Http::withToken($token)
    ->get('/api/voting/dashboard');

$dashboard = $response->json('data');

// Submit vote
$voteResponse = Http::withToken($token)
    ->post("/api/voting/polls/{$pollUuid}/vote", [
        'allocations' => [
            'USD' => 35,
            'EUR' => 25,
            'GBP' => 20,
            'CHF' => 10,
            'JPY' => 5,
            'XAU' => 5,
        ]
    ]);
```

## Testing

Use the following cURL commands to test the endpoints:

```bash
# Get active polls
curl -X GET https://api.finaegis.com/api/voting/polls \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Submit vote
curl -X POST https://api.finaegis.com/api/voting/polls/{uuid}/vote \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "allocations": {
        "USD": 35,
        "EUR": 25,
        "GBP": 20,
        "CHF": 10,
        "JPY": 5,
        "XAU": 5
    }
  }'
```