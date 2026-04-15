# Accobot — Project Overview

## What It Is

Accobot is a multi-tenant accounting platform for Indian CA firms and businesses. A CA firm signs up, creates their own workspace, and gets added as an external member to each of their client businesses. All data is strictly tenant-scoped — a CA only sees the data of the tenant they are currently viewing.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Inertia.js + Vue 3 + Tailwind CSS 3 |
| Build | Vite 8 |
| Database | PostgreSQL |
| Auth (web) | Laravel Breeze + Spatie Laravel Permission v7 |
| Auth (mobile/API) | Laravel Sanctum (Personal Access Tokens) |
| AI | `laravel/ai` v0.5 — OpenAI provider (`gpt-4o`, `gpt-4o-mini`, `gpt-4.1-mini`, `text-embedding-3-small`) |
| PDF | barryvdh/laravel-dompdf |
| Spreadsheets | phpoffice/phpspreadsheet |
| Testing | PestPHP 4.4 |
| Email | Google Gmail API (GmailTransport) |

---

## Running the Project

```bash
# First-time setup
composer run setup

# Development (starts all 4 processes concurrently)
composer run dev
#   → php artisan serve
#   → php artisan queue:listen
#   → php artisan pail (log viewer)
#   → npm run dev (Vite HMR)

# Tests
composer run test

# Seed the database
php artisan migrate:fresh --seed
```

---

## Architecture Overview

```
Browser / Mobile App
        │
        ├─── Web (Inertia + Vue)  →  routes/web.php   →  Controllers  →  Inertia::render()
        │
        └─── Mobile API (JSON)    →  routes/api.php   →  Api\Controllers  →  JsonResponse / SSE
```

### Multi-Tenancy

Every tenant has a UUID primary key. All tenant-scoped models carry `tenant_id` and use the `BelongsToTenant` trait, which automatically adds a global scope that reads the current tenant from the URL (`request()->route('tenant')`).

Web routes use the prefix `/t/{tenant}` — the `{tenant}` segment is resolved via route model binding and verified by the `member` middleware before any controller runs.

**Models with `BelongsToTenant` (auto-scoped):**
`Client`, `Vendor`, `Product`, `Invoice`, `NarrationHead`, `BankTransaction`, `NarrationRule`

**Models without global scope (manually scoped or app-wide):**
`User`, `Tenant`, `Invitation` (token-based), `InvoiceItem` (scoped via parent invoice), `NarrationSubHead` (scoped via parent head), `TenantUserRole`, `TenantRolePermission`, `AuditEvent`

### Tenant Types

| Type | Description |
|---|---|
| `business` | A regular company using the platform |
| `ca_firm` | A chartered accountant firm — personal to its owner (`is_personal = true`) |

---

## Modules

### 1. Auth & Users

Standard Breeze authentication (login, register, password reset, email verification). Users have a `type` (`human` or `integration`) and `status` (`active` or `platform_suspended`).

On registration, a user's personal tenant is created via `User::createPersonalTenant()`.

**Key files:**
- `app/Http/Controllers/AuthController.php`
- `app/Models/User.php`

---

### 2. Multi-Tenancy & Team Management

Users belong to tenants via the `tenant_user` pivot table. Each user-tenant pair has a role assigned via `tenant_user_roles`.

- **Invitations** — send by email, accept via token link or in-app bell
- **Roles** — global presets (owner, TenantAdmin, Manager, Staff, Viewer, ExternalAccountant, OwnerPartner, CAManager, Auditor, CAStaff, IntegrationUser) with per-tenant permission overrides stored in `tenant_role_permissions`
- **Impersonation** — platform admin can impersonate any user; destructive actions are blocked during impersonation

**Key files:**
- `app/Http/Controllers/TeamMemberController.php`
- `app/Http/Controllers/InvitationController.php`
- `app/Http/Controllers/RoleController.php`
- `app/Http/Controllers/ImpersonationController.php`
- `app/Http/Middleware/EnsureBelongsToTenant.php`
- `app/Http/Middleware/TenantPermission.php`

