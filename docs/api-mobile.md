# Mobile API Documentation

Base URL: `/api`
Auth: **Laravel Sanctum — Bearer token** (Personal Access Token)
All responses: `application/json` unless noted otherwise.

---

## Authentication

All authenticated endpoints require:
```
Authorization: Bearer <token>
```

Tokens are obtained from the login endpoint and stored on-device (Keychain / Keystore).
Tokens do not expire by default — revoke explicitly on logout.

---

## Endpoints

### Auth — Public

#### `POST /api/mobile/login`

Authenticate and receive a bearer token. Rate limited to **10 requests per minute**.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `email` | string | Yes | User's email address |
| `password` | string | Yes | User's password |
| `device_name` | string | Yes | Label for this session, e.g. `"iPhone 15 Pro"` (max 255) |

**Example request**
```json
{
  "email": "ca1@example.com",
  "password": "password",
  "device_name": "iPhone 15 Pro"
}
```

**Response `200`**
```json
{
  "token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": "uuid",
    "name": "CA1",
    "email": "ca1@example.com",
    "type": "human"
  }
}
```

**Errors**

| Status | Reason |
|---|---|
| `422` | Invalid credentials or suspended account |
| `429` | Rate limit exceeded |

---

### Auth — Authenticated

#### `GET /api/mobile/me`

Returns the authenticated user and their tenant memberships.

**Response `200`**
```json
{
  "user": {
    "id": "uuid",
    "name": "CA1",
    "email": "ca1@example.com",
    "type": "human",
    "status": "active"
  },
  "tenants": [
    {
      "id": "uuid",
      "name": "Alpha Advisors",
      "type": "ca_firm",
      "status": "active",
      "role": "owner",
      "permissions": ["invoices.view", "invoices.create", "products.view"]
    },
    {
      "id": "uuid",
      "name": "Tili",
      "type": "business",
      "status": "active",
      "role": "member",
      "permissions": ["invoices.view"]
    }
  ]
}
```

---

#### `POST /api/mobile/logout`

Revokes the current token (signs out this device).

**Response `200`**
```json
{ "message": "Token revoked." }
```

---

#### `GET /api/mobile/tokens`

Lists all active tokens (device sessions) for the authenticated user. Useful for a "manage devices" screen.

**Response `200`**
```json
{
  "tokens": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "last_used_at": "2026-04-15T10:00:00Z",
      "created_at": "2026-04-10T08:00:00Z"
    }
  ]
}
```

---

#### `DELETE /api/mobile/tokens/{tokenId}`

Revokes a specific token by its ID. Use to sign out another device remotely.

**URL params**

| Param | Type | Notes |
|---|---|---|
| `tokenId` | integer | Token ID from `GET /tokens` |

**Response `200`**
```json
{ "message": "Token revoked." }
```

**Errors**

| Status | Reason |
|---|---|
| `404` | Token not found or belongs to another user |

---

#### `DELETE /api/mobile/tokens`

Revokes **all** tokens — signs out every device.

**Response `200`**
```json
{ "message": "All tokens revoked." }
```

---

### Tenant — Banking

All tenant routes require:
- Valid Bearer token
- User must be a **member of the tenant** (`member` middleware)
- Additional **permission** per route (see below)

Base prefix: `/api/mobile/tenants/{tenant}`

---

#### `GET /api/mobile/tenants/{tenant}/banking/narration-heads`

**Permission required:** `transactions.view`

Returns all active narration heads and their sub-heads. Use this to populate dropdowns in the transaction correction UI.

**Response `200`**
```json
{
  "heads": [
    {
      "id": 1,
      "name": "Sales",
      "slug": "sales",
      "type": "income",
      "sub_heads": [
        {
          "id": 10,
          "ledger_code": "SL001",
          "ledger_name": "Product Sales",
          "requires_party": false
        }
      ]
    }
  ]
}
```

---

#### `GET /api/mobile/tenants/{tenant}/banking/pending`

**Permission required:** `transactions.view`

Paginated list of pending and reviewed transactions for the tenant. Includes AI-suggested invoice matches for unreconciled transactions (top 3).

**Query params**

| Param | Type | Default | Notes |
|---|---|---|---|
| `page` | integer | `1` | Page number |
| `per_page` | integer | `25` | Max `50` |

