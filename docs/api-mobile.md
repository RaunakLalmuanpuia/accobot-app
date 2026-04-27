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

Paginated list of pending transactions for the tenant. Includes AI-suggested invoice matches for unreconciled transactions (top 3).

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

#### `GET /api/mobile/tenants/{tenant}/banking/reviewed`

**Permission required:** `transactions.view`

Paginated list of transactions with `review_status = reviewed` for the tenant.

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
      "review_status": "reviewed",
      "is_reconciled": false,
      "narration_head": { "id": 1, "name": "Sales" },
      "narration_sub_head": null,
      "reconciled_invoice": null
    }
  ],
  "current_page": 1,
  "per_page": 25,
  "total": 45,
  "last_page": 2
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

### Tenant — Group Chat

All group chat routes require:
- Valid Bearer token
- User must be a **member of the tenant** (`member` middleware)
- At minimum the `chat.room.view` permission (see per-endpoint notes)

Base prefix: `/api/mobile/tenants/{tenant}/groups`

---

#### `GET /api/mobile/tenants/{tenant}/groups`

**Permission required:** `chat.room.view`

Returns all chat rooms the authenticated user belongs to, sorted newest-activity first. System rooms (Notifications) appear in the list. Includes `unread_count` per room and `tenant_users` (all users in the tenant) for the invite UI when creating a new room.