---

### 3. Master Data

Standard CRUD modules, all permission-gated and tenant-scoped.

| Module | Model | Route prefix | Permission group |
|---|---|---|---|
| Clients | `Client` | `/t/{tenant}/clients` | `clients.*` |
| Vendors | `Vendor` | `/t/{tenant}/vendors` | `vendors.*` |
| Inventory (Products) | `Product` | `/t/{tenant}/products` | `products.*` |
| Narration Heads | `NarrationHead` + `NarrationSubHead` | `/t/{tenant}/narration-heads` | `narration_heads.*` |

Narration heads are the chart of accounts used to categorise bank transactions. Each head has a `type` (credit / debit / both), a `sort_order`, and optional sub-heads with a `ledger_code` and `ledger_name`.

**Key files:**
- `app/Http/Controllers/ClientController.php`
- `app/Http/Controllers/VendorController.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/NarrationHeadController.php`

---

### 4. Invoices

Full invoice lifecycle with line items, payment tracking, and PDF download.

- Invoice numbers are per-tenant sequential (`INV-0001`, `INV-0002`, …), generated with `lockForUpdate()` to avoid race conditions
- Line items stored in `invoice_items` (not tenant-scoped — always accessed via invoice)
- PDF rendered via DomPDF from `resources/views/invoices/pdf.blade.php`

**Invoice statuses:** `draft` → `sent` → `paid` / `overdue` / `cancelled`

**Key files:**
- `app/Http/Controllers/InvoiceController.php`
- `app/Models/Invoice.php` — `generateNumber(string $tenantId)`
- `app/Models/InvoiceItem.php`
- `resources/views/invoices/pdf.blade.php`

---

### 5. Banking & Narration

The core feature for CA workflows. Bank transactions are ingested from three sources, auto-categorised, and queued for human review.

#### Ingest Pipeline

```
SMS / Email / Statement file
        │
        ▼
  AI Parser Agent          ← SmsParserAgent / EmailParserAgent / StatementDocumentParserAgent
        │
        ▼
  ParsedTransactionDTO     ← structured: amount, type, date, party_name, bank_reference
        │
        ▼
  Dedup Check              ← hash(date + amount + type + bank_reference) per tenant
        │
  ┌─────┴──────┐
  │            │
duplicate   new / source upgrade
  │            │
  │       Tier 1: NarrationRuleEngine   ← exact / fuzzy match on learned rules
  │            │
  │       Tier 2: NarrationAiService    ← AI fallback using narration heads as context
  │            │
  └─────────►  BankTransaction (review_status = "pending")
```

**Source priority** (higher = more authoritative): `sms (1)` < `email (2)` < `statement (3)`
If a duplicate arrives from a higher-priority source, the existing record is upgraded (not duplicated).

#### Review Actions

| Action | Permission | What it does |
|---|---|---|
| Approve | `transactions.review` | Marks as reviewed, no changes |
| Correct | `transactions.edit` | Re-categorise: sets head, sub-head, party, note |
| Save as rule | `transactions.edit` | Persists the correction as a `NarrationRule` for future auto-matching |
| Reconcile | `transactions.edit` | Links the transaction to an invoice |

#### Invoice Matching

When a transaction is not yet reconciled, `InvoiceMatchingService` scores open invoices by amount, date proximity, and party name similarity, returning the top 3 candidates.

**Key files:**
- `app/Services/Banking/NarrationPipelineService.php` — orchestrates the pipeline
- `app/Services/Banking/NarrationRuleEngine.php` — Tier 1 rule matching
- `app/Services/Banking/NarrationAiService.php` — Tier 2 AI suggestion
- `app/Services/Banking/InvoiceMatchingService.php` — invoice reconciliation candidates
- `app/Actions/Banking/` — ReviewNarrationAction, IngestSmsTransactionAction, IngestEmailTransactionAction, ProcessStatementAction
- `app/Agents/Narration/` — SmsParserAgent (`gpt-4o-mini`), EmailParserAgent (`gpt-4o-mini`), StatementDocumentParserAgent (`gpt-4.1-mini`), NarrationSuggestionAgent (`gpt-4o-mini`)
- `app/Services/EmbeddingService.php` — vector embeddings via `text-embedding-3-small` (1536 dims, stored in pgvector)
- `app/Http/Controllers/BankTransactionController.php`
- `app/Http/Controllers/NarrationReviewController.php`