**Response `200`** — Laravel paginator format
```json
{
  "data": [
    {
      "id": "uuid",
      "transaction_date": "2026-04-15",
      "amount": 5000.00,
      "type": "credit",
      "description": "NEFT CR - ABC CORP",
      "review_status": "pending",
      "is_reconciled": false,
      "narration_head": { "id": 1, "name": "Sales" },
      "narration_sub_head": null,
      "reconciled_invoice": null,
      "invoice_suggestions": [
        {
          "id": 42,
          "invoice_number": "INV-0001",
          "client_name": "ABC Corp",
          "amount_due": 5000.00,
          "total_amount": 5000.00,
          "invoice_date": "2026-04-10",
          "status": "sent",
          "match_score": 0.95,
          "match_reasons": ["amount_match", "party_name_match"]
        }
      ]
    }
  ],
  "current_page": 1,
  "per_page": 25,
  "total": 120,
  "last_page": 5
}
```

---

#### `POST /api/mobile/tenants/{tenant}/banking/ingest/sms`

**Permission required:** `transactions.import`

Parse a raw bank SMS and create a pending transaction.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `raw_sms` | string | Yes | Raw SMS text from the bank (min 10, max 1000) |
| `bank_account_name` | string | No | Account label, e.g. `"HDFC Savings"` (max 255) |

**Example request**
```json
{
  "raw_sms": "INR 5,000.00 credited to A/C XX1234 on 15-Apr-26 by NEFT from ABC CORP. Avail Bal: INR 25,000.00",
  "bank_account_name": "HDFC Savings"
}
```

**Response `200`**
```json
{ "message": "SMS processed and transaction added for review." }
```

---

#### `POST /api/mobile/tenants/{tenant}/banking/ingest/email`

**Permission required:** `transactions.import`

Parse a bank notification email and create a pending transaction.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `email_body` | string | Yes | Email body text (min 10, max 10000) |
| `email_subject` | string | No | Email subject line (max 500) |
| `bank_account_name` | string | No | Account label (max 255) |

**Example request**
```json
{
  "email_subject": "Credit Alert - HDFC Bank",
  "email_body": "Dear Customer, INR 5,000 has been credited to your account ending 1234 on 15-Apr-2026.",
  "bank_account_name": "HDFC Current"
}
```

**Response `200`**
```json
{ "message": "Email processed and transaction added for review." }
```

---

#### `POST /api/mobile/tenants/{tenant}/banking/ingest/statement`

**Permission required:** `transactions.import`

Upload a bank statement file. Processed synchronously (timeout: 300s).

**Content-Type:** `multipart/form-data`

**Request fields**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `statement` | file | Yes | PDF, JPG, PNG, CSV, or XLSX — max **20 MB** |
| `bank_account_name` | string | No | Account label (max 255) |

**Response `200`**
```json
{
  "message": "Statement processed: 45 imported, 5 duplicates skipped, 0 failed out of 50 total.",
  "stats": {
    "total": 50,
    "imported": 45,
    "duplicates": 5,
    "failed": 0
  }
}
```

**Response `422`** — when file was parsed but zero transactions were imported or skipped (likely an unrecognised format)
```json
{
  "message": "Statement processed: 0 imported, 0 duplicates skipped, 0 failed out of 0 total.",
  "stats": { "total": 0, "imported": 0, "duplicates": 0, "failed": 0 }
}
```

---

#### `POST /api/mobile/tenants/{tenant}/banking/transactions/{transaction}/approve`

**Permission required:** `transactions.review`

Mark a transaction as reviewed (no narration changes).

**URL params**

| Param | Type | Notes |
|---|---|---|
| `transaction` | UUID | Transaction ID |

**Response `200`**
```json
{ "message": "Transaction approved." }
```

**Errors**

| Status | Reason |
|---|---|
| `403` | Missing `transactions.review` permission |
| `404` | Transaction not found or belongs to a different tenant |

---

#### `POST /api/mobile/tenants/{tenant}/banking/transactions/{transaction}/correct`

**Permission required:** `transactions.edit`

Re-categorise a transaction. Optionally save as a learned rule and/or reconcile to an invoice.

**URL params**

