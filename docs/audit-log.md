# Audit Log

The audit log is an **append-only** record of every action that mutates data in the system. It is tenant-scoped and visible only to users with the `audit.view` permission (Owner and TenantAdmin roles).

---

## Access

**Route:** `GET /t/{tenant}/settings/audit`  
**Controller:** `AuditLogController@index`  
**Permission:** `audit.view`  
**Roles with access:** `owner`, `TenantAdmin`

The UI page is at `Settings/AuditLog.vue`. It offers four filters:
- Free-text search across event type and metadata
- Event type dropdown (populated from distinct events in the tenant)
- Actor user dropdown (populated from team members)
- Date range (from / to)

Results are paginated at 50 per page.

---

## Append-only Guarantee

`AuditEvent::boot()` hooks `updating` and `deleting` to return `false`. No code path may update or delete an audit event — only creation is allowed.

---

## Actor Types

| `actor_type` | When used | Who |
|---|---|---|
| `human` | Web and API calls made by a logged-in user | The authenticated `auth()->user()` |
| `integration` | Machine-to-machine Tally connector calls | No user; identified by `TallyConnection` token |
| `system` | AI agent tool calls on behalf of a user | `auth()->user()` is the actor (still logged in), but the action was AI-initiated |

The UI shows an **AI** badge next to the actor name for `system` events and a plain **Tally Connector** label for `integration` events.

---

## Event Taxonomy

### Auth
| Event | Trigger |
|---|---|
| `auth.login` | User successfully logs in |
| `auth.logout` | User logs out |
| `auth.user.registered` | New user account created |
| `auth.password.changed` | User changes their own password |
| `auth.password.reset` | Password reset via email link |
| `auth.password_reset.requested` | Reset link email sent |
| `auth.email.verified` | User verifies their email address |

### Profile
| Event | Trigger |
|---|---|
| `profile.updated` | User updates name/email/avatar |
| `profile.deleted` | User deletes their own account |

### Team & Roles
| Event | Trigger |
|---|---|
| `role.created` | New custom role created |
| `role.updated` | Role permissions changed |
| `role.deleted` | Custom role deleted |

### Clients / Vendors / Products
| Event | Trigger |
|---|---|
| `client.created` | New client saved |
| `client.updated` | Client details edited |
| `client.deleted` | Client deleted |
| `vendor.created` | New vendor saved |
| `vendor.updated` | Vendor details edited |
| `vendor.deleted` | Vendor deleted |
| `product.created` | New product saved |
| `product.updated` | Product details edited |
| `product.deleted` | Product deleted |

### Invoices
| Event | Trigger |
|---|---|
| `invoice.created` | Invoice created (web or AI) |
| `invoice.updated` | Invoice edited (web or AI) |
| `invoice.deleted` | Invoice deleted |

### Banking & Narration
| Event | Trigger |
|---|---|
| `banking.sms.ingested` | SMS transaction ingested |
| `banking.email.ingested` | Email transaction ingested |
| `banking.statement.uploaded` | Statement file processed |
| `narration.approved` | Transaction approved in review UI |
| `narration.corrected` | Transaction re-categorised in review UI |
| `narration.reviewed` | Transaction narrated (web review controller) |
| `narration.saved` | Transaction narrated via AI agent |
| `narration_head.created/updated/deleted` | Narration head managed |
| `narration_sub_head.created/updated/deleted` | Narration sub-head managed |

### Chat
| Event | Trigger |
|---|---|
| `chat.message` | Successful chat exchange (web or API) |
| `chat.error` | Chat agent error |

### Tally Integration
| Event | Trigger |
|---|---|
| `tally.connection.saved` | Tally connection configured |
| `tally.connection.token_regenerated` | API token regenerated |
| `tally.connection.deleted` | Tally connection removed |
| `tally.sync.triggered` | Manual sync requested |
| `tally.inbound.{entity}.synced` | Tally pushed data inbound (integration) |
| `tally.outbound.{entity}` | Tally connector fetched outbound data (integration) |
| `tally.outbound.confirmed` | Tally confirmed sync (integration) |
| `tally.{entity}.created/updated/deleted` | Master record managed via CRUD UI |

---

## Metadata

Every event carries a `metadata` JSON object with context-specific fields.

Common fields across AI-originated events:
```json
{ "via": "ai_agent" }
```

Example — `invoice.created`:
```json
{
  "id": 42,
  "invoice_number": "INV-00042",
  "client_id": 7,
  "total": 11800.00,
  "via": "ai_agent"
}
```

Example — `tally.inbound.ledgers.synced` (integration):
```json
{
  "entity": "ledgers",
  "created": 5,
  "updated": 12,
  "deleted": 0,
  "skipped": 3,
  "failed": 0
}
```

---

## Database

Table: `audit_events`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `occurred_at` | timestamp | When the event happened |
| `tenant_id` | bigint | Tenant scope |
| `actor_user_id` | bigint\|null | Null for integration/system without resolved user |
| `actor_type` | string | `human`, `integration`, `system` |
| `impersonator_user_id` | bigint\|null | Set when an admin is impersonating |
| `event_type` | string | Dot-separated event name |
| `ip` | string\|null | Request IP |
| `user_agent` | string\|null | Request user agent |
| `metadata` | json | Event-specific context |
