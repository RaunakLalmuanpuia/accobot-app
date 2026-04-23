# Tally Outbound API — Reference

Documents the outbound sync system: how Accobot queues changes for the Tally connector to pick up, and how the connector confirms delivery.

---

## Overview

Accobot uses a **flag-based outbound queue** rather than webhooks. The Tally connector polls Accobot at a regular interval, fetches any pending records, writes them into Tally, and then posts a confirmation back.

```
Accobot change → tally_outbound_queue (pending)
                        ↓ connector polls
                 GET /api/...  →  { Data: [...] }
                        ↓ connector processes in Tally
                 POST /api/.../update-...  →  { status: "ok" }
                        ↓
                 tally_outbound_queue (confirmed)
```

---

## Queue mechanics

### What triggers queuing

A record enters the queue any time a model is **created** or **updated** in Accobot:

| Source model | Entity queued |
|---|---|
| `TallyLedgerGroup` | itself |
| `TallyLedger` | itself |
| `TallyStockGroup` | itself |
| `TallyStockCategory` | itself |
| `TallyStockItem` | itself |
| `TallyStatutoryMaster` | itself |
| `TallyEmployeeGroup` | itself |
| `TallyEmployee` | itself |
| `TallyPayHead` | itself |
| `TallyAttendanceType` | itself |
| `TallyVoucher` | itself |
| `Client` | its linked `TallyLedger` (creates stub if none) |
| `Vendor` | its linked `TallyLedger` (creates stub if none) |
| `Product` | its linked `TallyStockItem` (creates stub if none) |
| `Invoice` | its linked `TallyVoucher` (creates stub if none) |

Inbound syncs (Tally → Accobot) are never queued for outbound. The `TallyInboundSync::$syncing` flag prevents re-queuing during inbound processing.

### Action field values

| Value | Meaning |
|---|---|
| `Create` | Record is new — Tally does not have it yet (`tally_id` is null) |
| `Update` | Record exists in Tally (`tally_id` is set) and has changed |
| `Delete` | Record was deactivated in Accobot — Tally should delete it |

The `Action` field is computed automatically by the observer. A deactivated record (`is_active = false`) with a `tally_id` produces `Action: Delete`; without a `tally_id` it is never queued (it never reached Tally).

### Queue states

| Status | Meaning |
|---|---|
| `pending` | Change detected, not yet confirmed by connector |
| `confirmed` | Connector has written the record into Tally |

Re-editing a `confirmed` record resets its status to `pending` and re-queues it.

---

## Authentication

All outbound and confirmation endpoints use the same Bearer token as inbound endpoints.

```
Authorization: Bearer {tally_token}
```

The token identifies the tenant. There is no `companyId` in any outbound route.

---

## Outbound GET Endpoints

The connector calls these to fetch pending records. Each returns only the records for the authenticated tenant that are currently `pending` in the queue. Returns `{ "Data": [] }` when nothing is pending.

### Masters

#### GET `/api/MastersAPI/ledger-group`

```json
{
  "Data": [
    {
      "AccobotId": 7,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Sundry Debtors",
      "UnderID": null,
      "UnderName": "Current Assets",
      "NatureOfGroup": "Assets"
    }
  ]
}
```

#### GET `/api/MastersAPI/ledger-master`

```json
{
  "Data": [
    {
      "AccobotId": 42,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "LedgerName": "Acme Pvt Ltd",
      "GroupName": "Sundry Debtors",
      "ParentGroup": "Current Assets",
      "IsBillWiseOn": "Yes",
      "InventoryAffected": "No",
      "GSTINNumber": "27AABCT1234A1Z5",
      "PANNumber": "AABCT1234A",
      "GSTType": "Regular",
      "MailingName": "Acme Pvt Ltd",
      "MobileNumber": "9876543210",
      "ContactPerson": "Raj Kumar",
      "ContactPersonEmail": "raj@acme.com",
      "ContactPersonMobile": "9876543210",
      "Addresses": [],
      "StateName": "Maharashtra",
      "CountryName": "India",
      "PinCode": "400001",
      "CreditPeriod": 30,
      "CreditLimit": 500000,
      "OpeningBalance": 0,
      "OpeningBalanceType": null,
      "Aliases": [],
      "Description": null,
      "Notes": null
    }
  ]
}
```

Field notes:
- `ID` — Tally's master ID. `null` for new records (`Action: Create`); set for updates.
- `AccobotId` — Accobot's primary key. The connector must echo this in the confirmation.
- `Action: Delete` — connector should delete the ledger in Tally; `ID` will be set.

#### GET `/api/MastersAPI/stock-group`

```json
{
  "Data": [
    {
      "AccobotId": 3,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Electronics",
      "Parent": "Primary",
      "Aliases": []
    }
  ]
}
```

#### GET `/api/MastersAPI/stock-category`

```json
{
  "Data": [
    {
      "AccobotId": 5,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Laptops",
      "Parent": "Primary",
      "Aliases": []
    }
  ]
}
```