**Response `200`**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "General",
      "type": "group",
      "is_system": false,
      "unread_count": 3,
      "latest_message": {
        "id": "uuid",
        "body": "Hey team!",
        "created_at": "2026-04-27T10:00:00Z",
        "sender": { "id": 1, "name": "CA1" }
      },
      "members": [
        { "user": { "id": 1, "name": "CA1" }, "role": "admin" }
      ]
    }
  ],
  "tenant_users": [
    { "id": 1, "name": "CA1" },
    { "id": 2, "name": "Fela" }
  ]
}
```

---

#### `POST /api/mobile/tenants/{tenant}/groups`

**Permission required:** `chat.room.create`

Create a new group chat room. The creator is automatically added as admin.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `name` | string | Yes | Room name (max 255) |
| `description` | string | No | Optional description (max 1000) |
| `member_ids` | array | No | Additional member user IDs (integers) |

**Response `201`**
```json
{
  "data": {
    "id": "uuid",
    "name": "Finance Team",
    "type": "group",
    "is_system": false,
    "members": [
      { "user": { "id": 1, "name": "CA1" }, "role": "admin" }
    ]
  }
}
```

---

#### `GET /api/mobile/tenants/{tenant}/groups/{room}`

**Permission required:** `chat.room.view`

Returns room details, members, the most recent 50 messages, and `tenant_users` (all users in the tenant). Use `can_load_more` to decide whether to show a "load earlier" control. Use `tenant_users` minus current `room.members` to populate the Add Member picker.

**Response `200`**
```json
{
  "room": {
    "id": "uuid",
    "name": "General",
    "type": "group",
    "is_system": false,
    "members": [
      { "user": { "id": 1, "name": "CA1" }, "role": "admin" }
    ]
  },
  "messages": [ /* message objects — see GET messages */ ],
  "can_load_more": true,
  "tenant_users": [
    { "id": 1, "name": "CA1" },
    { "id": 2, "name": "Fela" }
  ]
}
```

---

#### `GET /api/mobile/tenants/{tenant}/groups/{room}/messages`

**Permission required:** `chat.room.view`

Cursor-paginated message history (50 per page). Pass `before_id` to load older messages.

**Query params**

| Param | Type | Notes |
|---|---|---|
| `before_id` | UUID | Fetch messages older than this message ID |

**Response `200`**
```json
{
  "data": [
    {
      "id": "uuid",
      "body": "Hello!",
      "type": "text",
      "created_at": "2026-04-27T10:00:00Z",
      "sender": { "id": 1, "name": "CA1" },
      "reaction_summary": [
        { "emoji": "👍", "count": 2, "users": [1, 2] }
      ],
      "reply_to": null,
      "attachments": []
    }
  ],
  "can_load_more": false
}
```

---

#### `POST /api/mobile/tenants/{tenant}/groups/{room}/messages`

**Permission required:** `chat.message.send`

Send a message. Upload attachments first via the attachment endpoint, then include their IDs here.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `body` | string | No* | Message text (max 4000). Required if no `attachment_ids`. |
| `reply_to_message_id` | UUID | No | Message ID to reply to (must be in same room) |
| `attachment_ids` | array | No* | Up to 5 pre-uploaded attachment UUIDs |

**Response `201`**
```json
{
  "data": {
    "id": "uuid",
    "body": "Got it!",
    "type": "text",
    "created_at": "2026-04-27T10:05:00Z",
    "sender": { "id": 1, "name": "CA1" },
    "reaction_summary": [],
    "attachments": []
  }
}
```

---

#### `DELETE /api/mobile/tenants/{tenant}/groups/{room}/messages/{message}`

Delete a message. The message body is wiped and the record is soft-deleted. A broadcast update is fired so other clients remove it from view. Users may delete their own messages; `chat.message.delete` permission allows deleting others'.

**Response `200`**
```json
{ "ok": true }
```

**Errors**

| Status | Reason |
|---|---|
| `403` | Not the message owner and missing `chat.message.delete` permission |

---

#### `POST /api/mobile/tenants/{tenant}/groups/{room}/messages/{message}/reactions`

**Permission required:** `chat.room.view`

Toggle an emoji reaction on a message. Calling with the same emoji twice removes the reaction.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `emoji` | string | Yes | Single emoji character (max 10 bytes) |

**Response `200`**
```json
{
  "reaction_summary": [
    { "emoji": "👍", "count": 3, "users": [1, 2, 3] }
  ]
}
```

---

#### `POST /api/mobile/tenants/{tenant}/groups/{room}/read`

**Permission required:** `chat.room.view`

Mark a message as the user's last-read watermark. Broadcasts a read receipt to other room members.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `message_id` | UUID | Yes | ID of the latest message the user has seen |

**Response `200`**
```json
{ "ok": true }
```

---

#### `POST /api/mobile/tenants/{tenant}/groups/{room}/typing`

**Permission required:** `chat.room.view`

Broadcast a typing indicator. Call with `typing: true` when the user starts typing, `typing: false` when they stop. Indicators auto-expire after 5 seconds of silence on the receiving side.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `typing` | boolean | Yes | `true` = started typing, `false` = stopped |

**Response `200`**
```json
{ "ok": true }
```

---

#### `POST /api/mobile/tenants/{tenant}/groups/{room}/attachments`

**Permission required:** `chat.room.view`

Upload a file attachment (step 1 of 2). The returned `id` is passed to the send-message endpoint as `attachment_ids[]`. Files not linked to a message within 1 hour are automatically purged.

**Content-Type:** `multipart/form-data`

**Request fields**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `file` | file | Yes | jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, csv — max **20 MB** |

**Response `201`**
```json
{
  "data": {
    "id": "uuid",
    "original_filename": "invoice.pdf",
    "mime_type": "application/pdf",
    "size_bytes": 102400
  }
}
```

---

#### `GET /api/mobile/tenants/{tenant}/groups/{room}/attachments/{attachment}`

**Permission required:** `chat.room.view` (member of room)

Download an attachment as a binary stream. Set `Accept` to `*/*` and stream the response body to disk or display inline.

**Response `200`** — binary file stream with `Content-Disposition: attachment`

---

#### `POST /api/mobile/tenants/{tenant}/groups/{room}/members`

**Permission required:** `chat.room.manage`

Add a tenant member to the room.

**Request body**

| Field | Type | Required | Notes |
|---|---|:---:|---|
| `user_id` | integer | Yes | Must be a member of the tenant |
| `role` | string | No | `"admin"` or `"member"` (default `"member"`) |

**Response `200`**
```json
{ "ok": true }
```

---

#### `DELETE /api/mobile/tenants/{tenant}/groups/{room}/members/{user}`

**Permission required:** `chat.room.manage`

Remove a member from the room.

**Response `200`**
```json
{ "ok": true }
```

---

### Real-Time (WebSocket)

The mobile app connects to Laravel Reverb using the **Pusher protocol**. Use the official Pusher native SDK for iOS/Android.

**Connection details** (from your `.env` / app config):

| Key | Value |
|---|---|
| App key | `REVERB_APP_KEY` |
| Host | `REVERB_HOST` |
| Port | `REVERB_PORT` (443 for prod) |
| Scheme | `https` |
| Cluster | `mt1` (ignored by Reverb, required by SDK) |

**Auth endpoint:** `POST /broadcasting/auth` (include `Authorization: Bearer <token>` header)

**Channels to subscribe to:**

| Channel | Type | Events |
|---|---|---|
| `presence-room.{tenantId}.{roomId}` | Presence | `.chat.message`, `.chat.typing`, `.chat.reaction`, `.chat.read` |
| `private-user.{userId}` | Private | `.system.notification` |

**Event payloads** match those documented in the SSE/broadcast sections above.

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
| `GET .../banking/reviewed` | `transactions.view` |
| `POST .../banking/ingest/sms` | `transactions.import` |
| `POST .../banking/ingest/email` | `transactions.import` |
| `POST .../banking/ingest/statement` | `transactions.import` |
| `POST .../banking/transactions/{id}/approve` | `transactions.review` |
| `POST .../banking/transactions/{id}/correct` | `transactions.edit` |
| `POST .../chat` | `chat.view` |
| `GET .../groups` | `chat.room.view` |
| `POST .../groups` | `chat.room.create` |
| `GET .../groups/{room}` | `chat.room.view` + member |
| `GET .../groups/{room}/messages` | `chat.room.view` + member |
| `POST .../groups/{room}/messages` | `chat.message.send` + member |
| `DELETE .../groups/{room}/messages/{id}` | Own message or `chat.message.delete` |
| `POST .../groups/{room}/messages/{id}/reactions` | `chat.room.view` + member |
| `POST .../groups/{room}/read` | `chat.room.view` + member |
| `POST .../groups/{room}/typing` | `chat.room.view` + member |
| `POST .../groups/{room}/attachments` | `chat.room.view` + member |
| `GET .../groups/{room}/attachments/{id}` | `chat.room.view` + member |
| `POST .../groups/{room}/members` | `chat.room.manage` |
| `DELETE .../groups/{room}/members/{user}` | `chat.room.manage` |

---

## Mobile Developer Implementation Guide

This section describes exactly what the mobile app must implement to achieve full feature parity with the web group chat. The REST endpoints above handle all data operations. Real-time updates come via the Pusher native SDK connected to Laravel Reverb.

---

### 1. Authentication

Every HTTP request requires a Bearer token in the `Authorization` header:
```
Authorization: Bearer <token>
```

Obtain the token from `POST /api/mobile/login`. Store it in **Keychain** (iOS) or **Keystore** (Android). Tokens do not expire — revoke on logout via `POST /api/mobile/logout`.

---

### 2. Room List Screen

Call `GET /api/mobile/tenants/{tenant}/groups`.

- `unread_count` is calculated server-side — use it directly for the badge
- Sort: system rooms (`is_system: true`) pinned to the top, then by `updated_at` descending
- Show the `latest_message.body` as the preview line and `latest_message.created_at` as the timestamp
- Show an unread badge when `unread_count > 0`; cap display at `9+`

---

### 3. Room Screen — Initial Load

Call `GET /api/mobile/tenants/{tenant}/groups/{room}`.

Response contains:
- `room` — room metadata + full members list
- `messages` — last 50 messages, already in chronological order (oldest first)
- `can_load_more` — whether older messages exist

On open: scroll to the bottom, then immediately call mark-read (§8).

---

### 4. Real-Time — WebSocket Connection

The mobile app must use the **native Pusher SDK**, not a browser library.

- iOS: [pusher-swift](https://github.com/pusher/pusher-websocket-swift)
- Android: [pusher-java-client](https://github.com/pusher/pusher-websocket-java)

**Connection config:**

| Key | Value |
|---|---|
| `appKey` | `REVERB_APP_KEY` (get from backend team) |
| `host` | `REVERB_HOST` |
| `port` | `443` (prod) / `8080` (local) |
| `useTLS` | `true` (prod) / `false` (local) |
| `cluster` | `"mt1"` — required by the SDK but ignored by Reverb |

**WebSocket auth endpoint** — configure the Pusher SDK to use:
```
POST /api/mobile/broadcasting/auth
Authorization: Bearer <token>
```
This is a dedicated Sanctum-protected endpoint. Do **not** use the default `/broadcasting/auth` (that one is session/cookie only).

---

### 5. Channels to Subscribe To

Subscribe to these when the user opens a room:

**Presence channel** — `presence-room.{tenantId}.{roomId}`

All chat events flow through here. The presence protocol also gives you the online member list automatically.

| SDK event | Meaning |
|---|---|
| `pusher:subscription_succeeded` | Gives `members` map — use for online avatars |
| `pusher:member_added` | User came online — add green dot |
| `pusher:member_removed` | User went offline — remove green dot |

**Private channel** — `private-user.{userId}`

Subscribe once on login (not per-room). Receives system notifications (invoice paid, member joined, Tally sync, etc.) even when no room is open.

---

### 6. Events to Handle

#### `.chat.message`

Fires when a message is sent, edited, or deleted.

```json
{
  "id": "uuid",
  "body": "Hello!",
  "type": "text",
  "user_id": 1,
  "chat_room_id": "uuid",
  "created_at": "...",
  "sender": { "id": 1, "name": "CA1" },
  "reaction_summary": [],
  "reply_to": null,
  "attachments": []
}
```

Logic:
- If a message with this `id` already exists in your list → **replace it** (handles soft-deletes and edits)
- Otherwise → **append** it and scroll to bottom
- Then call mark-read (§8)

#### `.chat.typing`

```json
{ "user_id": 1, "user_name": "CA1", "typing": true }
```

- Ignore events where `user_id` equals your own user ID
- Show a typing indicator (e.g. "CA1 is typing…") when `typing: true`
- Remove it when `typing: false`
- Auto-clear after 5 seconds of no events as a safety net

#### `.chat.reaction`

```json
{ "message_id": "uuid", "emoji": "👍", "user_id": 1, "action": "added" }
```

Update `reaction_summary` on the matching message in your local list:
- `action: "added"` → increment count, add `user_id` to `users` array
- `action: "removed"` → decrement count, remove `user_id`; delete the entry if count reaches 0

#### `.chat.read`

```json
{ "user_id": 1, "last_read_message_id": "uuid" }
```

Maintain a `readMap` of `{ user_id → last_read_message_id }`. Use it to render read receipts:
- **✓ (grey)** — message sent, not yet read by all
- **✓✓ (violet/blue)** — read by everyone

#### `.system.notification` (private-user channel)

```json
{
  "title": "Invoice Paid",
  "body": "INV-0001 has been marked as paid.",
  "data": { "url": "/t/{tenantId}/groups" }
}
```

Show an in-app banner. If the app is in the foreground, display it inline. Tapping navigates to the groups screen.

---

### 7. Sending a Message

**If no attachments:**
```
POST /api/mobile/tenants/{tenant}/groups/{room}/messages
{ "body": "Hello!", "reply_to_message_id": null }
```

**If there are attachments — two steps:**

Step 1: Upload each file individually:
```
POST /api/mobile/tenants/{tenant}/groups/{room}/attachments
Content-Type: multipart/form-data
file: <binary>
```
Returns `{ "data": { "id": "uuid", ... } }`. Collect all IDs.

Step 2: Send the message with the IDs:
```
POST /api/mobile/tenants/{tenant}/groups/{room}/messages
{ "body": "See attached", "attachment_ids": ["uuid1", "uuid2"] }
```

**Deduplication:** Add the returned message to your local list immediately (optimistic). The `.chat.message` broadcast may arrive first — match by `id` and skip the duplicate.

---

### 8. Mark Read

Call in three situations:
1. When the room screen opens
2. When a new message arrives via `.chat.message`
3. When the user scrolls to the bottom (if there were unread messages above)

```
POST /api/mobile/tenants/{tenant}/groups/{room}/read
{ "message_id": "<id of the last visible message>" }
```

This broadcasts a `.chat.read` event to all room members so their read receipts update.

---

### 9. Typing Indicator

When the user starts typing:
```
POST /api/mobile/tenants/{tenant}/groups/{room}/typing
{ "typing": true }
```

When they stop (or after 3 seconds of no keystroke):
```
POST /api/mobile/tenants/{tenant}/groups/{room}/typing
{ "typing": false }
```

**Debounce rule:** send `true` at most once per 3 seconds while typing is active. Send `false` exactly once when they stop. Do not spam the endpoint on every keystroke.

---

### 10. Load Earlier Messages

When the user scrolls to the top and `can_load_more` is true:
```
GET /api/mobile/tenants/{tenant}/groups/{room}/messages?before_id=<oldest message id in your list>
```

Returns up to 50 older messages in chronological order. **Prepend** them to your list. Update `can_load_more` from the response. Maintain the scroll position so the user doesn't jump.

---

### 11. Emoji Reactions

Tap an emoji to toggle it on/off:
```
POST /api/mobile/tenants/{tenant}/groups/{room}/messages/{message}/reactions
{ "emoji": "👍" }
```

Calling with the same emoji twice removes the reaction. Response returns the updated `reaction_summary`. Also handle reactions from others via the `.chat.reaction` broadcast event.

Highlight the emoji button in violet when the current user's ID is in `reaction_summary[n].users`.

---

### 12. Delete a Message

```
DELETE /api/mobile/tenants/{tenant}/groups/{room}/messages/{message}
```

The server wipes the body and fires a `.chat.message` broadcast with `body: null` and `deleted_at` set. Handle the broadcast event to show a "This message was deleted" placeholder — do not remove the bubble entirely.

Users can only delete their own messages unless they have the `chat.message.delete` permission.

---

### 13. Room Management

**Create a room** (requires `chat.room.create`):
```
POST /api/mobile/tenants/{tenant}/groups
{ "name": "Finance Team", "description": "...", "member_ids": [2, 3] }
```

**Add a member** (requires `chat.room.manage`):
```
POST /api/mobile/tenants/{tenant}/groups/{room}/members
{ "user_id": 5, "role": "member" }
```

**Remove a member** (requires `chat.room.manage`):
```
DELETE /api/mobile/tenants/{tenant}/groups/{room}/members/{user}
```

---

### 14. Push Notifications — Known Gap

**The backend currently only supports browser Web Push (VAPID).** Native mobile push (FCM for Android, APNs for iOS) is not yet implemented.

Until FCM/APNs support is added:
- System notifications arrive **only while the WebSocket is connected** (app in foreground)
- There are **no background push notifications** on mobile

When FCM/APNs is added, the mobile developer will need to:
1. Call a new endpoint (TBD) to register the device push token after login
2. Call it again whenever the OS assigns a new token (e.g. after app reinstall)
3. Deregister on logout

---

### Feature Parity Summary

| Feature | Mobile status |
|---|---|
| Room list + unread counts | Full parity |
| Real-time messages | Full parity (Pusher native SDK) |
| Typing indicators | Full parity |
| Read receipts (✓ / ✓✓) | Full parity |
| Emoji reactions | Full parity |
| File attachments (upload + download) | Full parity |
| Reply-to messages | Full parity |
| Load earlier messages (cursor) | Full parity |
| Online member presence | Full parity |
| Delete messages | Full parity |
| System notifications (foreground) | Full parity |
| System notifications (background push) | **Not yet — FCM/APNs pending** |

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