| Param | Type | Notes |
|---|---|---|
| `transaction` | UUID | Transaction ID |

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `narration_head_id` | integer | Yes | Must exist in `narration_heads` |
| `narration_sub_head_id` | integer | No | Must exist in `narration_sub_heads` |
| `narration_note` | string | No | Free-text note (max 500) |
| `party_name` | string | No | Payer / payee name (max 255) |
| `save_as_rule` | boolean | No | If `true`, saves this mapping as a learned rule (default `false`) |
| `invoice_id` | integer | No | Reconcile to this invoice by ID |
| `invoice_number` | string | No | Reconcile to this invoice by number (max 100) |
| `unreconcile` | boolean | No | If `true`, removes existing reconciliation (default `false`) |

**Example request**
```json
{
  "narration_head_id": 1,
  "narration_sub_head_id": 10,
  "party_name": "ABC Corp",
  "narration_note": "Q1 payment received",
  "save_as_rule": true,
  "invoice_number": "INV-0001"
}
```

**Response `200`**
```json
{ "message": "Transaction corrected." }
```

**Errors**

| Status | Reason |
|---|---|
| `403` | Missing `transactions.edit` permission |
| `404` | Transaction not found or belongs to a different tenant |
| `422` | Validation failed (e.g. invalid narration_head_id) |

---

### Tenant — Chat

#### `POST /api/mobile/tenants/{tenant}/chat`

**Permission required:** `chat.view`

Send a message to the AI accounting assistant. Returns a **Server-Sent Events (SSE)** stream.

**Response Content-Type:** `text/event-stream`

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `message` | string | Yes | User's message (max 2000 chars) |
| `history` | array | No | Prior conversation turns (see below) |

**`history` item shape**

| Field | Type | Values |
|---|---|---|
| `role` | string | `"user"` or `"assistant"` |
| `content` | string | Message text |

**Example request**
```json
{
  "message": "Create an invoice for ABC Corp for 5000",
  "history": [
    { "role": "user", "content": "Who are my clients?" },
    { "role": "assistant", "content": "You have 3 clients: ABC Corp, XYZ Ltd, Acme Inc." }
  ]
}
```

**SSE stream format**

Each event is a `data:` line followed by a blank line:
```
data: {"type":"reply","reply":"Invoice INV-0001 created for ABC Corp...","success":true,...}

data: [DONE]

```

**Success event payload**

| Field | Type | Notes |
|---|---|---|
| `type` | string | Always `"reply"` |
| `reply` | string | Assistant's text response |
| `success` | boolean | `true` |
| `clients` | array\|null | Client records returned/created during this turn |
| `products` | array\|null | Product records returned/created |
| `invoices` | array\|null | Invoice list if queried |
| `invoice` | object\|null | Created or updated invoice |
| `created_client` | object\|null | Newly created client |
| `created_product` | object\|null | Newly created product |

**Error event payload**
```json
{ "type": "error", "reply": "Sorry, I encountered an error: ...", "success": false }
```

**Errors**

| Status | Reason |
|---|---|
| `403` | Missing `chat.view` permission |
| `422` | Validation failed (e.g. message too long) |

---

## Error Response Format

All validation errors return standard Laravel format:

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Auth errors:
```json
{ "message": "Unauthenticated." }
```

Permission errors:
```json
{ "message": "This action is unauthorized." }
```

---

## Permission Requirements Summary

| Endpoint | Permission |
|---|---|
| `POST /mobile/login` | Public |
| `GET /mobile/me` | Authenticated |
| `POST /mobile/logout` | Authenticated |
| `GET /mobile/tokens` | Authenticated |
| `DELETE /mobile/tokens/{id}` | Authenticated |
| `DELETE /mobile/tokens` | Authenticated |
| `GET .../banking/narration-heads` | `transactions.view` |
| `GET .../banking/pending` | `transactions.view` |
| `POST .../banking/ingest/sms` | `transactions.import` |
| `POST .../banking/ingest/email` | `transactions.import` |
| `POST .../banking/ingest/statement` | `transactions.import` |
| `POST .../banking/transactions/{id}/approve` | `transactions.review` |
| `POST .../banking/transactions/{id}/correct` | `transactions.edit` |
| `POST .../chat` | `chat.view` |

---

## Seeded Test Credentials

All passwords: **`password`**

| User | Email | Good for testing |
|---|---|---|
| CA1 | ca1@example.com | Chat, banking (Tili, Awab), CA firm |
| CA2 | ca2@example.com | Chat, banking (Eightsis), CA firm |
| Fela | fela@example.com | Full owner access (Tili, Awab, Eightsis) |
| Raunak | raunak@example.com | Staff access (Eightsis) — limited permissions |
| Viewer | — | No seeded viewer user yet |
