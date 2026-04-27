# Tally Integration — Comprehensive Guide

> For complete request/response examples for every endpoint, see **[docs/tally-api-reference.md](./tally-api-reference.md)**.

## Table of Contents

1. [How It Works — Big Picture](#1-how-it-works--big-picture)
2. [The Three Data Flows](#2-the-three-data-flows)
3. [Authentication & Tenant Resolution](#3-authentication--tenant-resolution)
4. [Setting Up a Connection](#4-setting-up-a-connection)
5. [Database Design — 16 Tables + 4 FK Columns](#5-database-design--16-tables--4-fk-columns)
6. [The Two-Table Design Pattern](#6-the-two-table-design-pattern)
7. [Inbound Sync — How Tally Data Enters Accobot](#7-inbound-sync--how-tally-data-enters-accobot)
8. [Auto-Mapping — Tally → Accobot Operational Models](#8-auto-mapping--tally--accobot-operational-models)
9. [Ledger Categorization Rules](#9-ledger-categorization-rules)
10. [Outbound GET — Accobot Data Served to Tally](#10-outbound-get--accobot-data-served-to-tally)
11. [Confirmation POST — Tally Writes Back Its IDs](#11-confirmation-post--tally-writes-back-its-ids)
12. [Report Snapshots](#12-report-snapshots)
13. [Sync Logs & Observability](#13-sync-logs--observability)
14. [All 66 API Endpoints](#14-all-66-api-endpoints)
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

Each tenant has exactly **one** `tally_connections` row. That row holds the bearer token the connector uses for every request. When the connector pushes data, Accobot auto-upserts a `tally_companies` row capturing the company name, GUID, licence type, and licence number from the payload.

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

All 63 API endpoints (inbound, outbound, confirmation) use the same token-based auth. There is **no Sanctum** involved — the Tally connector is not a user.

### How it works

1. On `TallyConnection::creating()`, a 48-character random token is auto-generated and stored in `inbound_token` (which is in `$hidden` so it never leaks in JSON responses).
2. The tenant copies this token into the Tally connector's configuration.
3. Every connector request sends `Authorization: Bearer <token>`.
4. `TallyBaseController::resolveConnection()` looks up the token **across all tenants** (`withoutGlobalScope('tenant')`) and returns the matching `TallyConnection`.
5. If the token doesn't exist or `is_active = false`, it returns HTTP 401 immediately.
6. On every successful request, `inbound_token_last_used_at` is stamped so you can see when Tally last talked to Accobot.

All endpoints — including confirmation — use only the Bearer token for auth. `companyId` is not required anywhere.

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

## 5. Database Design — 17 Tables + 4 FK Columns

### Overview

```
tally_connections          ← one per tenant, holds auth token
tally_companies            ← discovered Tally companies (auto-upserted on each push)
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
tally_statutory_masters    ← GST / TDS / TCS / PF / ESI / PT registrations
tally_employee_groups      ← payroll group hierarchy
tally_pay_heads            ← payroll earning / deduction / statutory heads
tally_attendance_types     ← Tally attendance & leave types
tally_employees            ← employee master with payroll details
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

**`alter_id`** — Stored from the incoming payload but not used for any logic. The `Action` field (`Create` / `Update` / `Delete`) is the sole driver of inbound sync behaviour.

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
| action | string | Create / Alter / Delete (stored as-is; only Delete changes behaviour) |
| name | string | Group name |
| under_id / under_name | integer / string | Parent group |
| nature_of_group | string | Assets / Liabilities / Income / Expenses |
| is_active | bool | |

#### tally_ledgers
All account masters — customers, vendors, bank accounts, tax ledgers, expense heads, etc.

| Section | Columns |
|---------|---------|
| Identity | tally_id, alter_id, action, ledger_name, group_name, parent_group |
| Derived | ledger_category (customer/vendor/bank/tax/income/expense/asset/liability/other) |
| Flags | is_bill_wise_on, inventory_affected |
| GST | gstin_number, pan_number, gst_type |
| Contact | mailing_name, mobile_number, contact_person, contact_person_email, contact_person_email_cc, contact_person_fax, contact_person_website, contact_person_mobile |
| Address | addresses (jsonb), state_name, country_name, pin_code |
| Credit | credit_period, credit_limit |
| Opening Balance | opening_balance, opening_balance_type (Dr/Cr) |
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
| Pricing | mrp_rate |
| Stock | opening_balance, opening_rate, opening_value, closing_balance, closing_rate, closing_value |
| Mapping | mapped_product_id FK → products |

#### tally_stock_groups
| Column | Notes |
|--------|-------|
| name | Group name |
| parent | Parent group (maps to `Parent` in payload) |
| aliases | jsonb |

#### tally_stock_categories
| Column | Notes |
|--------|-------|
| name | Category name |
| parent | Parent category (maps to `Parent` in payload) |
| aliases | jsonb |

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

#### tally_statutory_masters
Statutory registrations — GST, TDS, TCS, PF, ESI, PT, etc.

| Column | Notes |
|--------|-------|
| statutory_type | GST / TDS / TCS / PF / ESI / PT |
| registration_number | GSTIN, TAN, PF registration number, etc. |
| state_code | For GST — state code |
| registration_type | Regular / Composition / etc. |
| pan | PAN linked to the registration |
| tan | TAN for TDS/TCS registrations |
| applicable_from | date — when this registration became effective |
| details | jsonb — any additional statutory fields from Tally |

#### tally_employee_groups
| Column | Notes |
|--------|-------|
| name | Group name |
| guid | Tally GUID |
| under | Parent group (maps to `Under` in payload) |
| cost_centre_category | Maps to `CostCentreCategory` in payload |

#### tally_pay_heads
Payroll earning, deduction, and statutory heads.

| Column | Notes |
|--------|-------|
| pay_type | Earnings for Employees / Deductions / etc. (maps to `PayType` in payload) |
| income_type | Fixed / Variable / etc. |
| parent_group | Parent ledger group (maps to `ParentGroup` in payload) |
| calculation_type | On Attendance / As Computed Value / etc. |
| leave_type | LOP / etc. |
| calculation_period | Monthly / Days / etc. (maps to `CalculationPeriod` in payload) |

#### tally_attendance_types
| Column | Notes |
|--------|-------|
| attendance_type | Attendance / Leave with Pay / Leave without Pay / Productivity |
| attendance_period | Days / Hours / Pieces (maps to connector field `AttendancePeriod`) |

#### tally_employees
Full employee master with payroll and statutory details.

| Section | Columns |
|---------|---------|
| Identity | name, employee_number, parent, designation, employee_function, location |
| Dates | date_of_joining, date_of_leaving, date_of_birth |
| Personal | gender, father_name, spouse_name |
| Other | aliases (jsonb) |

> **Note:** The column is named `employee_function` (not `function`) to avoid PostgreSQL and PHP reserved-word conflicts.

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

Inbound sync runs through two services:
- **`TallyInboundSync`** — handles ledgers, stock items, vouchers, statutory masters. Called by `TallyInboundMastersController` / `TallyInboundVouchersController` / `TallyInboundReportsController`.
- **`TallyPayrollSync`** — handles payroll entities (employee groups, employees, pay heads, attendance types). Called by `TallyInboundPayrollController`.

Both services `use TallySyncHelpers` — a shared PHP trait that provides `startLog()`, `completeLog()`, `failLog()`, `strip()`, and `parseDate()`. This eliminates duplication and ensures consistent counter handling across all sync methods.

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
  6. Otherwise (Action = "Create" OR "Alter" OR anything else) → upsert all fields
     - "Alter" is NOT handled separately — it falls through to the same upsert path as "Create".
       The string value ("Create" or "Alter") is stored as-is in the action column for audit purposes.
  7. Run auto-mapping (ledger → client/vendor, stock item → product, sales voucher → invoice)
     (auto-mapping re-runs on every upsert, whether the action was Create or Alter)

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
2. If not found, look for a **placeholder** `Client` with `tally_ledger_id = null` — try matching by GSTIN/PAN (`tax_id`) first, then fall back to name. This handles the case where `buyer_name` on the voucher differs from `mailing_name` on the ledger.
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

The Tally connector polls these endpoints to pick up changes made in Accobot. Only records with a **pending** entry in `tally_outbound_queue` are returned — the connector gets an empty `Data` array when there is nothing new.

**Change detection (outbound queue):**
- When any Tally model record is created or updated in Accobot, a `TallyModelObserver` upserts a `pending` row in `tally_outbound_queue (tenant_id, entity_type, entity_id)`.
- When an Accobot-native record (Client, Vendor, Product, Invoice) is created or updated, `TallyAccobotObserver` auto-creates a stub Tally record (TallyLedger / TallyStockItem / TallyVoucher) if none exists, then queues it.
- Observer queuing is **suppressed during inbound sync** via `TallyInboundSync::$syncing` to prevent loop-back.
- A record edited multiple times before the connector polls still produces a single `pending` entry (upsert).
- Status transitions: `pending` → `confirmed`. Records stay `pending` and are re-served on every poll until the confirmation endpoint marks them `confirmed`.

**Flow:**
1. Request arrives with Bearer token.
2. `TallyBaseController::resolveConnection()` authenticates and finds the tenant.
3. `TallyOutboundQueueService::pendingIds()` fetches IDs with `status = pending` for that entity type.
4. `TallyOutboundController` queries the relevant `tally_*` table, filtered to those IDs.
5. `TallyOutboundFormatter` converts them to Tally's exact payload structure.
6. Response: `{ "Data": [...] }` — empty array if nothing pending.

**Endpoints:**
```
GET /api/MastersAPI/ledger-group
GET /api/MastersAPI/ledger-master
GET /api/MastersAPI/stock-master
GET /api/MastersAPI/stock-group
GET /api/MastersAPI/stock-category
GET /api/MastersAPI/statutory-master
GET /api/PayrollAPI/employee-group
GET /api/PayrollAPI/employee
GET /api/PayrollAPI/pay-head
GET /api/PayrollAPI/attendance-type
GET /api/PayrollAPI/salary-voucher
GET /api/PayrollAPI/attendance-voucher
GET /api/VoucherAPI/sales-voucher
GET /api/VoucherAPI/purchase-voucher
GET /api/VoucherAPI/debitNote-voucher
GET /api/VoucherAPI/creditNote-voucher
GET /api/VoucherAPI/receipt-voucher
GET /api/VoucherAPI/payment-voucher
GET /api/VoucherAPI/contra-voucher
GET /api/VoucherAPI/journal-voucher
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
- `IsSynced` = when `true`, stamps `tally_synced_at` on the mapped Accobot model (Client, Vendor, Product, or Invoice)

`TallyConfirmController` looks up the record by `Id` and `tenant_id`, updates its `tally_id`, and marks the `tally_outbound_queue` entry as `confirmed`. When `IsSynced: true`, the corresponding auto-mapped model (e.g. the Client created from a Ledger) gets its `tally_synced_at` timestamp set.

**Endpoints:**
```
POST /api/MastersAPI/update-ledger-master
POST /api/MastersAPI/update-stock-master
POST /api/MastersAPI/update-ledger-group
POST /api/MastersAPI/update-stock-group
POST /api/MastersAPI/update-stock-category
POST /api/MastersAPI/update-statutory-master
POST /api/PayrollAPI/update-employee-group
POST /api/PayrollAPI/update-employee
POST /api/PayrollAPI/update-pay-head
POST /api/PayrollAPI/update-attendance-type
POST /api/PayrollAPI/update-salary-voucher
POST /api/PayrollAPI/update-attendance-voucher
POST /api/VoucherAPI/update-sales-voucher
POST /api/VoucherAPI/update-purchase-voucher
POST /api/VoucherAPI/update-debitnote-voucher
POST /api/VoucherAPI/update-creditnote-voucher
POST /api/VoucherAPI/update-receipt-voucher
POST /api/VoucherAPI/update-payment-voucher
POST /api/VoucherAPI/update-contra-voucher
POST /api/VoucherAPI/update-journal-voucher
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

### Inbound request log

Every inbound POST (all 22 endpoints across masters, payroll, vouchers, reports) also writes a raw copy of the full JSON body to `tally_inbound_logs` before any processing. This is separate from `tally_sync_logs` (which records the outcome). The inbound log is useful for debugging and replaying requests.

| Column | Notes |
|--------|-------|
| `tenant_id` | UUID FK → tenants |
| `tally_connection_id` | FK → tally_connections |
| `endpoint` | Request path e.g. `api/tally/inbound/masters/ledgers` |
| `payload` | Full JSON body (jsonb) |
| `created_at` | When the request arrived |

### Stats bar on Sync page

Live counts pulled directly from `tally_*` tables, grouped by category. Each card links to the corresponding browse page:
- Total active ledger groups / ledgers / stock items / vouchers
- Total active statutory masters
- Total active employees (payroll)

### Sync page tabs

| Tab | What it displays |
|-----|-----------------|
| Masters | Latest sync status per entity: ledger_groups, ledgers, stock_groups, stock_categories, stock_items, statutory_masters |
| Payroll | Latest sync status per payroll entity: employee_groups, employees, pay_heads, attendance_types |
| Vouchers | Latest sync status per voucher type: sales, purchase, credit_note, debit_note, receipt, payment, contra, journal |
| Reports | All report snapshots with type, period, and received timestamp |
| Logs | Full log table (last 200 entries), expandable error messages |

### "Sync Now" button

Because Accobot cannot pull from Tally, this button does not trigger a data pull. It creates a `manual_trigger` log entry as a reminder and informs the user that data flows automatically from the connector.

---

## 14. All 66 API Endpoints

All routes are in `routes/api.php`. All are throttled at 120 requests/minute. None require Sanctum — token auth only.

### Inbound POST — 22 endpoints

| Endpoint | Controller | Entity |
|----------|-----------|--------|
| POST /api/tally/inbound/masters/ledger-groups | `TallyInboundMastersController@ledgerGroups` | tally_ledger_groups |
| POST /api/tally/inbound/masters/ledgers | `@ledgers` | tally_ledgers |
| POST /api/tally/inbound/masters/stock-items | `@stockItems` | tally_stock_items |
| POST /api/tally/inbound/masters/stock-groups | `@stockGroups` | tally_stock_groups |
| POST /api/tally/inbound/masters/stock-categories | `@stockCategories` | tally_stock_categories |
| POST /api/tally/inbound/masters/statutory | `@statutory` | tally_statutory_masters |
| POST /api/tally/inbound/masters/company | `@company` | tally_companies |
| GET /api/MastersAPI/company-master | `TallyOutboundController@companyMaster` | tally_companies (pending queue) |
| POST /api/MastersAPI/update-company-master | `TallyConfirmController@companyMaster` | tally_companies.tally_id |
| POST /api/tally/inbound/payroll/employee-groups | `TallyInboundPayrollController@employeeGroups` | tally_employee_groups |
| POST /api/tally/inbound/payroll/employees | `@employees` | tally_employees |
| POST /api/tally/inbound/payroll/pay-heads | `@payHeads` | tally_pay_heads |
| POST /api/tally/inbound/payroll/attendance-types | `@attendanceTypes` | tally_attendance_types |
| POST /api/tally/inbound/payroll/salary-voucher | `TallyInboundVouchersController@salary` | tally_vouchers (Payroll) + tally_voucher_employee_allocations |
| POST /api/tally/inbound/payroll/attendance-voucher | `@attendance` | tally_vouchers (Attendance) + tally_voucher_employee_allocations |
| POST /api/tally/inbound/vouchers/sales | `TallyInboundVouchersController@sales` | tally_vouchers (Sales) |
| POST /api/tally/inbound/vouchers/credit-note | `@creditNote` | tally_vouchers (CreditNote) |
| POST /api/tally/inbound/vouchers/purchase | `@purchase` | tally_vouchers (Purchase) |
| POST /api/tally/inbound/vouchers/debit-note | `@debitNote` | tally_vouchers (DebitNote) |
| POST /api/tally/inbound/vouchers/receipt | `@receipt` | tally_vouchers (Receipt) |
| POST /api/tally/inbound/vouchers/payment | `@payment` | tally_vouchers (Payment) |
| POST /api/tally/inbound/vouchers/contra | `@contra` | tally_vouchers (Contra) |
| POST /api/tally/inbound/vouchers/journal | `@journal` | tally_vouchers (Journal) |
| POST /api/tally/inbound/reports/balance-sheet | `TallyInboundReportsController@balanceSheet` | tally_reports |
| POST /api/tally/inbound/reports/profit-loss | `@profitLoss` | tally_reports |
| POST /api/tally/inbound/reports/cash-flow | `@cashFlow` | tally_reports |
| POST /api/tally/inbound/reports/ratio-analysis | `@ratioAnalysis` | tally_reports |

**Request format:** `{ "Data": [...] }` (masters/payroll) / `{ "data": [...] }` (vouchers — both cases accepted)  
**Response format:** `{ "status": "success", "created": N, "updated": N, "skipped": N, "failed": N }`

The `employees` endpoint also accepts `"full_sync": true` — after processing, any employee not present in the payload is marked `is_active = false`.

### Outbound GET — 18 endpoints

| Endpoint | Returns |
|----------|---------|
| GET /api/MastersAPI/ledger-group | tally_ledger_groups |
| GET /api/MastersAPI/ledger-master | tally_ledgers |
| GET /api/MastersAPI/stock-master | tally_stock_items |
| GET /api/MastersAPI/stock-group | tally_stock_groups |
| GET /api/MastersAPI/stock-category | tally_stock_categories |
| GET /api/MastersAPI/statutory-master | tally_statutory_masters |
| GET /api/PayrollAPI/employee-group | tally_employee_groups |
| GET /api/PayrollAPI/employee | tally_employees |
| GET /api/PayrollAPI/pay-head | tally_pay_heads |
| GET /api/PayrollAPI/attendance-type | tally_attendance_types |
| GET /api/PayrollAPI/salary-voucher | tally_vouchers (Payroll) with EmployeeAllocations |
| GET /api/PayrollAPI/attendance-voucher | tally_vouchers (Attendance) with EmployeeAllocations |
| GET /api/VoucherAPI/sales-voucher | tally_vouchers (Sales) |
| GET /api/VoucherAPI/purchase-voucher | tally_vouchers (Purchase) |
| GET /api/VoucherAPI/debitNote-voucher | tally_vouchers (DebitNote) |
| GET /api/VoucherAPI/creditNote-voucher | tally_vouchers (CreditNote) |
| GET /api/VoucherAPI/receipt-voucher | tally_vouchers (Receipt) |
| GET /api/VoucherAPI/payment-voucher | tally_vouchers (Payment) |
| GET /api/VoucherAPI/contra-voucher | tally_vouchers (Contra) |
| GET /api/VoucherAPI/journal-voucher | tally_vouchers (Journal) |

**Response format:** `{ "Data": [...] }` — Tally-compatible field names and casing

### Confirmation POST — 23 endpoints

| Endpoint | Writes TallyId to |
|----------|------------------|
| POST /api/MastersAPI/update-ledger-master | tally_ledgers.tally_id |
| POST /api/MastersAPI/update-stock-master | tally_stock_items.tally_id |
| POST /api/MastersAPI/update-ledger-group | tally_ledger_groups.tally_id |
| POST /api/MastersAPI/update-stock-group | tally_stock_groups.tally_id |
| POST /api/MastersAPI/update-stock-category | tally_stock_categories.tally_id |
| POST /api/MastersAPI/update-statutory-master | tally_statutory_masters.tally_id |
| POST /api/PayrollAPI/update-employee-group | tally_employee_groups.tally_id |
| POST /api/PayrollAPI/update-employee | tally_employees.tally_id |
| POST /api/PayrollAPI/update-pay-head | tally_pay_heads.tally_id |
| POST /api/PayrollAPI/update-attendance-type | tally_attendance_types.tally_id |
| POST /api/PayrollAPI/update-salary-voucher | tally_vouchers.tally_id |
| POST /api/PayrollAPI/update-attendance-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-sales-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-purchase-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-debitnote-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-creditnote-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-receipt-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-payment-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-contra-voucher | tally_vouchers.tally_id |
| POST /api/VoucherAPI/update-journal-voucher | tally_vouchers.tally_id |

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
| POST | /t/{tenant}/tally/ledger-groups | tally.ledger-groups.store | integrations.manage |
| PUT | /t/{tenant}/tally/ledger-groups/{ledgerGroup} | tally.ledger-groups.update | integrations.manage |
| DELETE | /t/{tenant}/tally/ledger-groups/{ledgerGroup} | tally.ledger-groups.destroy | integrations.manage |
| GET | /t/{tenant}/tally/ledgers | tally.ledgers.index | integrations.view |
| POST | /t/{tenant}/tally/ledgers | tally.ledgers.store | integrations.manage |
| PUT | /t/{tenant}/tally/ledgers/{ledger} | tally.ledgers.update | integrations.manage |
| DELETE | /t/{tenant}/tally/ledgers/{ledger} | tally.ledgers.destroy | integrations.manage |
| GET | /t/{tenant}/tally/stock-masters | tally.stock-masters.index | integrations.view |
| POST | /t/{tenant}/tally/stock-groups | tally.stock-groups.store | integrations.manage |
| PUT | /t/{tenant}/tally/stock-groups/{stockGroup} | tally.stock-groups.update | integrations.manage |
| DELETE | /t/{tenant}/tally/stock-groups/{stockGroup} | tally.stock-groups.destroy | integrations.manage |
| POST | /t/{tenant}/tally/stock-categories | tally.stock-categories.store | integrations.manage |
| PUT | /t/{tenant}/tally/stock-categories/{stockCategory} | tally.stock-categories.update | integrations.manage |
| DELETE | /t/{tenant}/tally/stock-categories/{stockCategory} | tally.stock-categories.destroy | integrations.manage |
| GET | /t/{tenant}/tally/stock-items | tally.stock-items.index | integrations.view |
| POST | /t/{tenant}/tally/stock-items | tally.stock-items.store | integrations.manage |
| PUT | /t/{tenant}/tally/stock-items/{stockItem} | tally.stock-items.update | integrations.manage |
| DELETE | /t/{tenant}/tally/stock-items/{stockItem} | tally.stock-items.destroy | integrations.manage |
| GET | /t/{tenant}/tally/vouchers | tally.vouchers.index | integrations.view |
| GET | /t/{tenant}/tally/vouchers/{voucher} | tally.vouchers.show | integrations.view |
| GET | /t/{tenant}/tally/statutory-masters | tally.statutory-masters.index | integrations.view |
| POST | /t/{tenant}/tally/statutory-masters | tally.statutory-masters.store | integrations.manage |
| PUT | /t/{tenant}/tally/statutory-masters/{statutoryMaster} | tally.statutory-masters.update | integrations.manage |
| DELETE | /t/{tenant}/tally/statutory-masters/{statutoryMaster} | tally.statutory-masters.destroy | integrations.manage |
| GET | /t/{tenant}/tally/payroll | tally.payroll.index | integrations.view |
| POST | /t/{tenant}/tally/employees | tally.employees.store | integrations.manage |
| PUT | /t/{tenant}/tally/employees/{employee} | tally.employees.update | integrations.manage |
| DELETE | /t/{tenant}/tally/employees/{employee} | tally.employees.destroy | integrations.manage |
| POST | /t/{tenant}/tally/employee-groups | tally.employee-groups.store | integrations.manage |
| PUT | /t/{tenant}/tally/employee-groups/{employeeGroup} | tally.employee-groups.update | integrations.manage |
| DELETE | /t/{tenant}/tally/employee-groups/{employeeGroup} | tally.employee-groups.destroy | integrations.manage |
| POST | /t/{tenant}/tally/pay-heads | tally.pay-heads.store | integrations.manage |
| PUT | /t/{tenant}/tally/pay-heads/{payHead} | tally.pay-heads.update | integrations.manage |
| DELETE | /t/{tenant}/tally/pay-heads/{payHead} | tally.pay-heads.destroy | integrations.manage |
| POST | /t/{tenant}/tally/attendance-types | tally.attendance-types.store | integrations.manage |
| PUT | /t/{tenant}/tally/attendance-types/{attendanceType} | tally.attendance-types.update | integrations.manage |
| DELETE | /t/{tenant}/tally/attendance-types/{attendanceType} | tally.attendance-types.destroy | integrations.manage |

### Connection.vue — three sections

1. **Token card** (shown only after first save): Base URL, masked token with show/hide toggle and copy button, last-used timestamp, Regenerate Token button.
2. **"Enter These in Tally" table**: The three values the tenant must enter into the connector — Base URL, Token, Company ID — all copyable.
3. **Settings form**: Company ID input, is_active toggle, Save / Test Connection / Remove buttons.

### Sync.vue — five tabs + stats

- **Stats bar**: Live counts of ledger groups, ledgers, stock items, vouchers, statutory masters, and employees. Each card links to the corresponding data-browse page.
- **Masters tab**: Latest sync status per master entity — ledger_groups, ledgers, stock_groups, stock_categories, stock_items, statutory_masters.
- **Payroll tab**: Latest sync status per payroll entity — employee_groups, employees, pay_heads, attendance_types.
- **Vouchers tab**: Latest sync status per voucher type — sales, purchase, credit_note, debit_note, receipt, payment, contra, journal.
- **Reports tab**: List of all report snapshots with type, period, generated and received timestamps.
- **Logs tab**: Full log history (last 200), expandable error messages, colour-coded status badges.

### Data browse + CRUD pages

All pages show data to any user with `integrations.view`. Edit / New / Delete actions are only visible to users with `integrations.manage`.

**Delete behaviour**: if the record has a `tally_id` (i.e. was synced to Tally), the controller sets `is_active=false` and queues `Action: Delete` for the connector. If it has no `tally_id` (created in Accobot, never sent), it is hard-deleted.

**Outbound payload logging**: every outbound GET endpoint now logs `tally.outbound` to `storage/logs/laravel.log` with `entity`, `tenant_id`, `count`, and the full `payload` array. Empty responses (nothing pending) are not logged.

| Page | Route | CRUD entities |
|------|-------|---------------|
| LedgerGroups.vue | tally.ledger-groups.index | Ledger Groups — Name, Under, Nature of Group |
| Ledgers.vue | tally.ledgers.index | Ledgers — Name, Group, GSTIN, PAN, GST Type, State, Mobile, Opening Balance |
| StockMasters.vue | tally.stock-masters.index | Stock Groups + Stock Categories (tabs). Godowns tab is read-only (inbound only). |
| StockItems.vue | tally.stock-items.index | Stock Items — Name, Group, Category, Unit, HSN, GST rates (IGST/SGST/CGST/CESS), Opening Balance |
| StatutoryMasters.vue | tally.statutory-masters.index | Statutory Masters — Name, Type, Reg. No., State Code, Reg. Type, PAN, TAN, Applicable From |
| Payroll.vue | tally.payroll.index | 4 tabs: Employees, Employee Groups, Pay Heads, Attendance Types — all with full CRUD |
| Vouchers.vue | tally.vouchers.index | Full CRUD — all voucher parent fields (core, buyer, consignee, dispatch, order) in collapsible sections; full ledger entry fields (incl. igst_rate, hsn_code, cess_rate) and full inventory entry fields (incl. item_code, group_name, cess_rate, actual_qty, discount_percent, tax_amount, mrp, sales_ledger, godown_name, batch_name, is_deemed_positive). Sync status badge per row. |
| VoucherShow.vue | tally.vouchers.show | Read-only detail with inventory + ledger entries. |

#### Controllers
`TallyMasterCrudController` — 30 methods (store/update/destroy × 10 master entities). Handles both Option A (new Accobot-originated record) and Option B (editing an inbound-synced record). All saves trigger `TallyModelObserver` → outbound queue automatically.

`TallyVoucherCrudController` — store/update/destroy for `TallyVoucher`. Validation covers all parent fields (core, buyer, consignee, dispatch, order) and all child entry fields matching the inbound JSON payload structure. Update delete-reinserts ledger entries and inventory entries in a `DB::transaction()`. Delete: soft-deletes if `tally_id` set, hard-deletes + purges queue if never synced. `validationRules()` extracted as private method to avoid duplication between store and update.

### Seeder

`database/seeders/TallySeeder.php` seeds the **Tili** tenant with:
- 1 TallyConnection
- 8 LedgerGroups, 14 Ledgers (3 Debtors mapped to Clients, 2 Creditors mapped to Vendors, sales/purchase/bank/cash/GST accounts)
- 5 StockGroups, 3 StockCategories, 10 StockItems (mapped to first 5 Products)
- 10 Vouchers (Sales ×2, Purchase ×2, Receipt, Payment, Credit Note, Debit Note, Contra, Journal) with inventory and ledger entries
- **ISP / Lease Line dataset** (mirrors Postman test session): BlueStar Technologies ledger + Client, Sales - Lease Line ledger, Network Equipment stock group, Lease Line Services stock category, 30Mbps Lease Line stock item + Product, 2 Sales vouchers (INV/001 + INV/002) with inventory entries, ledger entries, auto-mapped Invoices, and InvoiceItem rows linked to the Product
- 5 Report snapshots (trial balance, P&L, balance sheet, sales register, purchase register)
- 8 StatutoryMasters (GST ×3, TDS ×2, PF ×1, ESI ×1, PT ×1)
- 5 EmployeeGroups (Management, Sales, Engineering, Operations, Finance)
- 10 PayHeads (Basic Salary, HRA, Travel Allowance, PF Employee, PF Employer, ESI Employee, ESI Employer, Professional Tax, LTA, Performance Bonus)
- 7 AttendanceTypes (Present, Casual Leave, Sick Leave, Earned Leave, Leave Without Pay, Holiday, Weekly Off)
- 5 Employees (Arjun Mehta, Sunita Sharma, Rakesh Gupta, Pooja Nair, Vikram Singh) with full PF/UAN/ESI/bank details
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
| `TallySyncHelpers` | `app/Services/Tally/` | PHP trait — shared helpers used by both sync services: `startLog()`, `completeLog()` (with null-guard `?? 0` on counters), `failLog()`, `strip()`, `parseDate()`. |
| `TallyInboundSync` | `app/Services/Tally/` | Inbound upsert for ledgers, stock items, vouchers, statutory masters. Uses `TallySyncHelpers`. Runs auto-mapping after each upsert. |
| `TallyPayrollSync` | `app/Services/Tally/` | Inbound upsert for payroll entities: `syncEmployeeGroups()`, `syncEmployees()` (supports `full_sync`), `syncPayHeads()`, `syncAttendanceTypes()`. Uses `TallySyncHelpers`. |
| `TallyReportSync` | `app/Services/Tally/` | Report snapshot inserts. 1 public method. |
| `TallyOutboundFormatter` | `app/Services/Tally/` | Formats Tally mirror records into Tally's exact payload structure. 20 public methods covering all masters, payroll (incl. salary + attendance vouchers), and voucher types. |

### API Controllers

| Class | Responsibility |
|-------|---------------|
| `TallyBaseController` | Resolves `TallyConnection` from Bearer token. Stamps `inbound_token_last_used_at`. Base class for all Tally API controllers. |
| `TallyInboundMastersController` | 6 methods: ledgerGroups, ledgers, stockItems, stockGroups, stockCategories, statutory. |
| `TallyInboundPayrollController` | 4 methods: employeeGroups, employees, payHeads, attendanceTypes. Injects `TallyPayrollSync`. |
| `TallyInboundVouchersController` | 8 methods: sales, creditNote, purchase, debitNote, receipt, payment, contra, journal. All delegate to `TallyInboundSync::syncVouchers()`. |
| `TallyInboundReportsController` | 4 methods: balanceSheet, profitLoss, cashFlow, ratioAnalysis. |
| `TallyOutboundController` | 20 methods (6 masters + 6 payroll + 8 voucher types). Returns only `pending` queue entries via `TallyOutboundQueueService`. |
| `TallyConfirmController` | 23 methods. Updates `tally_id` and marks queue entry `confirmed`. Reads `{ "Data": [{ "Id", "TallyId", "IsSynced" }] }`. |

### Web Controllers

| Class | Responsibility |
|-------|---------------|
| `TallyConnectionController` | show(), save(), regenerateToken(), destroy(), testConnection(). Manages the `tally_connections` row for a tenant. |
| `TallySyncController` | index() (builds Sync.vue props: latest logs per entity, all logs, report snapshots, stats including statutory_masters + employees counts), trigger() (logs a manual trigger entry). |
| `TallyDataController` | Data browse: ledgerGroups(), ledgers(), stockItems(), vouchers(), voucherShow(), statutoryMasters(), payroll(). Also passes `ledgerGroupNames`, `stockGroupNames`, `stockCategoryNames`, `ledgerNames`, `stockItemNames` for form autocomplete. |
| `TallyMasterCrudController` | 30 methods. store/update/destroy for: LedgerGroup, Ledger, StockGroup, StockCategory, StockItem, StatutoryMaster, EmployeeGroup, Employee, PayHead, AttendanceType. |
| `TallyVoucherCrudController` | 3 methods: voucherStore, voucherUpdate, voucherDestroy. Child entries (ledger + inventory) delete-reinserted in transaction on every update. |

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
  2026_04_19_000013_create_tally_statutory_masters_table.php
  2026_04_19_000014_create_tally_employee_groups_table.php
  2026_04_19_000015_create_tally_pay_heads_table.php
  2026_04_19_000016_create_tally_attendance_types_table.php
  2026_04_19_000017_create_tally_employees_table.php
  2026_04_21_121802_create_tally_inbound_logs_table.php

app/Models/
  TallyConnection.php           — per-tenant auth token, auto-generates on creating()
  TallyLedgerGroup.php
  TallyLedger.php               — mapped_client_id / mapped_vendor_id FKs
  TallyStockGroup.php
  TallyStockCategory.php
  TallyStockItem.php            — mapped_product_id FK
  TallyVoucher.php              — mapped_invoice_id FK; has inventoryEntries / ledgerEntries relations
  TallyVoucherInventoryEntry.php
  TallyVoucherLedgerEntry.php
  TallyReport.php
  TallySyncLog.php
  TallyInboundLog.php            — raw JSON of every inbound POST, stored before processing
  TallyStatutoryMaster.php      — details cast to array, applicable_from cast to date
  TallyEmployeeGroup.php
  TallyPayHead.php              — rate cast to float
  TallyAttendanceType.php
  TallyEmployee.php             — addresses/salary_details cast to array; date fields cast to date
  Client.php                    — added tally_ledger_id FK + tallyLedger() relation
  Vendor.php                    — added tally_ledger_id FK + tallyLedger() relation
  Product.php                   — added tally_stock_item_id FK + tallyStockItem() relation
  Invoice.php                   — added tally_voucher_id FK + tallyVoucher() relation

app/Services/Tally/
  TallySyncHelpers.php          — PHP trait: startLog, completeLog, failLog, strip, parseDate
  TallyInboundSync.php          — uses TallySyncHelpers
  TallyPayrollSync.php          — uses TallySyncHelpers; handles all payroll entities
  TallyReportSync.php
  TallyOutboundFormatter.php

app/Http/Controllers/
  TallyConnectionController.php
  TallySyncController.php
  TallyDataController.php
  Api/Tally/
    TallyBaseController.php
    TallyInboundMastersController.php
    TallyInboundPayrollController.php
    TallyInboundVouchersController.php
    TallyInboundReportsController.php
    TallyOutboundController.php
    TallyConfirmController.php

resources/js/Pages/Tally/
  Connection.vue
  Sync.vue
  LedgerGroups.vue
  Ledgers.vue
  StockItems.vue
  Vouchers.vue
  VoucherShow.vue
  StatutoryMasters.vue
  Payroll.vue

database/seeders/
  TallySeeder.php
```

---

## 18. Known Limitations & Pending Design Decisions

---

### L1 — Accobot edits to Tally-received data do NOT reach Tally

This is the most important limitation to understand. When Tally pushes data into Accobot, the data is stored in `tally_*` mirror tables. If you then edit that data inside Accobot, Tally has no way to learn about the change.

There are two distinct cases depending on the table type:

#### Case A — Pure mirror tables (no operational counterpart)

These tables have no separate Accobot operational table. The outbound GET reads directly from them, so an edit to the mirror row WILL appear in the next outbound response.

Affected tables:

| Table | Outbound GET endpoint |
|-------|-----------------------|
| `tally_ledger_groups` | `GET /api/MastersAPI/ledger-group` |
| `tally_stock_groups` | `GET /api/MastersAPI/stock-group` |
| `tally_stock_categories` | `GET /api/MastersAPI/stock-category` |
| `tally_statutory_masters` | `GET /api/MastersAPI/statutory-master` |
| `tally_employee_groups` | `GET /api/PayrollAPI/employee-group` |
| `tally_employees` | `GET /api/PayrollAPI/employee` |
| `tally_pay_heads` | `GET /api/PayrollAPI/pay-head` |
| `tally_attendance_types` | `GET /api/PayrollAPI/attendance-type` |

Additionally, **there is currently no edit UI** for any of these entities in Accobot. The browse pages (LedgerGroups.vue, Payroll.vue, StatutoryMasters.vue, etc.) are read-only. Any edit would need to be done directly in the database.

**Fix required:** Add an `accobot_alter_id` integer column to each of these tables. Increment it on every Accobot-side save. The outbound formatter should expose `accobot_alter_id` as the `AlterID` field (falling back to `alter_id` when `accobot_alter_id` is zero) so the connector detects the change. Edit UI also needs to be built.

#### Case B — Split tables (mirror + operational counterpart)

These tables have a paired Accobot operational table. When Tally data arrives inbound, both the mirror row and the operational row are written. But if you then edit the **operational** row in Accobot (e.g. rename a Client, change a Product's price), only that operational table is updated — the mirror row in `tally_*` is completely untouched.

Since the outbound GET reads from the mirror table (not the operational table), the change never appears in the outbound response at all — Tally sees nothing.

| Accobot edit | Mirror table (not updated) | Outbound GET (returns stale data) |
|---|---|---|
| Edit a `Client` | `tally_ledgers` | `GET /api/MastersAPI/ledger-master` |
| Edit a `Vendor` | `tally_ledgers` | `GET /api/MastersAPI/ledger-master` |
| Edit a `Product` | `tally_stock_items` | `GET /api/MastersAPI/stock-master` |
| Edit an `Invoice` | `tally_vouchers` | `GET /api/VoucherAPI/sales-voucher` |

**Fix required:** Model observers on `Client`, `Vendor`, `Product`, `Invoice`. When a model that has a `tally_ledger_id` / `tally_stock_item_id` / `tally_voucher_id` FK is updated, the observer writes the changed fields back into the mirror row and increments `accobot_alter_id`. Only the subset of fields that exist in both tables can be synced back (e.g. name, email, phone, GSTIN for a Client — not GST rate details, bank info, or other Tally-only fields).

---

### L2 — Deletions in Accobot are NOT propagated to Tally

Deleting or deactivating any record in Accobot (Client, Vendor, Product, Invoice, or any `tally_*` row directly) sends no signal to Tally. The record continues to exist in Tally unchanged. The only way to delete something in Tally is to do it in Tally itself, which will then send `"Action": "Delete"` inbound and Accobot will set `is_active = false`.

---

### L3 — Accobot-created records are NOT sent to Tally

If a record is created directly in Accobot (a new Client, Product, or Invoice that never came from Tally), it has no row in the `tally_*` mirror tables. The outbound GET endpoints only query mirror tables, so these records are never returned to the connector and Tally never learns about them.

**Pending design decisions before this can be implemented:**

1. **New Client / Vendor (no `tally_ledger_id`)** — Should Accobot auto-create a stub `tally_ledgers` row so the outbound GET picks it up? Or should the user manually map the Client to a Tally ledger first? Or block the record entirely until it's mapped?

2. **New Product (no `tally_stock_item_id`)** — Same question for `tally_stock_items`.

3. **New Invoice (no `tally_voucher_id`)** — An invoice created in Accobot needs its client and all line-item products to already be in Tally before a meaningful `tally_vouchers` row can be constructed. What happens if they aren't?

4. **Invoice line items** — `autoMapSalesVoucher()` only creates the Invoice header today; `InvoiceItem` rows from `tally_voucher_inventory_entries` are not mapped. This would need to be addressed as part of any Accobot→Tally invoice flow.

---

### L4 — No queue; large payloads may time out

Inbound sync is synchronous — the connector waits for the HTTP response. There is no queue or background processing. The connector should send a maximum of ~500 records per request to avoid hitting PHP's execution time limit. Very large companies (thousands of ledgers or vouchers) should send data in batches.

---

### L5 — "Sync Now" button is a no-op

Because Accobot cannot initiate contact with Tally (the connector always calls Accobot, never the other way around), the "Sync Now" button on the Sync page does not transfer any data. It only logs a `manual_trigger` entry and shows a reminder message. To trigger a sync, the Tally connector must be running and will push data on its own configured schedule.

---

### L6 — Auto-mapping quality limitations

**`tax_amount` is always `0` on auto-mapped invoices.** The GST breakdown (IGST/SGST/CGST lines) is stored in `tally_voucher_ledger_entries` but is not summed into `invoices.tax_amount` during `autoMapSalesVoucher()`.

**`due_date` defaults to `voucher_date`.** Tally ledgers carry a `CreditPeriod` field but it is not applied when computing the invoice due date during auto-mapping.

**Invoice status is always `unpaid` after auto-mapping.** No cross-check is done against Receipt vouchers that may have already settled the sales voucher in Tally.

**Placeholder claim for Products is name-match only.** When `autoMapStockItem()` claims a placeholder Product, it matches on `name` alone. If two products share the same name, the wrong placeholder could be claimed. Client placeholder claim now uses GSTIN/PAN first (more reliable), falling back to name.

---

### ~~L7 — Real Tally connector field name variants~~ ✅ Fixed

The real Tally connector uses different field name conventions from the simplified examples in `tally-api-reference.md`. `TallyInboundSync.php` now accepts both variants for full compatibility:

| Real connector field | Alternative accepted |
|---|---|
| `TallyId` | `ID` / `Id` |
| `UnderId` | `UnderID` |
| `Group` (ledger) | `GroupName` |
| `GSTIN_Number` | `GSTINNumber` |
| `PAN_Number` | `PANNumber` |
| `Mobile_Number` | `MobileNumber` |
| `ContactPerson_Email` | `ContactPersonEmail` |
| `GST_Type` | `GSTType` |
| `Opening_Balance` | `OpeningBalance` |
| `Opening_Balance_Type` | `OpeningBalanceType` |
| `LedgerAddress` | `Addresses` |
| `IGST_Rate` | `IGSTRate` |
| `SGST_Rate` | `SGSTRate` |
| `CGST_Rate` | `CGSTRate` |
| `CESS_Rate` | `CessRate` |
| `Taxablity` (Tally typo) | `Taxability` |
| `Category` (stock item) | `CategoryName` |
| `Unit` (stock item) | `UnitName` |
| `Closing_Balance` | `ClosingBalance` |
| `Voucher_Total` | `VoucherTotal` |
| `ledgerentries` (lowercase) | `LedgerEntries` |
| `BuyerCountryName` | `BuyerCountry` |
| `ConsigneeCountryName` | `ConsigneeCountry` |
| `VoucherCostCentre` | `CostCentre` |
| `Cess_Rate` (ledger entry) | `CessRate` |
| `"Applicable"` for IsGSTApplicable | `"Yes"` |
