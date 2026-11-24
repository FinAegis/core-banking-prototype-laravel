# Voting API Endpoints Documentation

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

This document describes the voting and governance endpoints of the GCU (Global Currency Unit) demo. Votes are simulated and have no monetary effect.

The endpoints belong to the `Governance` module. The production Zelta configuration lists `Governance` in `MODULES_DISABLED`, so these routes exist only on demo and self-hosted deployments that keep the module on.

## Overview

The voting system has three groups of endpoints:
1. The user voting endpoints (`/api/voting/*`) let a user vote on the basket composition.
2. The governance poll endpoints (`/api/polls/*`) create, manage and vote on polls of any type.
3. The vote management endpoints (`/api/votes/*`) list votes, show statistics and verify a vote.

## Authentication

All endpoints require authentication using Laravel Sanctum:
```
Authorization: Bearer {token}
```

## User Voting Interface

These endpoints give a simpler interface for GCU basket voting.

### Get Active Polls

```http
GET /api/voting/polls
```

This endpoint returns the active polls. Each poll shows whether the user has voted and how much voting power the user has.

**Response:**
```json
{
    "data": [
        {
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "title": "GCU Currency Basket Composition - September 2024",
            "description": "Vote on the currency composition for GCU for September 2024",
            "type": "weighted_choice",
            "status": "active",
            "options": [
                {"id": "USD", "label": "US Dollar"},
                {"id": "EUR", "label": "Euro"}
            ],
            "start_date": "2024-09-01T00:00:00.000000Z",
            "end_date": "2024-09-07T23:59:59.000000Z",
            "required_participation": null,
            "current_participation": 23.5,
            "user_context": {
                "has_voted": false,
                "voting_power": 1000,
                "can_vote": true,
                "vote": null
            },
            "metadata": {
                "is_gcu_poll": true,
                "voting_month": "2024-09",
                "template": "monthly_basket"
            },
            "results_visible": false,
            "time_remaining": {
                "days": 3,
                "hours": 5,
                "human_readable": "3 days before"
            }
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

This endpoint returns the draft polls that will open within the next 30 days. Each poll has the same fields as in the active poll list.

### Get Voting History

```http
GET /api/voting/polls/history
```

This endpoint returns the polls the signed-in user has voted in, 10 per page. Each poll has the same fields as in the active poll list, and `user_context.vote` holds the user's vote. The response also has `meta.total_votes` and `meta.member_since`.

### Submit Basket Vote

```http
POST /api/voting/polls/{uuid}/vote
```

This endpoint records a weighted allocation vote on the basket composition. It accepts only polls whose `metadata.template` is `monthly_basket`.

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
- Each allocation must be a number between 0 and 100.
- The allocations must add up to 100.
- The user must have voting power above zero.
- A user can vote only once in a poll.

**Success Response (201):**
```json
{
    "message": "Your vote has been recorded successfully",
    "vote_id": "880e8400-e29b-41d4-a716-446655440003",
    "voting_power_used": 1000
}
```

**Error Responses:**
- `400` means the poll is not a basket voting poll.
- `403` means the user has no voting power or has already voted.
- `404` means the poll was not found.
- `422` means validation failed or the allocations do not add up to 100.

### Get Voting Dashboard

```http
GET /api/voting/dashboard
```

This endpoint returns the voting dashboard of the signed-in user.

**Response:**
```json
{
    "data": {
        "stats": {
            "active_polls": 1,
            "votes_cast": 12,
            "gcu_balance": 1000,
            "voting_power": 1000
        },
        "next_poll": {
            "title": "GCU Currency Basket Composition - October 2024",
            "starts_in": "5 days before",
            "start_date": "2024-10-01T00:00:00.000000Z"
        },
        "basket_info": {
            "name": "Global Currency Unit",
            "code": "GCU",
            "symbol": "Ǥ"
        }
    }
}
```

`next_poll` is `null` when no poll is scheduled.

## Governance Polls Endpoints

These are the general poll endpoints. They support every poll type.

### List All Polls

```http
GET /api/polls
```

**Query Parameters:**
- `status` - Filter by status (`draft`, `pending`, `active`, `closed`, `executed`, `cancelled` or `failed`)
- `type` - Filter by poll type
- `page` - Page number for pagination
- `per_page` - Items per page (default 15, at most 100)

### Get Active Polls

```http
GET /api/polls/active
```

This endpoint returns only the polls that are open now.

### Create Poll

```http
POST /api/polls
```

This endpoint creates a poll. Any signed-in user can create a poll. Only platform operators can attach an `execution_workflow`, and other users get `403` when they try.

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
    "start_date": "2024-09-01",
    "end_date": "2024-09-07",
    "voting_power_strategy": "asset_weighted_vote",
    "execution_workflow": "App\\Domain\\Governance\\Workflows\\AddAssetWorkflow"
}
```

