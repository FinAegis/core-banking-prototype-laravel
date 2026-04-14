# VertexSMS API Reference

API URL: https://kube-api.vertexsms.com

---

## POST /sms

`https://kube-api.vertexsms.com/sms`

Submit one or more messages for sending. Returns HTTP 200 and an array of message IDs added to the sending queue.

### Required parameters

| Field | Type | Description |
|---|---|---|
| `to` | string | Destination address (recipient) |
| `from` | string | Source address (originator) |
| `message` | string | Message body. |

### Optional parameters

| Field | Type | Description |
|---|---|---|
| `dlrUrl` | url | Endpoint to receive delivery reports. Reports include the same message ID returned on submit. |

---

## POST /sms/cost

`https://kube-api.vertexsms.com/sms/cost`

Calculate the cost of sending message(s) without actually sending them. Accepts the same request body as `POST /sms`.

### Request body

Single message or array — same format as `POST /sms`.

**Single message:**
```json
{ "from": "VertexSMS", "to": "37061234567", "message": "Hello world" }
```

**Multiple messages:**
```json
[
  { "from": "VertexSMS", "to": "37061234567", "message": "Hello" },
  { "from": "VertexSMS", "to": "44712345678", "message": "World" }
]
```

### Response

Array of cost results, one per message.

| Field | Type | Description |
|---|---|---|
| `from` | string | Sender ID |
| `to` | string | Recipient number |
| `parts` | int | Number of SMS parts after auto-split |
| `countryISO` | string | Detected country ISO code |
| `mccmnc` | string / null | Mobile Country Code + Mobile Network Code |
| `pricePerPart` | float | Price per single SMS part |
| `totalPrice` | float | `pricePerPart × parts` |
| `currency` | string | Always `EUR` |

**Example response:**
```json
[{
  "from": "VertexSMS",
  "to": "37061234567",
  "parts": 1,
  "countryISO": "LT",
  "mccmnc": "24601",
  "pricePerPart": 0.035,
  "totalPrice": 0.035,
  "currency": "EUR"
}]
```

### Errors

| Code | Reason |
|---|---|
| `400` | Malformed request, unrecognized destination country, no route configured, or no price configured for destination |

---

## GET /sms/status/{messageID}

Returns the delivery status of a single SMS by its message ID.

### Request

```http
GET /sms/status/1281532560
```

### Response

```json
{
  "id": 1281532560,
  "status": "delivered",
  "type": "SMS",
  "createDate": "2026-04-14 10:30:00",
  "sentDate": "2026-04-14 10:30:01",
  "dlrDate": "2026-04-14 10:30:03",
  "from": "VertexSMS",
  "to": "37069912345",
  "text": "Test message",
  "udh": null,
  "dlrStatus": 1,
  "error": 0
}
```

### Response fields

| Field | Type | Description |
|---|---|---|
| `id` | int | Message ID (same ID returned when SMS was submitted via `POST /sms`) |
| `status` | string | Human-readable delivery status (see table below) |
| `type` | string | Message type (`SMS`, `HLR`) |
| `createDate` | string | When message was submitted (`YYYY-MM-DD HH:MM:SS`) |
| `sentDate` | string | When message was sent to the operator |
| `dlrDate` | string | When delivery report was received |
| `from` | string | Sender ID |
| `to` | string | Recipient number |
| `text` | string | Message body |
| `udh` | string / null | User Data Header (hex, `0x`-prefixed) or null |
| `dlrStatus` | int | Numeric delivery status code |
| `error` | int | Error code (`0` if no error) |

### Status values

| `status` | `dlrStatus` | Description |
|---|---|---|
| `pending` | `0` | Message is queued, not yet sent to the operator |
| `sent` | `0` | Message was sent to the operator, awaiting delivery report |
| `delivered` | `1` | Message was delivered to the recipient |
| `undelivered` | `2` | Message could not be delivered |
| `expired` | `16` | Message validity period expired before delivery |

### Errors

| Code | Reason |
|---|---|
| `400` | Missing or invalid message ID |
| `401` | No authentication token provided |
| `404` | Message not found (archived, does not exist, or belongs to another user) |

---

## GET /rates

Returns SMS rates (sell prices) configured for the authenticated user.

### Query parameters

| Param | Description |
|---|---|
| `CountryISO` | Filter by 2-letter country ISO code |

### Request

```http
GET /rates?CountryISO=LT
```

### Response

```json
[
  {
    "CountryName": "Lithuania",
    "CountryCode": "LT",
    "Operator": "Telia",
    "Network": "Omnitel",
    "MCC": "246",
    "MNC": "01",
    "Rate": "0.0350"
  },
  {
    "CountryName": "Lithuania",
    "CountryCode": "LT",
    "Operator": "Bite",
    "Network": "Bite",
    "MCC": "246",
    "MNC": "02",
    "Rate": "0.0350"
  }
]
```

---

## Delivery reports (DLR callback)

After each send attempt, the Vertex server POSTs a JSON delivery receipt to your `dlrUrl` (if url was provided).

```http
POST /customer_dlr_endpoint HTTP/1.1
Host: customerserver.com
Content-Type: application/json

{ "id": "1281532560", "status": "1", "error": 0, "mcc": "246", "mnc": "021" }
```

### Payload fields

| Field | Type | Description |
|---|---|---|
| `id` | int | Message ID (BufferID) — same ID returned on submit |
| `status` | int | `1` — Delivered. `2` — Undelivered. `3` — Seen (Viber). `16` — Expired. |
| `error` | int | `0` when delivered. Non-zero mapped error code when undelivered. |
| `mcc` | string | Mobile Country Code (3 digits) |
| `mnc` | string | Mobile Network Code (up to 3 digits) |
