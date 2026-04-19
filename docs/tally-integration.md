# Tally Integration — Comprehensive Guide

> For complete request/response examples for every endpoint, see **[docs/tally-api-reference.md](./tally-api-reference.md)**.

## Table of Contents

1. [How It Works — Big Picture](#1-how-it-works--big-picture)
2. [The Three Data Flows](#2-the-three-data-flows)
3. [Authentication & Tenant Resolution](#3-authentication--tenant-resolution)
4. [Setting Up a Connection](#4-setting-up-a-connection)
5. [Database Design — 11 Tables + 4 FK Columns](#5-database-design--11-tables--4-fk-columns)
6. [The Two-Table Design Pattern](#6-the-two-table-design-pattern)
7. [Inbound Sync — How Tally Data Enters Accobot](#7-inbound-sync--how-tally-data-enters-accobot)
8. [Auto-Mapping — Tally → Accobot Operational Models](#8-auto-mapping--tally--accobot-operational-models)
9. [Ledger Categorization Rules](#9-ledger-categorization-rules)
10. [Outbound GET — Accobot Data Served to Tally](#10-outbound-get--accobot-data-served-to-tally)
11. [Confirmation POST — Tally Writes Back Its IDs](#11-confirmation-post--tally-writes-back-its-ids)
12. [Report Snapshots](#12-report-snapshots)
13. [Sync Logs & Observability](#13-sync-logs--observability)
14. [All 35 API Endpoints](#14-all-35-api-endpoints)
15. [Web UI Routes & Pages](#15-web-ui-routes--pages)
16. [Service & Controller Map](#16-service--controller-map)
17. [Full File Map](#17-full-file-map)
18. [Known Limitations & Pending Design Decisions](#18-known-limitations--pending-design-decisions)

---

## 1. How It Works — Big Picture

**Tally is the source of truth. Accobot makes zero outbound HTTP calls to Tally.**

Accobot replicates the `cloud-tally.in` connector API exactly — same paths, same payload shapes — so the existing Tally connector software talks to Accobot instead of the cloud. The connector is the only active party; Accobot is passive.

```
┌─────────────────────────────────────────────────────────┐
│                      Tally ERP                          │
│  (runs on-premise or hosted — your client's machine)    │
└───────────────────────┬─────────────────────────────────┘
                        │  Tally Connector software
                        │  (configured with Accobot's base_url + token)
                        │
          ┌─────────────┼──────────────────────────────┐
          │             │                              │
          ▼             ▼                              ▼
    Inbound POST    Outbound GET              Confirmation POST
    (Tally → Accobot)  (Tally reads Accobot)   (Tally sends back IDs)
          │             │                              │
          └─────────────┴──────────────────────────────┘
                        │
              ┌─────────▼──────────┐
              │    Accobot API     │
              │  (Laravel backend) │
              └─────────┬──────────┘
                        │
              ┌─────────▼──────────┐
              │  PostgreSQL DB     │
              │  tally_* tables    │
              │  + clients/vendors │
              │  + products/invoices│
              └────────────────────┘
```

Each tenant has exactly **one** `tally_connections` row. That row holds the bearer token the connector uses for every request, and the `company_id` (Tally's UUID for the company) used to verify the right company is talking to the right tenant.

---

## 2. The Three Data Flows

### Flow 1 — Inbound (Tally → Accobot)

The connector pushes Tally master data and vouchers to Accobot via POST requests. Accobot upserts the data into its `tally_*` mirror tables and runs auto-mapping.

**When it happens:** Continuously as Tally data changes. The connector runs on a schedule (usually every few minutes) and pushes any records whose `AlterID` has changed since the last push.

**Paths:** `/api/tally/inbound/masters/*` and `/api/tally/inbound/vouchers/*`

### Flow 2 — Outbound GET (Tally reads Accobot)

Tally GETs records that were created in Accobot (e.g. a new client created via the Accobot UI that should appear in Tally as a ledger). Accobot formats and returns them in Tally's exact payload structure.

**When it happens:** The connector polls these endpoints periodically.

**Paths:** `/api/MastersAPI/*` and `/api/VoucherAPI/*` (exact `cloud-tally.in` Swagger paths)

### Flow 3 — Confirmation POST (Tally confirms back)

After Tally creates an Accobot-originated record (from Flow 2), it POSTs back the `TallyId` it assigned so Accobot can store it. This closes the loop — the Accobot record now has its Tally ID for future deduplication.

**Paths:** `/api/MastersAPI/update-*` and `/api/VoucherAPI/update-*`

---

## 3. Authentication & Tenant Resolution

All 35 API endpoints (inbound, outbound, confirmation) use the same token-based auth. There is **no Sanctum** involved — the Tally connector is not a user.

### How it works

1. On `TallyConnection::creating()`, a 48-character random token is auto-generated and stored in `inbound_token` (which is in `$hidden` so it never leaks in JSON responses).
2. The tenant copies this token into the Tally connector's configuration.
3. Every connector request sends `Authorization: Bearer <token>`.
4. `TallyBaseController::resolveConnection()` looks up the token **across all tenants** (`withoutGlobalScope('tenant')`) and returns the matching `TallyConnection`.
5. If the token doesn't exist or `is_active = false`, it returns HTTP 401 immediately.
6. On every successful request, `inbound_token_last_used_at` is stamped so you can see when Tally last talked to Accobot.

For confirmation endpoints (Flow 3), `resolveConnectionByCompanyId()` additionally verifies that the `{companyId}` in the URL matches the `company_id` stored on the connection.

```php
// TallyBaseController — runs on every API request
$conn = TallyConnection::withoutGlobalScope('tenant')
    ->where('inbound_token', $token)
    ->where('is_active', true)
    ->first();
```

### Why withoutGlobalScope?

The `BelongsToTenant` trait scopes queries by the `{tenant}` URL parameter from the web route. API routes (like `/api/tally/inbound/...`) don't have that parameter, so without `withoutGlobalScope`, queries would return nothing. All Tally API controllers explicitly bypass the scope and scope by `tenant_id` from the resolved connection instead.

---

## 4. Setting Up a Connection

### Step 1 — Tenant configures in Accobot

Navigate to **Settings → Tally**. Enter the Tally `Company ID` (the UUID from Tally's company settings). Save. Accobot creates a `tally_connections` row and auto-generates the token.

### Step 2 — Copy credentials into Tally connector

The Connection page shows three values the tenant copies into the Tally connector software:

| Field | Value | Where it comes from |
|-------|-------|---------------------|
| Base URL | `https://your-accobot-domain.com` | `url('/')` |
| Token | 48-char random string | Auto-generated on first save |
| Company ID | Tally company UUID | Entered by tenant in step 1 |

### Step 3 — Connector starts pushing

Once configured, the Tally connector automatically begins pushing data. No further action needed.

### Token regeneration

If the token is compromised, the tenant can regenerate it from the Connection page. The old token stops working immediately. The connector must be reconfigured with the new token.

---

## 5. Database Design — 11 Tables + 4 FK Columns

### Overview

```
tally_connections          ← one per tenant, holds auth token
tally_ledger_groups        ← Tally's account hierarchy
tally_ledgers              ← every account (customers, vendors, bank, tax, etc.)
tally_stock_groups         ← product group hierarchy
tally_stock_categories     ← product category hierarchy
tally_stock_items          ← every product/stock item
tally_vouchers             ← every transaction (sales, purchase, payment, etc.)
tally_voucher_inventory_entries  ← line items inside vouchers
tally_voucher_ledger_entries     ← ledger movements inside vouchers
tally_reports              ← financial report snapshots
tally_sync_logs            ← audit log of every sync operation
```

Plus 4 FK columns added to existing tables:
```
clients.tally_ledger_id    → tally_ledgers.id
vendors.tally_ledger_id    → tally_ledgers.id
products.tally_stock_item_id → tally_stock_items.id
invoices.tally_voucher_id  → tally_vouchers.id
```

### Key design decisions

**`unique(tenant_id, tally_id)`** — Every master/voucher table has this constraint. `tally_id` is the integer ID Tally assigns. This is the upsert key for all inbound syncs.

**`alter_id`** — Tally increments `AlterID` every time a record changes. On inbound sync, if the incoming `AlterID` matches what's stored, the record is skipped entirely (no DB write). This is the primary deduplication mechanism.

**`is_active`** — Soft deletes. When Tally sends `Action: Delete`, `is_active` is set to `false`. Records are never hard-deleted.

**`last_synced_at`** — Per-record timestamp of the last successful sync. Separate from `updated_at`.

### Full column list

#### tally_connections
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| tenant_id | uuid | unique FK → tenants |
| company_id | string | Tally company UUID |
| is_active | bool | default true; set false to block connector |
| inbound_token | string(48) | unique; in `$hidden`; auto-generated |
| inbound_token_last_used_at | timestamp | nullable; stamped on every request |
| last_synced_at | timestamp | nullable; stamped after each sync |

#### tally_ledger_groups
| Column | Type | Notes |
|--------|------|-------|
| tally_id | integer | Tally's assigned ID |
| alter_id | integer | Skip-check key |
| action | string | Create / Delete |
| name | string | Group name |
| under_id / under_name | integer / string | Parent group |
| nature_of_group | string | Assets / Liabilities / Income / Expenses |
| is_revenue | bool nullable | |
| affects_gross | bool nullable | |
| is_addable | bool nullable | |
| is_active | bool | |

#### tally_ledgers
All account masters — customers, vendors, bank accounts, tax ledgers, expense heads, etc.

| Section | Columns |
|---------|---------|
| Identity | tally_id, alter_id, action, ledger_name, group_name, parent_group |
| Derived | ledger_category (customer/vendor/bank/tax/income/expense/asset/liability/other) |
| Flags | is_bill_wise_on, inventory_affected, is_cost_centre_applicable |
| GST | gstin_number, pan_number, tan_number, gst_type, is_rcm_applicable |
| Contact | mailing_name, mobile_number, contact_person, contact_person_email, contact_person_email_cc, contact_person_fax, contact_person_website, contact_person_mobile |
| Address | addresses (jsonb), state_name, country_name, pin_code |
| Credit | credit_period, credit_limit |
| Opening Balance | opening_balance, opening_balance_type (Dr/Cr) |
| Bank | bank_details (jsonb) |
| Other | aliases (jsonb), description, notes |
| Mapping | mapped_client_id FK → clients, mapped_vendor_id FK → vendors |

#### tally_stock_items
All product/inventory masters.

| Section | Columns |
|---------|---------|
| Identity | tally_id, alter_id, action, name, description, remarks, aliases (jsonb) |
| Classification | stock_group_id, stock_group_name, stock_category_id, category_name |
| Units | unit_id, unit_name, alternate_unit, conversion, denominator |
| GST | is_gst_applicable, taxability, calculation_type, igst_rate, sgst_rate, cgst_rate, cess_rate, hsn_code |
| Pricing | mrp_rate, standard_cost, standard_price |
| Stock | opening_balance, opening_rate, opening_value, closing_balance, closing_rate, closing_value |
| Inventory | costing_method, is_batch_applicable, is_expiry_date_applicable, reorder_level, reorder_quantity, maximum_quantity |
| Batch | batch_allocations (jsonb) |
| Mapping | mapped_product_id FK → products |

#### tally_stock_groups
| Column | Notes |
|--------|-------|
| name | Group name |
| parent_id / parent_name | Parent group |
| nature_of_group | Assets / etc. |
| should_add_quantities | bool |

#### tally_stock_categories
| Column | Notes |
|--------|-------|
| name | Category name |
| parent_name | Parent category |

#### tally_vouchers
All financial transactions — every voucher type.

| Section | Columns |
|---------|---------|
| Identity | tally_id (= MasterID from Tally), alter_id, action, voucher_type, voucher_number, voucher_date, reference, reference_date |
| Party | party_name, party_tally_ledger_id FK → tally_ledgers, voucher_total |
| Flags | is_invoice, is_deleted |
| Dispatch | place_of_supply, delivery_note_no, delivery_note_date, dispatch_doc_no, dispatch_through, destination, carrier_name, lr_no, lr_date, motor_vehicle_no |
| Order | order_no, order_date, terms_of_payment, terms_of_delivery, other_references |
| Buyer | buyer_name, buyer_alias, buyer_gstin, buyer_pin_code, buyer_state, buyer_country, buyer_gst_registration_type, buyer_email, buyer_mobile, buyer_address (jsonb) |
| Consignee | consignee_name, consignee_gstin, consignee_tally_group, consignee_pin_code, consignee_state, consignee_country, consignee_gst_registration_type |
| e-Invoice | irn, acknowledgement_no, acknowledgement_date, qr_code |
| Other | narration, cost_centre |
| Mapping | mapped_invoice_id FK → invoices |

#### tally_voucher_inventory_entries
Line items (stock/product lines) within a voucher. Deleted and re-inserted on every voucher upsert.

| Column | Notes |
|--------|-------|
| tally_voucher_id | FK → tally_vouchers (cascade delete) |
| tally_stock_item_id | FK → tally_stock_items (nullable) |
| stock_item_name, item_code, group_name, hsn_code, unit | |
| igst_rate, cess_rate | |
| is_deemed_positive | Positive = income direction |
| actual_qty, billed_qty, rate, discount_percent | |
| amount, tax_amount, mrp | |
| sales_ledger, godown_name, batch_name | |
| batch_allocations, accounting_allocations | jsonb |

#### tally_voucher_ledger_entries
Ledger movements (debit/credit) within a voucher. Also deleted and re-inserted on each upsert.

| Column | Notes |
|--------|-------|
| tally_voucher_id | FK → tally_vouchers (cascade delete) |
| tally_ledger_id | FK → tally_ledgers (nullable) |
| ledger_name, ledger_group | |
| ledger_amount | |
| is_deemed_positive, is_party_ledger | |
| igst_rate, hsn_code, cess_rate | stored as string (Tally format) |
| bills_allocation | jsonb |

#### tally_reports
Financial report snapshots — insert-only, never updated.

| Column | Notes |
|--------|-------|
| report_type | balance_sheet / profit_loss / cash_flow / ratio_analysis |
| period_from | date nullable (null for balance sheet which is point-in-time) |
| period_to | date |
| data | jsonb — full report JSON as Tally produced it |
| generated_at | when Tally generated the report |
| synced_at | when Accobot received it |

Index on `(tenant_id, report_type, period_to)` for fast retrieval of the latest report of each type.

#### tally_sync_logs
Audit trail of every sync operation.

| Column | Notes |
|--------|-------|
| entity | ledger_groups / ledgers / stock_items / vouchers_sales / reports_balance_sheet / etc. |
| direction | inbound / outbound |
| status | running → success or failed |
| triggered_manually | bool |
| records_created / updated / skipped / failed | integer counters |
| error_message | text; populated on failure |
| started_at / completed_at | timestamps |

---

## 6. The Two-Table Design Pattern

For each of the four core Accobot entities, Tally data is stored in **two** places:

```
Tally mirror table              Accobot operational table
(full Tally data)               (slim — what the app actually needs)
────────────────────────────    ──────────────────────────────────
tally_ledgers                   clients / vendors
tally_stock_items               products
tally_vouchers (Sales)          invoices
```

### Why two tables?

The Tally mirror tables store everything Tally knows — 40-50 columns per record covering GST, bank details, credit terms, dispatch info, e-invoice data, etc. The Accobot operational tables store only what the rest of the app uses day-to-day.

This separation means:
- **The chat assistant**, invoice PDF, banking reconciliation, and UI all query the slim Accobot tables unchanged — no Tally knowledge required.
- Clients and products created manually in Accobot (before Tally is connected, or never synced) work fine — they just have `tally_ledger_id = null`.
- Tally-synced records and manually-created records coexist in the same table.
- Future changes to Tally's schema don't affect invoice generation or the AI agent.

### How the link is maintained

The link is bidirectional:

```
tally_ledgers.mapped_client_id  →  clients.id
clients.tally_ledger_id         →  tally_ledgers.id
```

Both sides are nullable FKs. When a ledger is synced and auto-mapped, both are written simultaneously. This allows querying from either direction.

---

## 7. Inbound Sync — How Tally Data Enters Accobot

All inbound sync runs through `TallyInboundSync` (service) → called by `TallyInboundMastersController` / `TallyInboundVouchersController` / `TallyInboundReportsController`.

### The sync loop — same for every entity

```
For each item in payload:
  1. Strip \u0004 prefix from all string values (Tally encoding artifact)
  2. Read tally_id (= ID field in payload)
  3. Look up existing row by (tenant_id, tally_id)
  4. Compare alter_id:
     - Same → skip (records_skipped++)
     - Different → proceed
  5. If Action = "Delete" → set is_active = false, stop
  6. Upsert all fields
  7. Run auto-mapping (ledger → client/vendor, stock item → product, sales voucher → invoice)

After loop (if full_sync = true):
  Mark all rows NOT in the payload as is_active = false
```

### The \u0004 strip

Tally sometimes prefixes string values with the ASCII character `\u0004` (End of Transmission). Every string field is run through `ltrim($v, "\u{0004}")` before being stored. The strip is recursive — it applies to nested arrays too (e.g. `Addresses`, `BankDetails` jsonb fields).

### Voucher child entries

Vouchers have two child collections: `InventoryEntries` (stock line items) and `LedgerEntries` (debit/credit movements). On every voucher upsert — whether create or update — the old child rows are deleted first and re-inserted fresh. This avoids tracking changes in child arrays and ensures the stored data always exactly matches Tally.

```php
TallyVoucherInventoryEntry::withoutGlobalScope('tenant')
    ->where('tally_voucher_id', $voucher->id)->delete();
TallyVoucherLedgerEntry::withoutGlobalScope('tenant')
    ->where('tally_voucher_id', $voucher->id)->delete();
// then re-insert from payload
```

### Full sync mode

The connector can send `"full_sync": true` in the payload. This means the payload contains **all** active records for that entity. After processing, any row in Accobot that was not present in the payload is marked `is_active = false`. This handles deletions that happen outside of Tally's normal `Action: Delete` flow.

### Sync log lifecycle

Every sync method starts by creating a `TallySyncLog` with `status = running`. The counters (`records_created`, `records_updated`, `records_skipped`, `records_failed`) are incremented in memory as each item is processed. At the end:
- Success → status set to `success`, counters saved, `last_synced_at` on the connection updated.
- Exception → status set to `failed`, `error_message` saved.

The log is returned to the controller, which passes the counters back to the connector as the response.

### Sync order

When first connecting a Tally company, sync in this order to satisfy FK dependencies:

```
1. ledger-groups     (no deps)
2. ledgers           (refs ledger-groups by name, not FK, but logically first)
3. stock-groups      (no deps)
4. stock-categories  (no deps)
5. stock-items       (refs stock-groups and stock-categories by name)
6. vouchers/sales    (party_tally_ledger_id refs tally_ledgers)
7. vouchers/purchase
8. vouchers/credit-note
   ... rest of voucher types
```

---

## 8. Auto-Mapping — Tally → Accobot Operational Models

After each upsert, the sync service checks whether the record should be reflected in Accobot's operational tables. This is the bridge between the Tally mirror and the rest of the app.

### Ledger → Client (category = customer)

**Trigger:** `syncLedgers()` → `autoMapLedger()` — runs for every ledger whose `ledger_category` is `customer`.

Resolution order:
1. Look for a `clients` row already linked by `tally_ledger_id` — update it.
2. If not found, look for a **placeholder** `Client` with the same name and `tally_ledger_id = null` (created earlier from a voucher's buyer fields) — claim it by writing `tally_ledger_id` and updating all fields.
3. If still not found — create a new `Client`.

What gets mapped: name (prefers mailing name), email, phone (prefers mobile), company name, and tax ID (prefers GSTIN, falls back to PAN). Everything else (bank details, credit terms, addresses, aliases) stays in `tally_ledgers`.

### Ledger → Vendor (category = vendor)

Identical resolution order. Same fields mapped, stored in `vendors` table with `tally_ledger_id` link.

### Stock Item → Product

**Trigger:** `syncStockItems()` → `autoMapStockItem()` — runs for every stock item.

Resolution order:
1. Look for a `products` row already linked by `tally_stock_item_id` — update it.
2. If not found, look for a **placeholder** `Product` with the same name and `tally_stock_item_id = null` (created earlier from a voucher inventory entry) — claim it by writing `tally_stock_item_id` and updating all fields.
3. If still not found — create a new `Product`.

What gets mapped: name, description, unit (falls back to "pcs"), price (prefers standard price, falls back to cost), tax rate (IGST rate). HSN code, batch info, stock levels, costing method stay in `tally_stock_items`.

### Sales Voucher → Invoice

**Trigger:** `syncVouchers()` → `autoMapSalesVoucher()` — only runs when `$type === 'Sales'`.

**Client resolution (with auto-create fallback):** The sync first tries to find a `clients` row via `party_tally_ledger_id`. If the party ledger hasn't been synced yet, it falls back to creating a placeholder `Client` from the voucher's buyer fields (`BuyerName`/`PartyName`, `BuyerGSTIN`, `BuyerEmail`, `BuyerMobile`, `BuyerAddress`). When the ledger syncs later, `autoMapLedger()` will `updateOrCreate` on `tally_ledger_id` and link up automatically. The only case where invoice mapping is skipped is if neither `BuyerName` nor `PartyName` is present in the voucher.

**Product resolution (with auto-create fallback):** For each `InventoryEntry`, if no `TallyStockItem` row matches `StockItemName`, a placeholder `Product` is created from the entry's `StockItemName`, `Rate`, `Unit`, and `IGSTRate`. When stock items sync later, `autoMapStockItem()` will update the product via `tally_stock_item_id`.

What gets mapped: voucher number as invoice number, client, date, total, narration as notes. The GST breakdown, e-invoice IRN/QR code, buyer/consignee details, dispatch info, and line items all stay in `tally_vouchers` and its child tables.

**Note:** Only Sales vouchers map to invoices. The other 7 voucher types (Purchase, Receipt, Payment, Contra, Journal, CreditNote, DebitNote) are stored in `tally_vouchers` for reporting but do not create Accobot records.

---

## 9. Ledger Categorization Rules

`deriveCategory()` is called during `syncLedgers()` for every ledger. It examines `group_name` and `parent_group` (both lowercased, concatenated) to determine the `ledger_category`. The order of checks matters.

| Check order | Condition | Category | Effect |
|-------------|-----------|----------|--------|
| 1 | contains "debtor" | `customer` | → auto-create/update client |
| 2 | contains "creditor" or "supplier" | `vendor` | → auto-create/update vendor |
| 3 | contains "bank", "cash-in-hand", or "cash" | `bank` | no mapping |
| 4 | contains "duties & taxes", "gst", "tds", or "tcs" | `tax` | no mapping |
| 5 | contains "sales" or "income" | `income` | no mapping |
| 6 | contains "expense" | `expense` | no mapping |
| 7 | contains "fixed assets", "current assets", or "investments" | `asset` | no mapping |
| 8 | contains "loans", "capital", "current liabilities", or "provisions" | `liability` | no mapping |
| 9 | none of the above | `other` | no mapping |

Tax (check 4) deliberately comes before income (check 5) because some tax group names could match income patterns.

---

## 10. Outbound GET — Accobot Data Served to Tally

These are the exact `cloud-tally.in` Swagger paths. The Tally connector polls them to fetch any Accobot-originated records that should appear in Tally.

**Flow:**
1. Request arrives with Bearer token + `?companyId=` query param.
2. `TallyBaseController::resolveConnection()` authenticates and finds the tenant.
3. `companyId` is verified against `tally_connections.company_id`.
4. `TallyOutboundController` queries the relevant `tally_*` table for `is_active = true` records.
5. `TallyOutboundFormatter` converts them to Tally's exact payload structure.
6. Response: `{ "Data": [...] }`

**Current scope:** These endpoints return data from the `tally_*` mirror tables (records that came from Tally originally). The Outbound formatter includes all fields Tally expects, preserving exact casing and using `"Yes"` / `"No"` for boolean fields (Tally's format).

**Endpoints:**
```
GET /api/MastersAPI/ledger-group?companyId=
GET /api/MastersAPI/ledger-master?companyId=
GET /api/MastersAPI/stock-master?companyId=
GET /api/MastersAPI/stock-group?companyId=
GET /api/MastersAPI/stock-category?companyId=
GET /api/VoucherAPI/sales-voucher?companyId=
GET /api/VoucherAPI/purchase-voucher?companyId=
GET /api/VoucherAPI/debitNote-voucher?companyId=
GET /api/VoucherAPI/creditNote-voucher?companyId=
```

---

## 11. Confirmation POST — Tally Writes Back Its IDs

After Tally creates an Accobot-originated record, it confirms the creation by POSTing back the ID it assigned.

**Request body:**
```json
{
    "Data": [
        { "Id": 42, "TallyId": "1234", "IsSynced": true }
    ]
}
```

- `Id` = Accobot's primary key in the tally_* table
- `TallyId` = the integer ID Tally assigned
- `IsSynced` = confirmation flag

`TallyConfirmController` looks up the record by `Id` and `tenant_id`, then updates its `tally_id` column with the value Tally assigned. This ensures the record can be deduped correctly on future inbound syncs.

**Endpoints:**
```
POST /api/MastersAPI/update-ledger-master/{companyId}
POST /api/MastersAPI/update-stock-master/{companyId}
POST /api/MastersAPI/update-ledger-group/{companyId}
POST /api/MastersAPI/update-stock-group/{companyId}
POST /api/MastersAPI/update-stock-category/{companyId}
POST /api/VoucherAPI/update-sales-voucher/{companyId}
POST /api/VoucherAPI/update-purchase-voucher/{companyId}
POST /api/VoucherAPI/update-debitnote-voucher/{companyId}
POST /api/VoucherAPI/update-creditnote-voucher/{companyId}
```

---

## 12. Report Snapshots

Tally can push four financial reports: balance sheet, profit & loss, cash flow, and ratio analysis. Unlike masters and vouchers, **reports are never updated** — each push creates a new row in `tally_reports`.

This gives you a full time-series history: you can see the balance sheet as of any date Tally has ever pushed it. The `period_to` field is the reporting date; `period_from` is null for balance sheets (which are point-in-time), present for P&L and cash flow.

The `data` column stores the complete JSON payload as Tally generated it — no interpretation or restructuring.

**Payload format from connector:**
```json
{
    "report_type": "balance_sheet",
    "period_from": null,
    "period_to": "2024-03-31",
    "generated_at": "2024-04-01T09:00:00",
    "data": { ...full Tally report JSON... }
}
```

---

## 13. Sync Logs & Observability

Every sync operation — inbound and outbound — creates a `tally_sync_logs` row. The Sync page in the UI displays these in four tabs.

### What each tab shows

| Tab | What it displays |
|-----|-----------------|
| Masters | Latest sync status per entity: ledger_groups, ledgers, stock_groups, stock_categories, stock_items |
| Vouchers | Latest sync status per voucher type: sales, purchase, credit_note, debit_note, receipt, payment, contra, journal |
| Reports | All report snapshots with type, period, and received timestamp |
| Logs | Full log table (last 200 entries), expandable error messages |

### Stats bar on Sync page

Four live counts pulled directly from `tally_*` tables:
- Total active ledger groups
- Total active ledgers
- Total active stock items
- Total active vouchers

### "Sync Now" button

Because Accobot cannot pull from Tally, this button does not trigger a data pull. It creates a `manual_trigger` log entry as a reminder and informs the user that data flows automatically from the connector.

---

## 14. All 35 API Endpoints

All routes are in `routes/api.php`. All are throttled at 120 requests/minute. None require Sanctum — token auth only.

### Inbound POST — 17 endpoints

| Endpoint | Controller Method | Entity |
|----------|------------------|--------|
| POST /api/tally/inbound/masters/ledger-groups | `ledgerGroups` | tally_ledger_groups |
| POST /api/tally/inbound/masters/ledgers | `ledgers` | tally_ledgers |
| POST /api/tally/inbound/masters/stock-items | `stockItems` | tally_stock_items |
| POST /api/tally/inbound/masters/stock-groups | `stockGroups` | tally_stock_groups |
| POST /api/tally/inbound/masters/stock-categories | `stockCategories` | tally_stock_categories |
| POST /api/tally/inbound/vouchers/sales | `sales` | tally_vouchers (Sales) |
| POST /api/tally/inbound/vouchers/credit-note | `creditNote` | tally_vouchers (CreditNote) |
| POST /api/tally/inbound/vouchers/purchase | `purchase` | tally_vouchers (Purchase) |
| POST /api/tally/inbound/vouchers/debit-note | `debitNote` | tally_vouchers (DebitNote) |
| POST /api/tally/inbound/vouchers/receipt | `receipt` | tally_vouchers (Receipt) |
| POST /api/tally/inbound/vouchers/payment | `payment` | tally_vouchers (Payment) |
| POST /api/tally/inbound/vouchers/contra | `contra` | tally_vouchers (Contra) |
| POST /api/tally/inbound/vouchers/journal | `journal` | tally_vouchers (Journal) |
| POST /api/tally/inbound/reports/balance-sheet | `balanceSheet` | tally_reports |
| POST /api/tally/inbound/reports/profit-loss | `profitLoss` | tally_reports |
| POST /api/tally/inbound/reports/cash-flow | `cashFlow` | tally_reports |
| POST /api/tally/inbound/reports/ratio-analysis | `ratioAnalysis` | tally_reports |

**Request format:** `{ "Data": [...] }` (masters) / `{ "data": [...] }` (vouchers — both cases accepted)  
**Response format:** `{ "status": "success", "created": N, "updated": N, "skipped": N, "failed": N }`

### Outbound GET — 9 endpoints

| Endpoint | Controller Method | Returns |
|----------|------------------|---------|
| GET /api/MastersAPI/ledger-group?companyId= | `ledgerGroup` | tally_ledger_groups |
| GET /api/MastersAPI/ledger-master?companyId= | `ledgerMaster` | tally_ledgers |
| GET /api/MastersAPI/stock-master?companyId= | `stockMaster` | tally_stock_items |
| GET /api/MastersAPI/stock-group?companyId= | `stockGroup` | tally_stock_groups |
| GET /api/MastersAPI/stock-category?companyId= | `stockCategory` | tally_stock_categories |
| GET /api/VoucherAPI/sales-voucher?companyId= | `salesVoucher` | tally_vouchers (Sales) |
| GET /api/VoucherAPI/purchase-voucher?companyId= | `purchaseVoucher` | tally_vouchers (Purchase) |
| GET /api/VoucherAPI/debitNote-voucher?companyId= | `debitNoteVoucher` | tally_vouchers (DebitNote) |
| GET /api/VoucherAPI/creditNote-voucher?companyId= | `creditNoteVoucher` | tally_vouchers (CreditNote) |

**Response format:** `{ "Data": [...] }` — Tally-compatible field names and casing

### Confirmation POST — 9 endpoints

| Endpoint | Controller Method | Writes TallyId to |
|----------|------------------|------------------|
| POST /api/MastersAPI/update-ledger-master/{companyId} | `ledgerMaster` | tally_ledgers.tally_id |
| POST /api/MastersAPI/update-stock-master/{companyId} | `stockMaster` | tally_stock_items.tally_id |
| POST /api/MastersAPI/update-ledger-group/{companyId} | `ledgerGroup` | tally_ledger_groups.tally_id |
| POST /api/MastersAPI/update-stock-group/{companyId} | `stockGroup` | tally_stock_groups.tally_id |
| POST /api/MastersAPI/update-stock-category/{companyId} | `stockCategory` | tally_stock_categories.tally_id |
| POST /api/VoucherAPI/update-sales-voucher/{companyId} | `salesVoucher` | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-purchase-voucher/{companyId} | `purchaseVoucher` | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-debitnote-voucher/{companyId} | `debitNoteVoucher` | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-creditnote-voucher/{companyId} | `creditNoteVoucher` | tally_vouchers.tally_id |

---

## 15. Web UI Routes & Pages

All web routes are inside the `Route::middleware(['auth', 'verified', 'member'])->prefix('t/{tenant}')` group.

| Method | Path | Route name | Permission required |
|--------|------|-----------|---------------------|
| GET | /t/{tenant}/settings/tally | tally.connection.show | integrations.view |
| POST | /t/{tenant}/settings/tally | tally.connection.save | integrations.manage |
| GET | /t/{tenant}/settings/tally/test | tally.connection.test | integrations.manage |
| POST | /t/{tenant}/settings/tally/regenerate-token | tally.connection.regenerate-token | integrations.manage |
| DELETE | /t/{tenant}/settings/tally | tally.connection.destroy | integrations.manage |
| GET | /t/{tenant}/tally/sync | tally.sync.index | integrations.view |
| POST | /t/{tenant}/tally/sync | tally.sync.trigger | integrations.manage |
| GET | /t/{tenant}/tally/ledger-groups | tally.ledger-groups.index | integrations.view |
| GET | /t/{tenant}/tally/ledgers | tally.ledgers.index | integrations.view |
| GET | /t/{tenant}/tally/stock-items | tally.stock-items.index | integrations.view |
| GET | /t/{tenant}/tally/vouchers | tally.vouchers.index | integrations.view |
| GET | /t/{tenant}/tally/vouchers/{voucher} | tally.vouchers.show | integrations.view |

### Connection.vue — three sections

1. **Token card** (shown only after first save): Base URL, masked token with show/hide toggle and copy button, last-used timestamp, Regenerate Token button.
2. **"Enter These in Tally" table**: The three values the tenant must enter into the connector — Base URL, Token, Company ID — all copyable.
3. **Settings form**: Company ID input, is_active toggle, Save / Test Connection / Remove buttons.

### Sync.vue — four tabs + stats

- **Stats bar**: Live counts of total ledger groups, ledgers, stock items, vouchers. Each card is a link to the corresponding data-browse page.
- **Masters tab**: Latest sync status per master entity with created/updated/skipped counts and timestamp.
- **Vouchers tab**: Latest sync status per voucher type.
- **Reports tab**: List of all report snapshots with type, period, generated and received timestamps.
- **Logs tab**: Full log history (last 200), expandable error messages, colour-coded status badges.

### Data browse pages (TallyDataController)

| Page | Route | Description |
|------|-------|-------------|
| LedgerGroups.vue | tally.ledger-groups.index | Search + filter all synced ledger groups |
| Ledgers.vue | tally.ledgers.index | Search + group-filter ledgers with Client/Vendor mapping badges |
| StockItems.vue | tally.stock-items.index | Search + group-filter stock items with Product mapping badges |
| Vouchers.vue | tally.vouchers.index | Filter by voucher type, click-through to detail |
| VoucherShow.vue | tally.vouchers.show | Full voucher detail: inventory entries + ledger entries |

### Seeder

`database/seeders/TallySeeder.php` seeds the **Tili** tenant with:
- 1 TallyConnection
- 8 LedgerGroups, 14 Ledgers (3 Debtors mapped to Clients, 2 Creditors mapped to Vendors, sales/purchase/bank/cash/GST accounts)
- 5 StockGroups, 3 StockCategories, 10 StockItems (mapped to first 5 Products)
- 10 Vouchers (Sales ×2, Purchase ×2, Receipt, Payment, Credit Note, Debit Note, Contra, Journal) with inventory and ledger entries
- 5 Report snapshots (trial balance, P&L, balance sheet, sales register, purchase register)
- 20 TallySyncLog entries (two sync runs + a failed attempt + manual trigger)

### Nav link

The "Tally" link appears in the top navigation for any user with `integrations.view` permission:

```vue
<template v-if="hasPermission('integrations.view')">
    <NavLink
        :href="route('tally.sync.index', { tenant: currentTenantId() })"
        :active="route().current('tally.sync.index') || route().current('tally.connection.show')"
    >Tally</NavLink>
</template>
```

---

## 16. Service & Controller Map

### Services

| Class | Location | Responsibility |
|-------|----------|---------------|
| `TallyInboundSync` | `app/Services/Tally/` | All inbound upsert logic — 6 public methods. Creates sync logs, strips \u0004, runs AlterID check, upserts, runs auto-mapping. |
| `TallyReportSync` | `app/Services/Tally/` | Report snapshot inserts. 1 public method. |
| `TallyOutboundFormatter` | `app/Services/Tally/` | Formats Tally mirror records into Tally's exact payload structure. 9 public methods. |

### API Controllers

| Class | Responsibility |
|-------|---------------|
| `TallyBaseController` | Resolves `TallyConnection` from Bearer token. Stamps `inbound_token_last_used_at`. Verifies `companyId`. Base class for all Tally API controllers. |
| `TallyInboundMastersController` | 5 methods: ledgerGroups, ledgers, stockItems, stockGroups, stockCategories. Each reads `Data` array from request and calls `TallyInboundSync`. |
| `TallyInboundVouchersController` | 8 methods: sales, creditNote, purchase, debitNote, receipt, payment, contra, journal. All delegate to `TallyInboundSync::syncVouchers()` with the appropriate type string. |
| `TallyInboundReportsController` | 4 methods: balanceSheet, profitLoss, cashFlow, ratioAnalysis. Each calls `TallyReportSync::syncReport()`. |
| `TallyOutboundController` | 9 methods (5 masters + 4 voucher types). Queries tally_* tables, formats via `TallyOutboundFormatter`, returns `{ "Data": [...] }`. |
| `TallyConfirmController` | 9 methods. Reads `{ "Data": [{ "Id", "TallyId", "IsSynced" }] }`. Updates `tally_id` on the matching row. |

### Web Controllers

| Class | Responsibility |
|-------|---------------|
| `TallyConnectionController` | show(), save(), regenerateToken(), destroy(), testConnection(). Manages the `tally_connections` row for a tenant. |
| `TallySyncController` | index() (builds Sync.vue props: latest logs per entity, all logs, report snapshots, stats), trigger() (logs a manual trigger entry). |

---

## 17. Full File Map

```
database/migrations/
  2026_04_19_000001_create_tally_connections_table.php
  2026_04_19_000002_create_tally_ledger_groups_table.php
  2026_04_19_000003_create_tally_ledgers_table.php
  2026_04_19_000004_create_tally_stock_groups_table.php
  2026_04_19_000005_create_tally_stock_categories_table.php
  2026_04_19_000006_create_tally_stock_items_table.php
  2026_04_19_000007_create_tally_vouchers_table.php
  2026_04_19_000008_create_tally_voucher_inventory_entries_table.php
  2026_04_19_000009_create_tally_voucher_ledger_entries_table.php
  2026_04_19_000010_create_tally_reports_table.php
  2026_04_19_000011_create_tally_sync_logs_table.php
  2026_04_19_000012_add_tally_fk_to_existing_models.php

app/Models/
  TallyConnection.php          — per-tenant auth token, auto-generates on creating()
  TallyLedgerGroup.php
  TallyLedger.php              — mapped_client_id / mapped_vendor_id FKs
  TallyStockGroup.php
  TallyStockCategory.php
  TallyStockItem.php           — mapped_product_id FK
  TallyVoucher.php             — mapped_invoice_id FK; has inventoryEntries / ledgerEntries relations
  TallyVoucherInventoryEntry.php
  TallyVoucherLedgerEntry.php
  TallyReport.php
  TallySyncLog.php
  Client.php                   — added tally_ledger_id FK + tallyLedger() relation
  Vendor.php                   — added tally_ledger_id FK + tallyLedger() relation
  Product.php                  — added tally_stock_item_id FK + tallyStockItem() relation
  Invoice.php                  — added tally_voucher_id FK + tallyVoucher() relation

app/Services/Tally/
  TallyInboundSync.php
  TallyReportSync.php
  TallyOutboundFormatter.php

app/Http/Controllers/
  TallyConnectionController.php
  TallySyncController.php
  Api/Tally/
    TallyBaseController.php
    TallyInboundMastersController.php
    TallyInboundVouchersController.php
    TallyInboundReportsController.php
    TallyOutboundController.php
    TallyConfirmController.php

resources/js/Pages/Tally/
  Connection.vue
  Sync.vue
```

---

## 18. Known Limitations & Pending Design Decisions

### Implemented

- Accobot-side edits (renaming a ledger group, changing a ledger's address) do **not** bump AlterID — the connector has no signal that anything changed and will skip the record on the next inbound sync.
- Deletions in Accobot are **not** propagated to Tally.
- No queue — the connector should send max ~500 items per request to avoid PHP timeout.
- **"Sync Now" button is a no-op.** Because Accobot cannot pull from Tally (the connector always initiates), the button only logs a `manual_trigger` entry and shows a reminder message. It does not trigger any actual data transfer. To sync, the Tally connector must be running and pushing data on its own schedule.
- **`tax_amount` is always `0` on auto-mapped invoices.** Tax breakdown is available in `tally_voucher_ledger_entries` (IGST/SGST/CGST lines) but is not summed into `invoices.tax_amount` during auto-mapping.
- **`due_date` defaults to `voucher_date`.** Tally ledgers carry a `CreditPeriod` field but it is not applied when computing the invoice due date.
- **Invoice status is always `unpaid` after auto-mapping.** No cross-check is done against Receipt vouchers that may have already settled the sales voucher in Tally.
- **Placeholder claim is name-match only.** When `autoMapLedger()` or `autoMapStockItem()` claims a placeholder Client/Product created from a voucher, it matches on `name` alone. If two clients or products share the same name, the wrong placeholder could be claimed.

### Pending — Accobot-created Invoices → Tally

When an Invoice is created directly in Accobot (not synced from Tally), the following questions are **not yet resolved** and will require a design decision before implementation:

1. **Flow direction** — The current architecture is Tally-pulls-only (connector GETs Accobot data). Should Accobot-created invoices appear in `GET /api/VoucherAPI/sales-voucher` for Tally to pick up on its next poll, or should Accobot push proactively?

2. **Unmapped client** — If the invoice's `Client` has no `tally_ledger_id` (created in Accobot, never came from Tally):
   - Include in outbound response so Tally creates a new ledger on its end?
   - Block/skip the invoice until the client is manually mapped to a Tally ledger?
   - Auto-create a stub `TallyLedger` row so the confirmation POST can link it up?

3. **Unmapped product** — Same question for line items whose `Product` has no `tally_stock_item_id`.

4. **Invoice line items** — Currently `autoMapSalesVoucher()` only creates the Invoice header; line items from `tally_voucher_inventory_entries` are not reflected as `InvoiceItem` rows. Should this be addressed as part of the Accobot→Tally work?