#### GET `/api/MastersAPI/stock-master`

```json
{
  "Data": [
    {
      "AccobotId": 18,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Dell Laptop 15",
      "Description": "15-inch business laptop",
      "Remarks": null,
      "Aliases": [],
      "StockGroupID": null,
      "StockGroupName": "Electronics",
      "StockCategoryID": null,
      "Category": "Laptops",
      "UnitID": null,
      "Unit": "NOS",
      "AlternateUnit": null,
      "Conversion": null,
      "Denominator": null,
      "IsGSTApplicable": "Yes",
      "Taxablity": "Taxable",
      "CalculationType": null,
      "IGST_Rate": 18,
      "SGST_Rate": 9,
      "CGST_Rate": 9,
      "CESS_Rate": 0,
      "HSNCode": "8471",
      "MRPRate": null,
      "Opening_Balance": null,
      "Opening_Rate": null,
      "Opening_Value": null,
      "Closing_Balance": null,
      "Closing_Rate": null,
      "Closing_Value": null
    }
  ]
}
```

#### GET `/api/MastersAPI/statutory-master`

```json
{
  "Data": [
    {
      "AccobotId": 2,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "GST - Maharashtra",
      "StatutoryType": "GST",
      "RegistrationNumber": "27AABCT1234A1Z5",
      "StateCode": "27",
      "RegistrationType": "Regular",
      "PAN": "AABCT1234A",
      "TAN": null,
      "ApplicableFrom": "2017-07-01",
      "Details": {}
    }
  ]
}
```

---

### Payroll

#### GET `/api/PayrollAPI/employee-group`

```json
{
  "Data": [
    {
      "AccobotId": 4,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Management",
      "Under": null,
      "CostCentreCategory": null
    }
  ]
}
```

#### GET `/api/PayrollAPI/employee`

```json
{
  "Data": [
    {
      "AccobotId": 11,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Raj Kumar",
      "EmployeeNumber": "EMP001",
      "Parent": "Management",
      "Designation": "Manager",
      "Function": "Finance",
      "Location": "Mumbai",
      "JoiningDate": "2022-04-01",
      "ResignationDate": null,
      "DOB": "1985-06-15",
      "Gender": "Male",
      "FatherName": "Suresh Kumar",
      "SpouseName": null,
      "Aliases": []
    }
  ]
}
```

#### GET `/api/PayrollAPI/pay-head`

```json
{
  "Data": [
    {
      "AccobotId": 6,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Basic Salary",
      "PayType": "Earning",
      "IncomeType": "Fixed",
      "ParentGroup": "Indirect Expenses",
      "CalculationType": "As Computed Value",
      "LeaveType": null,
      "CalculationPeriod": "Monthly"
    }
  ]
}
```

#### GET `/api/PayrollAPI/attendance-type`

```json
{
  "Data": [
    {
      "AccobotId": 2,
      "ID": null,
      "AlterID": null,
      "Action": "Create",
      "Name": "Present",
      "AttendanceType": "Attendance",
      "UnitOfMeasure": "Days"
    }
  ]
}
```

---

### Vouchers

All voucher GET endpoints return the same structure. The `VoucherType` field distinguishes them.

#### GET `/api/VoucherAPI/sales-voucher`

```json
{
  "Data": [
    {
      "AccobotId": 101,
      "MasterID": null,
      "AlterID": null,
      "Action": "Create",
      "VoucherType": "Sales",
      "VoucherNumber": "INV/2024-25/001",
      "VoucherDate": "2024-04-05",
      "Reference": null,
      "PartyName": "Acme Pvt Ltd",
      "VoucherTotal": 35964.04,
      "IsInvoice": "Yes",
      "PlaceOfSupply": "Maharashtra",
      "BuyerName": "Acme Pvt Ltd",
      "BuyerGSTIN": "27AABCT1234A1Z5",
      "BuyerState": "Maharashtra",
      "BuyerAddress": null,
      "BuyerEmail": "raj@acme.com",
      "BuyerMobile": "9876543210",
      "Narration": null,
      "IRN": null,
      "AcknowledgementNo": null,
      "AcknowledgementDate": null,
      "QRCode": null,
      "InventoryEntries": [
        {
          "StockItemName": "Dell Laptop 15",
          "HSNCode": "8471",
          "Unit": "NOS",
          "IGSTRate": 18,
          "ActualQty": 2,
          "BilledQty": 2,
          "Rate": 15000,
          "DiscountPercent": 0,
          "Amount": 30000,
          "TaxAmount": 5400
        }
      ],
      "LedgerEntries": [
        {
          "LedgerName": "Acme Pvt Ltd",
          "LedgerGroup": "Sundry Debtors",
          "LedgerAmount": 35400,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "BillsAllocation": []
        },
        {
          "LedgerName": "IGST Output",
          "LedgerGroup": "Duties & Taxes",
          "LedgerAmount": 5400,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "BillsAllocation": []
        }
      ]
    }
  ]
}
```