`type` is one of `single_choice`, `multiple_choice`, `weighted_choice`, `yes_no` and `ranked_choice`. A poll needs at least two options, and `start_date` must lie in the future. `voting_power_strategy` is `one_user_one_vote` or `asset_weighted_vote`.

### Get Poll Details

```http
GET /api/polls/{uuid}
```

This endpoint returns the details of one poll.

### Activate Poll

```http
POST /api/polls/{uuid}/activate
```

This endpoint opens a draft poll. The creator of the poll can open it, unless the poll has an execution workflow. Platform operators can open any poll.

### Submit Vote

```http
POST /api/polls/{uuid}/vote
```

This is the general vote endpoint for any poll. The request lists the ids of the chosen options.

**Request Body:**
```json
{
    "selected_options": ["yes"]
}
```

### Get Poll Results

```http
GET /api/polls/{uuid}/results
```

This endpoint returns the current results of a poll.

**Response:**
```json
{
    "data": {
        "poll_uuid": "550e8400-e29b-41d4-a716-446655440000",
        "total_votes": 156,
        "total_voting_power": 125000,
        "option_results": { "...": "..." },
        "participation_rate": 23.5,
        "winning_option": null,
        "metadata": {}
    }
}
```

### Check Voting Power

```http
GET /api/polls/{uuid}/voting-power
```

This endpoint returns the voting power of the signed-in user in one poll.

**Response:**
```json
{
    "voting_power": 1000,
    "can_vote": true,
    "has_voted": false,
    "strategy": "asset_weighted_vote"
}
```

## Vote Management Endpoints

### List Votes

```http
GET /api/votes
```

This endpoint lists the signed-in user's own votes, 15 per page by default.

**Query Parameters:**
- `poll_id` - Filter by poll
- `per_page` - Items per page (at most 100)

### Get Vote Statistics

```http
GET /api/votes/stats
```

This endpoint returns the voting statistics of the signed-in user.

**Response:**
```json
{
    "total_votes": 12,
    "total_voting_power": 10200,
    "recent_votes": 2,
    "avg_voting_power": 850.0,
    "participation_rate": 50.0
}
```

`recent_votes` counts the votes of the last 30 days.

### Get Vote Details

```http
GET /api/votes/{id}
```

This endpoint returns the details of one vote.

### Verify Vote

```http
POST /api/votes/{id}/verify
```

This endpoint checks the signature of a vote to confirm that it was not changed.

**Response:**
```json
{
    "verified": true,
    "message": "Vote signature is valid and vote has not been tampered with"
}
```

## Error Responses

Most voting errors come back as a plain message in `error` or `message` with an HTTP status. Validation errors use the standard Laravel format with an `errors` object.

```json
{
    "error": "You have already voted in this poll"
}
```

The status tells the client what went wrong:
- `400` means the data is not valid for this poll, for example a basket vote on a poll that is not a basket poll.
- `401` means the token is missing or not valid.
- `403` means the user has no voting power, has already voted or lacks permission.
- `404` means the poll was not found.
- `422` means validation failed, for example when the allocations do not add up to 100.

## Rate Limiting

The voting endpoints are rate limited. The poll lists, the dashboard and the vote lists use the `query` limit, which allows 100 requests per minute by default. Vote submission uses the `vote` transaction limit, which allows 50 votes per hour and 100 per day by default. Creating and activating polls uses the `admin` limit, which allows 200 requests per minute by default. The values are set in `config/rate_limiting.php`.

## Webhooks

`poll.created`, `poll.activated`, `poll.completed` and `vote.cast` are listed as event types for webhook subscriptions (`GET /api/v2/webhooks/events`). No code sends these events yet, so a subscription to them receives nothing.

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

Use these cURL commands to test the endpoints on a deployment that has the `Governance` module on:

```bash
# Get active polls
curl -X GET https://your-finaegis-host.example/api/voting/polls \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Submit vote
curl -X POST https://your-finaegis-host.example/api/voting/polls/{uuid}/vote \
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