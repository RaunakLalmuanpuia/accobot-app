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

## Masters API

### GET `/api/MastersAPI/ledger-group`

Returns pending ledger groups for the tenant.

**Response:**
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

**Confirmation — POST `/api/MastersAPI/update-ledger-group`**

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

---

### GET `/api/MastersAPI/ledger-master`

Returns pending ledgers for the tenant.

**Response:**
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
      "Notes": null,
      "BankDetails": []
    }
  ]
}
```

Field notes:
- `ID` — Tally's master ID. `null` for new records (`Action: Create`); set for updates/deletes.
- `AccobotId` — Accobot's primary key. The connector must echo this in the confirmation.
- `BankDetails` — array of bank account objects if populated; `[]` when none.
- `Action: Delete` — connector should delete the ledger in Tally; `ID` will be set.

**Confirmation — POST `/api/MastersAPI/update-ledger-master`**

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

`IsSynced: true` causes Accobot to stamp `tally_synced_at` on the mapped Client or Vendor record.

---

### GET `/api/MastersAPI/stock-group`

**Response:**
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

**Confirmation — POST `/api/MastersAPI/update-stock-group`**

```json
{
  "Data": [
    { "AccobotId": 3, "TallyId": 4599, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/MastersAPI/stock-category`

**Response:**
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

**Confirmation — POST `/api/MastersAPI/update-stock-category`**

```json
{
  "Data": [
    { "AccobotId": 5, "TallyId": 4591, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/MastersAPI/stock-master`

**Response:**
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

`IsSynced: true` stamps `tally_synced_at` on the mapped Product record.

**Confirmation — POST `/api/MastersAPI/update-stock-master`**

```json
{
  "Data": [
    { "AccobotId": 18, "TallyId": 241, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/MastersAPI/statutory-master`

**Response:**
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

**Confirmation — POST `/api/MastersAPI/update-statutory-master`**

```json
{
  "Data": [
    { "AccobotId": 2, "TallyId": 301, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/MastersAPI/company-master`

Returns pending company records for the tenant (queued when the user edits company details in Accobot).

**Response:**
```json
{
  "Data": [
    {
      "AccobotId": 1,
      "ID": null,
      "Action": "Create",
      "Guid": "644e52fa-2de6-4bf6-aabd-e2a3533780a7",
      "CompanyName": "Aignite",
      "Address": "Add1",
      "State": "Dubai",
      "Country": "UAE",
      "TallySerialNo": "775580148",
      "TallyLicenseType": "Gold"
    }
  ]
}
```

Field notes:
- `ID` — Tally's assigned ID. `null` for new records; set for updates.
- `Guid` — the company GUID from Tally, used as the unique identifier.

**Confirmation — POST `/api/MastersAPI/update-company-master`**

```json
{
  "Data": [
    { "AccobotId": 1, "TallyId": 1001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

## Payroll API

### GET `/api/PayrollAPI/employee-group`

**Response:**
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

**Confirmation — POST `/api/PayrollAPI/update-employee-group`**

```json
{
  "Data": [
    { "AccobotId": 4, "TallyId": 401, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/PayrollAPI/employee`

**Response:**
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
      "Aliases": [
        { "Alias": "RK" }
      ]
    }
  ]
}
```

`Aliases` is an array of objects `{ "Alias": "..." }`, not a flat string array.

**Confirmation — POST `/api/PayrollAPI/update-employee`**

```json
{
  "Data": [
    { "AccobotId": 11, "TallyId": 4588, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/PayrollAPI/pay-head`

**Response:**
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

**Confirmation — POST `/api/PayrollAPI/update-pay-head`**

```json
{
  "Data": [
    { "AccobotId": 6, "TallyId": 601, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/PayrollAPI/attendance-type`

**Response:**
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

**Confirmation — POST `/api/PayrollAPI/update-attendance-type`**

```json
{
  "Data": [
    { "AccobotId": 2, "TallyId": 701, "IsSynced": true }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/PayrollAPI/salary-voucher`

Returns pending salary (payroll) vouchers with per-employee pay head breakdowns.

**Response:**
```json
{
  "Data": [
    {
      "AccobotId": 50,
      "MasterID": null,
      "AlterID": null,
      "Action": "Create",
      "VoucherType": "Payroll",
      "VoucherNumber": "PAY/2024-25/001",
      "VoucherDate": "2024-04-30",
      "Narration": "April 2024 salary",
      "EmployeeAllocations": [
        {
          "EmployeeName": "Raj Kumar",
          "EmployeeGroup": "Management",
          "PayHeadEntries": [
            { "PayHead": "Basic Salary", "Amount": 30000 },
            { "PayHead": "HRA", "Amount": 10000 }
          ],
          "NetPayable": 40000
        }
      ]
    }
  ]
}
```

Field notes:
- `MasterID` — Tally's voucher master ID. `null` for new records; set for updates/deletes.
- `PayHeadEntries` — stored as-is from inbound sync; structure matches the Tally payroll spec.

**Confirmation — POST `/api/PayrollAPI/update-salary-voucher`**

```json
{
  "Data": [
    { "AccobotId": 50, "TallyId": 8001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/PayrollAPI/attendance-voucher`

Returns pending attendance vouchers with per-employee attendance breakdowns.

**Response:**
```json
{
  "Data": [
    {
      "AccobotId": 51,
      "MasterID": null,
      "AlterID": null,
      "Action": "Create",
      "VoucherType": "Attendance",
      "VoucherNumber": "ATT/2024-25/001",
      "VoucherDate": "2024-04-30",
      "Narration": "April 2024 attendance",
      "EmployeeAllocations": [
        {
          "EmployeeName": "Raj Kumar",
          "EmployeeGroup": "Management",
          "AttendanceEntries": [
            { "AttendanceType": "Present", "AttendanceValue": 26 },
            { "AttendanceType": "Casual Leave", "AttendanceValue": 2 }
          ]
        }
      ]
    }
  ]
}
```

`AttendanceEntries` — stored as-is from inbound sync; structure matches the Tally attendance spec. No `NetPayable` on attendance vouchers.

**Confirmation — POST `/api/PayrollAPI/update-attendance-voucher`**

```json
{
  "Data": [
    { "AccobotId": 51, "TallyId": 9001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

## Voucher API

All voucher GET endpoints share the same field structure. `VoucherType` distinguishes them. `InventoryEntries` is always present — it is `[]` for non-inventory vouchers (Receipt, Payment, Contra, Journal).

### Full field reference

The sales voucher below shows every possible field. Other voucher types carry the same fields with their respective `VoucherType` values.

### GET `/api/VoucherAPI/sales-voucher`

**Response:**
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
      "ReferenceDate": null,
      "PartyName": "Acme Pvt Ltd",
      "VoucherTotal": 35400,
      "IsInvoice": "Yes",
      "PlaceOfSupply": "Maharashtra",
      "VoucherCostCentre": null,

      "DeliveryNoteNo": null,
      "DeliveryNoteDate": null,
      "DispatchDocNo": null,
      "DispatchThrough": null,
      "Destination": null,
      "CarrierName": null,
      "LRNo": null,
      "LRDate": null,
      "MotorVehicleNo": null,

      "OrderNo": null,
      "OrderDate": null,
      "TermsOfPayment": null,
      "OtherReferences": null,
      "TermsOfDelivery": null,

      "BuyerName": "Acme Pvt Ltd",
      "BuyerAlias": null,
      "BuyerGSTIN": "27AABCT1234A1Z5",
      "BuyerPinCode": "400001",
      "BuyerState": "Maharashtra",
      "BuyerCountryName": "India",
      "BuyerGSTRegistrationType": "Regular",
      "BuyerEmail": "raj@acme.com",
      "BuyerMobile": "9876543210",
      "BuyerAddress": [
        { "BuyerAddress": "123 MG Road, Mumbai" }
      ],

      "ConsigneeName": null,
      "ConsigneeGSTIN": null,
      "ConsigneeTallyGroup": null,
      "ConsigneePinCode": null,
      "ConsigneeState": null,
      "ConsigneeCountryName": null,
      "ConsigneeGSTRegistrationType": null,

      "Narration": null,
      "IRN": null,
      "AcknowledgementNo": null,
      "AcknowledgementDate": null,
      "QRCode": null,

      "InventoryEntries": [
        {
          "StockItemName": "Dell Laptop 15",
          "ItemCode": "DL15",
          "GroupName": "Electronics",
          "HSNCode": "8471",
          "Unit": "NOS",
          "IGSTRate": 18,
          "CessRate": 0,
          "IsDeemedPositive": "No",
          "ActualQty": 2,
          "BilledQty": 2,
          "Rate": 15000,
          "DiscountPercent": 0,
          "Amount": 30000,
          "TaxAmount": 5400,
          "MRP": null,
          "SalesLedger": "Sales Account",
          "GodownName": null,
          "BatchName": null
        }
      ],
      "LedgerEntries": [
        {
          "LedgerName": "Acme Pvt Ltd",
          "LedgerGroup": "Sundry Debtors",
          "LedgerAmount": 35400,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "IGSTRate": null,
          "HSNCode": null,
          "CessRate": null,
          "BillsAllocation": []
        },
        {
          "LedgerName": "IGST Output",
          "LedgerGroup": "Duties & Taxes",
          "LedgerAmount": 5400,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "IGSTRate": 18,
          "HSNCode": "8471",
          "CessRate": null,
          "BillsAllocation": []
        }
      ]
    }
  ]
}
```

Field notes:
- `MasterID` — Tally's voucher master ID. `null` for new records; set for updates/deletes.
- `BuyerAddress` — always an array of `{ "BuyerAddress": "..." }` objects, never a raw string.
- `IsDeemedPositive` / `IsPartyLedger` / `IsInvoice` — always `"Yes"` or `"No"` (string), never a boolean.
- `IGSTRate`, `HSNCode`, `CessRate` on `LedgerEntries` — populated only for tax ledgers; `null` for party ledgers.

**Confirmation — POST `/api/VoucherAPI/update-sales-voucher`**

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

`IsSynced: true` stamps `tally_synced_at` on the mapped Invoice record.

---

### GET `/api/VoucherAPI/purchase-voucher`

Same structure as sales-voucher. `VoucherType` is `"Purchase"`. `InventoryEntries` is populated for goods-received bills.

**Confirmation — POST `/api/VoucherAPI/update-purchase-voucher`**

```json
{
  "Data": [
    { "AccobotId": 102, "TallyId": 6001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/VoucherAPI/debitNote-voucher`

Same structure. `VoucherType` is `"Debit Note"`. `InventoryEntries` is populated for goods-return debit notes.

**Confirmation — POST `/api/VoucherAPI/update-debitnote-voucher`**

```json
{
  "Data": [
    { "AccobotId": 103, "TallyId": 8001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/VoucherAPI/creditNote-voucher`

Same structure. `VoucherType` is `"Credit Note"`. `InventoryEntries` is populated for goods-return credit notes.

**Confirmation — POST `/api/VoucherAPI/update-creditnote-voucher`**

```json
{
  "Data": [
    { "AccobotId": 104, "TallyId": 7001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/VoucherAPI/receipt-voucher`

Same structure. `VoucherType` is `"Receipt"`. `InventoryEntries` is always `[]`.

**Response (abbreviated — non-inventory fields only):**
```json
{
  "Data": [
    {
      "AccobotId": 200,
      "MasterID": null,
      "AlterID": null,
      "Action": "Create",
      "VoucherType": "Receipt",
      "VoucherNumber": "RCP/2024-25/001",
      "VoucherDate": "2024-04-10",
      "Reference": "INV/2024-25/001",
      "ReferenceDate": null,
      "PartyName": "Acme Pvt Ltd",
      "VoucherTotal": 35400,
      "IsInvoice": "No",
      "PlaceOfSupply": null,
      "VoucherCostCentre": null,
      "Narration": "Receipt against INV-001",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "Acme Pvt Ltd",
          "LedgerGroup": "Sundry Debtors",
          "LedgerAmount": 35400,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "IGSTRate": null,
          "HSNCode": null,
          "CessRate": null,
          "BillsAllocation": [
            { "BillName": "INV/2024-25/001", "BillAmount": 35400 }
          ]
        },
        {
          "LedgerName": "HDFC Bank",
          "LedgerGroup": "Bank Accounts",
          "LedgerAmount": 35400,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "IGSTRate": null,
          "HSNCode": null,
          "CessRate": null,
          "BillsAllocation": []
        }
      ]
    }
  ]
}
```

**Confirmation — POST `/api/VoucherAPI/update-receipt-voucher`**

```json
{
  "Data": [
    { "AccobotId": 200, "TallyId": 9001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/VoucherAPI/payment-voucher`

Same structure. `VoucherType` is `"Payment"`. `InventoryEntries` is always `[]`.

**Confirmation — POST `/api/VoucherAPI/update-payment-voucher`**

```json
{
  "Data": [
    { "AccobotId": 201, "TallyId": 10001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/VoucherAPI/contra-voucher`

Same structure. `VoucherType` is `"Contra"`. `InventoryEntries` is always `[]`. Used for bank-to-cash or cash-to-bank transfers.

**Confirmation — POST `/api/VoucherAPI/update-contra-voucher`**

```json
{
  "Data": [
    { "AccobotId": 202, "TallyId": 11001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

### GET `/api/VoucherAPI/journal-voucher`

Same structure. `VoucherType` is `"Journal"`. `InventoryEntries` is always `[]`.

**Confirmation — POST `/api/VoucherAPI/update-journal-voucher`**

```json
{
  "Data": [
    { "AccobotId": 203, "TallyId": 12001, "IsSynced": false }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

---

## Batch confirmations

Multiple records can be confirmed in a single request to any confirmation endpoint:

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

Example — delete a ledger:
```json
{
  "Data": [
    {
      "AccobotId": 42,
      "ID": 5501,
      "AlterID": null,
      "Action": "Delete",
      "LedgerName": "Acme Pvt Ltd",
      "GroupName": "Sundry Debtors",
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

## Endpoint summary

| GET endpoint | Confirmation POST |
|---|---|
| `/api/MastersAPI/ledger-group` | `/api/MastersAPI/update-ledger-group` |
| `/api/MastersAPI/ledger-master` | `/api/MastersAPI/update-ledger-master` |
| `/api/MastersAPI/stock-group` | `/api/MastersAPI/update-stock-group` |
| `/api/MastersAPI/stock-category` | `/api/MastersAPI/update-stock-category` |
| `/api/MastersAPI/stock-master` | `/api/MastersAPI/update-stock-master` |
| `/api/MastersAPI/statutory-master` | `/api/MastersAPI/update-statutory-master` |
| `/api/MastersAPI/company-master` | `/api/MastersAPI/update-company-master` |
| `/api/PayrollAPI/employee-group` | `/api/PayrollAPI/update-employee-group` |
| `/api/PayrollAPI/employee` | `/api/PayrollAPI/update-employee` |
| `/api/PayrollAPI/pay-head` | `/api/PayrollAPI/update-pay-head` |
| `/api/PayrollAPI/attendance-type` | `/api/PayrollAPI/update-attendance-type` |
| `/api/PayrollAPI/salary-voucher` | `/api/PayrollAPI/update-salary-voucher` |
| `/api/PayrollAPI/attendance-voucher` | `/api/PayrollAPI/update-attendance-voucher` |
| `/api/VoucherAPI/sales-voucher` | `/api/VoucherAPI/update-sales-voucher` |
| `/api/VoucherAPI/purchase-voucher` | `/api/VoucherAPI/update-purchase-voucher` |
| `/api/VoucherAPI/debitNote-voucher` | `/api/VoucherAPI/update-debitnote-voucher` |
| `/api/VoucherAPI/creditNote-voucher` | `/api/VoucherAPI/update-creditnote-voucher` |
| `/api/VoucherAPI/receipt-voucher` | `/api/VoucherAPI/update-receipt-voucher` |
| `/api/VoucherAPI/payment-voucher` | `/api/VoucherAPI/update-payment-voucher` |
| `/api/VoucherAPI/contra-voucher` | `/api/VoucherAPI/update-contra-voucher` |
| `/api/VoucherAPI/journal-voucher` | `/api/VoucherAPI/update-journal-voucher` |

---

## Error cases

| Situation | Behaviour |
|---|---|
| `AccobotId` missing or not found | Item skipped, not counted |
| `TallyId` missing | Item skipped, not counted |
| Token invalid / tenant mismatch | `401 Unauthorized` |
| Nothing pending | `{ "Data": [] }` — not an error |