---

### 6. AI Accounting Assistant (Chat)

A conversational AI agent powered by `laravel/ai` (OpenAI). Available on web (SSE stream) and mobile API.

**Agent:** `AccountingAgent` — model `gpt-4o`, max 20 steps, 120s timeout

**8 tools available to the agent:**

| Tool | What it does |
|---|---|
| `ManageClientTool` | Search, create, update clients |
| `ManageProductTool` | Search, create, update products |
| `ListInvoicesTool` | List invoices with filters (status, client, date) |
| `CreateInvoiceTool` | Create invoice with line items |
| `UpdateInvoiceTool` | Edit due date, status, notes, add/remove line items |
| `GetInvoiceTool` | Retrieve a single invoice by number |
| `ManageNarrationHeadTool` | Create/edit narration heads and sub-heads |
| `NarrateTransactionTool` | List, view, and categorise bank transactions |

**Agent behaviour:**
- Always searches before creating (client, product)
- Confirms invoice draft with user before calling `CreateInvoiceTool`
- Defaults to INR; auto-determines Indian GST rate from product name
- Never exposes internal IDs to the user
- PDF download links are passed through verbatim from tool results

**Key files:**
- `app/Agents/AccountingAgent.php`
- `app/Tools/` — 8 tool files
- `app/Http/Controllers/ChatController.php` — web SSE
- `app/Http/Controllers/Api/TenantChatController.php` — mobile SSE

---

## Permissions System

Two-layer system:

1. **Global role defaults** — defined in `RolesAndPermissionsSeeder`, stored in Spatie's `roles` + `permissions` tables
2. **Per-tenant overrides** — stored in `tenant_role_permissions`; when overrides exist for a role in a tenant, they completely replace the global defaults for that tenant

**Middleware:** `tenant.permission:transactions.edit` — checked on every route.

**In controllers:** `abort_unless($user->hasPermissionInTenant('X', $tenant), 403)`

**Frontend:** Permissions are shared via `HandleInertiaRequests` as `auth.permissions` (array of permission names). The `hasPermission()` util in `utils/permissions.js` checks this array. The dashboard "Your Access" card is driven by `config/permission_groups.php` — add new groups there, no Vue changes needed.

See [`roles-and-permissions.md`](roles-and-permissions.md) for the full permission matrix.

---

## Admin Panel

Platform admins (`role:admin`) access `/dashboard` (no tenant prefix). They can:

- View platform-wide stats (tenants, businesses, CA firms, suspended, pending, users, roles)
- Browse all tenants with their status and member count
- Impersonate any user via the Recent Users list

Admin visiting a tenant (`/t/{tenant}/...`) sees the full tenant nav identical to a tenant user.

**Key files:**
- `app/Http/Controllers/DashboardController.php`
- `resources/js/Pages/Dashboard/Admin.vue`

---

## Routes Summary

### Web (`routes/web.php`)

| Group | Prefix | Middleware |
|---|---|---|
| Public | `/` | — |
| Guest auth | `/login`, `/register` | `guest` |
| Admin | `/dashboard`, `/admin/...` | `auth + role:admin` |
| Tenant | `/t/{tenant}/...` | `auth + verified + member` |
| Invitations (token) | `/invite/{token}/...` | `throttle:20,1` |
| Profile | `/profile` | `auth` |

### API (`routes/api.php`)

| Group | Prefix | Middleware |
|---|---|---|
| Mobile auth (public) | `/api/mobile` | `throttle:10,1` |
| Mobile auth (authenticated) | `/api/mobile` | `auth:sanctum` |
| Tenant-scoped | `/api/mobile/tenants/{tenant}` | `auth:sanctum + member` |