Other voucher endpoints (`purchase-voucher`, `debitNote-voucher`, `creditNote-voucher`, `receipt-voucher`, `payment-voucher`, `contra-voucher`, `journal-voucher`) return the same structure with their respective `VoucherType` values.

Field notes:
- `MasterID` — Tally's master ID. `null` for new records; set for updates/deletes.
- `Action: Delete` — connector should void/delete the voucher in Tally.

---

## Confirmation POST Endpoints

After the connector writes a record into Tally, it posts back with the assigned Tally ID. Accobot stores this and marks the queue entry as confirmed.

**Required body fields per item:**

| Field | Type | Description |
|---|---|---|
| `AccobotId` | integer | Accobot's primary key (from the GET response) |
| `TallyId` | integer | The ID Tally assigned after creating/updating the record |
| `IsSynced` | boolean | `true` = propagate `tally_synced_at` to mapped Client/Vendor/Product/Invoice |

### Masters

#### POST `/api/MastersAPI/update-ledger-group`

```json
{
  "Data": [
    { "AccobotId": 7, "TallyId": 4001, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

#### POST `/api/MastersAPI/update-ledger-master`

```json
{
  "Data": [
    { "AccobotId": 42, "TallyId": 5501, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

#### POST `/api/MastersAPI/update-stock-group`

```json
{
  "Data": [
    { "AccobotId": 3, "TallyId": 4599, "IsSynced": true }
  ]
}
```

#### POST `/api/MastersAPI/update-stock-category`

```json
{
  "Data": [
    { "AccobotId": 5, "TallyId": 4591, "IsSynced": true }
  ]
}
```

#### POST `/api/MastersAPI/update-stock-master`

```json
{
  "Data": [
    { "AccobotId": 18, "TallyId": 241, "IsSynced": true }
  ]
}
```

#### POST `/api/MastersAPI/update-statutory-master`

```json
{
  "Data": [
    { "AccobotId": 2, "TallyId": 301, "IsSynced": false }
  ]
}
```

---

### Payroll

#### POST `/api/PayrollAPI/update-employee-group`

```json
{
  "Data": [
    { "AccobotId": 4, "TallyId": 401, "IsSynced": true }
  ]
}
```

#### POST `/api/PayrollAPI/update-employee`

```json
{
  "Data": [
    { "AccobotId": 11, "TallyId": 4588, "IsSynced": true }
  ]
}
```

#### POST `/api/PayrollAPI/update-pay-head`

```json
{
  "Data": [
    { "AccobotId": 6, "TallyId": 601, "IsSynced": true }
  ]
}
```

#### POST `/api/PayrollAPI/update-attendance-type`

```json
{
  "Data": [
    { "AccobotId": 2, "TallyId": 701, "IsSynced": true }
  ]
}
```

---

### Vouchers

#### POST `/api/VoucherAPI/update-sales-voucher`

```json
{
  "Data": [
    { "AccobotId": 101, "TallyId": 5001, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

Other voucher confirmation endpoints follow the same pattern:

| Endpoint | Example `TallyId` |
|---|---|
| POST `/api/VoucherAPI/update-purchase-voucher` | 6001 |
| POST `/api/VoucherAPI/update-debitnote-voucher` | 8001 |
| POST `/api/VoucherAPI/update-creditnote-voucher` | 7001 |
| POST `/api/VoucherAPI/update-receipt-voucher` | 9001 |
| POST `/api/VoucherAPI/update-payment-voucher` | 10001 |
| POST `/api/VoucherAPI/update-contra-voucher` | 11001 |
| POST `/api/VoucherAPI/update-journal-voucher` | 12001 |

---

## Batch confirmations

Multiple records can be confirmed in a single request:

```json
{
  "Data": [
    { "AccobotId": 42, "TallyId": 5501, "IsSynced": true },
    { "AccobotId": 43, "TallyId": 5502, "IsSynced": true },
    { "AccobotId": 44, "TallyId": 5503, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 3 }
```

Items with a missing or unrecognised `AccobotId` are silently skipped (not counted in `updated`).

---

## Delete flow

When a record is deactivated in Accobot:

1. Observer sets `action = 'Delete'` on the Tally model and queues it.
2. The GET endpoint serves it with `Action: "Delete"` and the Tally ID set.
3. Connector deletes/voids the record in Tally.
4. Connector posts confirmation as usual.

```json
{
  "Data": [
    {
      "AccobotId": 42,
      "ID": 5501,
      "Action": "Delete",
      "LedgerName": "Acme Pvt Ltd",
      ...
    }
  ]
}
```

Confirmation:
```json
{
  "Data": [
    { "AccobotId": 42, "TallyId": 5501, "IsSynced": false }
  ]
}
```

---

## Error cases

| Situation | Behaviour |
|---|---|
| `AccobotId` missing or not found | Item skipped, not counted |
| `TallyId` missing | Item skipped, not counted |
| Token invalid / tenant mismatch | `401 Unauthorized` |
| Nothing pending | `{ "Data": [] }` — not an error |