See [`api-mobile.md`](api-mobile.md) for the full mobile API reference.

---

## Key Design Decisions

| Decision | Reason |
|---|---|
| UUID tenant IDs | Avoids sequential ID enumeration attacks |
| `BelongsToTenant` global scope | Prevents accidental cross-tenant data leaks on every query |
| Controllers repeat `where('tenant_id', $tenant->id)` even with global scope | Extra defence-in-depth; belt and braces |
| NarrationSubHead has no global scope | Scoped through parent head — always verified via `$sub->narrationHead->tenant_id` |
| Invoice numbers use `lockForUpdate()` | Prevents duplicate invoice numbers under concurrent requests |
| Source priority on dedup | A statement (official) silently upgrades an SMS (noisy) for the same transaction — no duplicate noise |
| Per-tenant permission overrides | A CA firm needs different defaults than a business without forking the role definition |
| SSE for chat | Streaming response — user sees the agent reply token-by-token without waiting |
| `is_personal` on Tenant | CA's own firm is always sorted to the top of the tenant switcher and labelled "Mine" |

---

## File Structure (Key Paths)

```
app/
├── Actions/Banking/          ← ingest + review actions (single-responsibility)
├── Agents/                   ← AccountingAgent + 4 narration parser agents
├── Http/
│   ├── Controllers/          ← web controllers
│   ├── Controllers/Api/      ← mobile API controllers
│   └── Middleware/           ← EnsureBelongsToTenant, TenantPermission, BlockImpersonationDestructive
├── Models/
│   └── Concerns/BelongsToTenant.php
├── Services/Banking/         ← NarrationPipelineService, RuleEngine, AiService, InvoiceMatchingService
└── Tools/                    ← 8 AI agent tools

database/
├── migrations/               ← all schema, chronological
└── seeders/
    ├── RolesAndPermissionsSeeder.php   ← all roles + permissions
    └── DummyDataSeeder.php             ← test users, tenants, assignments

resources/js/
├── Components/               ← reusable Vue components
├── Layouts/AuthenticatedLayout.vue
└── Pages/                    ← one file per page/route

config/
└── permission_groups.php     ← dashboard "Your Access" card groups

docs/
├── project-overview.md       ← this file
├── roles-and-permissions.md  ← full role/permission matrix + seeded users
└── api-mobile.md             ← mobile API reference
```

---

## Data Flow: Creating an Invoice via Chat

```
User: "Create invoice for ABC Corp for 5000 consulting"
        │
        ▼
AccountingAgent receives message
        │
        ├─ ManageClientTool(action=search, query="ABC Corp")
        │        └─ found: { id, name, email }
        │
        ├─ ManageProductTool(action=search, query="consulting")
        │        └─ not found → ManageProductTool(action=create, name="consulting", price=5000, tax_rate=18%)
        │
        ├─ Agent summarises draft to user → user confirms
        │
        ├─ CreateInvoiceTool(client_id, line_items=[{product_id, qty:1, price:5000}])
        │        └─ Invoice::generateNumber(tenantId) → "INV-0001"
        │        └─ Invoice + InvoiceItem created
        │        └─ returns invoice + PDF download URL
        │
        └─ Agent reply with invoice summary + [Click here to download PDF](url)
```

## Data Flow: SMS Narration

```
User pastes SMS → POST /banking/transactions/sms
        │
        ▼
SmsIngestController → IngestSmsTransactionAction
        │
        ▼
NarrationPipelineService.processFromSms()
        │
        ├─ SmsParserAgent (AI) → ParsedTransactionDTO
        │
        ├─ Dedup hash check → not duplicate
        │
        ├─ Tier 1: NarrationRuleEngine → no matching rule
        │
        ├─ Tier 2: NarrationAiService → suggests head + sub-head + note
        │
        └─ BankTransaction saved (review_status = "pending")
                │
                ▼
        User reviews in Narration page → Approve / Correct
                │
                ▼ (if Correct + save_as_rule)
        NarrationRule saved → used by Tier 1 next time
```
