# Tally Voucher API — Unified Endpoints

Documents the three unified voucher endpoints covering both directions of sync between the Tally connector and Accobot.

> Payroll vouchers (Salary / Attendance) are handled separately under `/api/tally/inbound/payroll/` and `/api/PayrollAPI/`. Not covered here.

---

## Endpoints

| Direction | Method | Path | Purpose |
|---|---|---|---|
| Inbound | POST | `/api/tally/inbound/vouchers` | Connector pushes vouchers into Accobot |
| Outbound | GET | `/api/VoucherAPI/voucher` | Connector fetches all pending vouchers from Accobot |
| Confirm | POST | `/api/VoucherAPI/update-voucher` | Connector writes back Tally IDs after sync |

**Auth:** `Authorization: Bearer <inbound_token>` on all three endpoints. Same per-tenant token. No `companyId` needed.

---

## 1. Inbound — POST `/api/tally/inbound/vouchers`

The Tally connector calls this endpoint to push vouchers into Accobot. `VoucherBaseType` is the required top-level field that drives all classification logic (routing, auto-mapping, full-sync scoping). `VoucherType` is stored as the specific voucher name but is not used for routing.

### Request body

```json
{
  "VoucherType": "Sales",
  "full_sync": false,
  "Data": [ <VoucherItem>, ... ]
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `VoucherBaseType` | string | Yes | Classification driver. One of: `Sales`, `Purchase`, `CreditNote`, `DebitNote`, `Receipt`, `Payment`, `Contra`, `Journal` |
| `VoucherType` | string | No | Specific Tally voucher type name (e.g. `"Sales-Interstate"`). Stored as-is; falls back to `VoucherBaseType` if omitted |
| `Data` | array | Yes | Array of voucher objects — see [Voucher Item Schema](#voucher-item-schema) |
| `full_sync` | boolean | No | If `true`, records absent from this push are marked inactive |

Returns `422` if `VoucherBaseType` is missing or not in the valid list:
```json
{ "error": "Invalid or missing VoucherBaseType. Must be one of: Sales, CreditNote, Purchase, ..." }
```

### Response

```json
{
  "synced": 3,
  "created": 1,
  "updated": 2,
  "deleted": 0,
  "errors": []
}
```

### Sales inbound example

```json
{
  "VoucherType": "Sales",
  "full_sync": false,
  "Data": [
    {
      "MasterID": 1,
      "AlterID": 31,
      "Action": "Update",
      "VoucherNumber": "1",
      "VoucherDate": "20250401",
      "PartyName": "BLUE STAR LIMITED",
      "VoucherType": "Sales",
      "VoucherBaseType": "Sales",
      "Voucher_Total": 35964.04,
      "IsInvoice": "Yes",
      "IsDeleted": "No",
      "PlaceOfSupply": "Odisha",
      "BuyerName": "BLUE STAR LIMITED",
      "BuyerAlias": "Ledger Alias",
      "BuyerGSTIN": "21AAACB4487D1Z4",
      "BuyerPinCode": "752101",
      "BuyerState": "Odisha",
      "BuyerCountryName": "India",
      "BuyerGSTRegistrationType": "Regular",
      "BuyerEmail": "abc@gmail.com",
      "BuyerMobile": "9358444502",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "ConsigneeState": "Odisha",
      "ConsigneeCountryName": "India",
      "ConsigneeGSTRegistrationType": "Unregistered/Consumer",
      "InventoryEntries": [
        {
          "StockItemName": "Supply of Goods Transport Service",
          "HSNCode": "996511",
          "IGSTRate": 18,
          "CessRate": 0.00,
          "IsDeemedPositive": "No",
          "ActualQty": 0,
          "BilledQty": 0,
          "Rate": 0,
          "DiscountPercent": 0,
          "Amount": 30478.00,
          "TaxAmount": 5486.04,
          "MRP": 0,
          "SalesLedger": "Transportation Charges",
          "GodownName": "Main Location",
          "BatchName": "Primary Batch",
          "BatchAllocations": [
            {
              "BatchName": "Primary Batch",
              "GodownName": "Main Location",
              "ActualQty": 0.00,
              "BilledQty": 0.00,
              "Rate": 0.00,
              "DiscountPercent": 0.00,
              "Amount": 30478.00
            }
          ],
          "AccountingAllocations": [
            {
              "LedgerName": "Transportation Charges",
              "LedgerGroup": "Sales Accounts",
              "IGSTRate": 0,
              "Amount": 30478.00
            }
          ]
        }
      ],
      "ledgerentries": [
        {
          "LedgerName": "BLUE STAR LIMITED",
          "LedgerAmount": 35964.04,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "BillsAllocation": [
            { "AgstType": "New Ref", "Reference": "1", "CreditPeriod": "120 Days", "Amount": -35964.04 }
          ]
        },
        {
          "LedgerName": "CGST OUTPUT",
          "LedgerAmount": 2743.02,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "BillsAllocation": [{ "AgstType": "On Account", "Amount": 2743.02 }]
        },
        {
          "LedgerName": "SGST OUTPUT",
          "LedgerAmount": 2743.02,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "BillsAllocation": [{ "AgstType": "On Account", "Amount": 2743.02 }]
        }
      ]
    }
  ]
}
```

### Payment inbound example

```json
{
  "VoucherType": "Payment",
  "Data": [
    {
      "MasterID": 25,
      "AlterID": 30,
      "Action": "Update",
      "VoucherNumber": "7",
      "VoucherDate": "20250401",
      "PartyName": "Cash",
      "VoucherType": "Payment",
      "VoucherBaseType": "Payment",
      "Voucher_Total": 10500.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "BEING CASH PAID FOR GROCERY MESS",
      "ledgerentries": [
        {
          "LedgerName": "STAFF FOODING STARLINE CUTTACK BRANCH",
          "LedgerAmount": 10500.00,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No"
        },
        {
          "LedgerName": "Cash",
          "LedgerAmount": 10500.00,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes"
        }
      ]
    }
  ]
}
```

---

## 2. Outbound — GET `/api/VoucherAPI/voucher`

The connector polls this endpoint to fetch all vouchers that are pending sync from Accobot to Tally. Returns all 8 standard voucher types in a single response. Each item includes `VoucherType` so the connector can distinguish types.

Only records in the `tally_outbound_queue` with status `pending` are included. An empty `Data` array means nothing is pending.

### Response

```json
{
  "Data": [ <VoucherItem>, ... ]
}
```

### Sales outbound example

```json
{
  "Data": [
    {
      "AccobotId": 1,
      "TallyId": null,
      "AlterID": 31,
      "Action": "Update",
      "VoucherType": "Sales",
      "VoucherBaseType": "Sales",
      "VoucherNumber": "1",
      "VoucherDate": "20250401",
      "PartyName": "BLUE STAR LIMITED",
      "VoucherTotal": 35964.04,
      "IsInvoice": "Yes",
      "PlaceOfSupply": "Odisha",
      "IsDeleted": "No",
      "BuyerName": "BLUE STAR LIMITED",
      "BuyerGSTIN": "21AAACB4487D1Z4",
      "BuyerState": "Odisha",
      "BuyerCountryName": "India",
      "BuyerGSTRegistrationType": "Regular",
      "InventoryEntries": [
        {
          "StockItemName": "Supply of Goods Transport Service",
          "HSNCode": "996511",
          "IGSTRate": 18,
          "Amount": 30478.00,
          "TaxAmount": 5486.04,
          "SalesLedger": "Transportation Charges",
          "BatchAllocations": [ { "BatchName": "Primary Batch", "Amount": 30478.00 } ],
          "AccountingAllocations": [ { "LedgerName": "Transportation Charges", "Amount": 30478.00 } ]
        }
      ],
      "LedgerEntries": [
        { "LedgerName": "BLUE STAR LIMITED", "LedgerAmount": 35964.04, "IsDeemedPositive": "Yes", "IsPartyLedger": "Yes" },
        { "LedgerName": "CGST OUTPUT",       "LedgerAmount": 2743.02,  "IsDeemedPositive": "No",  "IsPartyLedger": "No"  },
        { "LedgerName": "SGST OUTPUT",       "LedgerAmount": 2743.02,  "IsDeemedPositive": "No",  "IsPartyLedger": "No"  }
      ]
    }
  ]
}
```

### Receipt outbound example

```json
{
  "Data": [
    {
      "AccobotId": 5,
      "TallyId": null,
      "Action": "Create",
      "VoucherType": "Receipt",
      "VoucherBaseType": "Receipt",
      "VoucherNumber": "3",
      "VoucherDate": "20250502",
      "PartyName": "BLUE STAR LIMITED",
      "VoucherTotal": 35354.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "BEING AMOUNT RECEIVED",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "BLUE STAR LIMITED",
          "LedgerAmount": 35354.00,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "BillsAllocation": [{ "AgstType": "Agst Ref", "Reference": "1", "CreditPeriod": "120 Days", "Amount": -35354.00 }]
        },
        {
          "LedgerName": "PUNJAB NATIONAL BANK STARLINE EXPRESS CC ACCOUNT",
          "LedgerAmount": 35354.00,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "BankAllocationDetails": [{ "TRANSACTIONTYPE": "Same Bank Transfer", "BANKNAME": "ICICI Bank", "AMOUNT": "35,354.00", "PAYMENTFAVOURING": "", "IFSCODE": "", "ACCOUNTNUMBER": "" }]
        }
      ]
    }
  ]
}
```

### Contra outbound example

```json
{
  "Data": [
    {
      "AccobotId": 12,
      "TallyId": null,
      "Action": "Create",
      "VoucherType": "Contra",
      "VoucherBaseType": "Contra",
      "VoucherNumber": "2",
      "VoucherDate": "20250402",
      "PartyName": "PUNJAB NATIONAL BANK",
      "VoucherTotal": 50000.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "BEING AMOUNT CASH WDL",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "PUNJAB NATIONAL BANK",
          "LedgerAmount": 50000.00,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "BankAllocationDetails": [{ "TRANSACTIONTYPE": "Same Bank Transfer", "BANKNAME": "", "AMOUNT": "50,000.00", "PAYMENTFAVOURING": "Self", "IFSCODE": "", "ACCOUNTNUMBER": "" }]
        },
        { "LedgerName": "Cash", "LedgerAmount": 50000.00, "IsDeemedPositive": "Yes", "IsPartyLedger": "No" }
      ]
    }
  ]
}
```

### Journal outbound example

```json
{
  "Data": [
    {
      "AccobotId": 15,
      "TallyId": null,
      "Action": "Create",
      "VoucherType": "Journal",
      "VoucherBaseType": "Journal",
      "VoucherNumber": "1",
      "VoucherDate": "20250501",
      "PartyName": "BLUE STAR LIMITED",
      "VoucherTotal": 610.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "TDS Receivable A/c",
          "LedgerAmount": 610.00,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No",
          "BillsAllocation": [{ "AgstType": "On Account", "Amount": -610.00 }]
        },
        {
          "LedgerName": "BLUE STAR LIMITED",
          "LedgerAmount": 610.00,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "BillsAllocation": [{ "AgstType": "Agst Ref", "Reference": "1", "CreditPeriod": "120 Days", "Amount": 610.00 }]
        }
      ]
    }
  ]
}
```

---

## 3. Confirm — POST `/api/VoucherAPI/update-voucher`

After writing vouchers into Tally, the connector posts back the Tally-assigned IDs. Handles all voucher types in a single call.

### Request

```json
{
  "Data": [
    { "AccobotId": 1, "TallyId": 4501, "IsSynced": true  },
    { "AccobotId": 8, "TallyId": 4502, "IsSynced": false }
  ]
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `AccobotId` | integer | Yes | Must match the `AccobotId` from the outbound GET response |
| `TallyId` | integer | Yes | Tally's internal numeric ID assigned to this voucher |
| `IsSynced` | boolean | No | If `true`, touches `tally_synced_at` on the mapped Accobot Invoice |

### Response

```json
{ "status": "ok", "updated": 2 }
```

Items with a missing or unrecognised `AccobotId`, or a missing `TallyId`, are silently skipped. `updated` reflects records actually written.

---

## Voucher Item Schema

Shared structure used in both the inbound `Data` array and outbound `Data` response. Null/absent fields are omitted (`dropNulls`).

### Root fields

| Field | Inbound key | Outbound key | Type | Notes |
|---|---|---|---|---|
| Accobot internal ID | *(not sent inbound)* | `AccobotId` | integer | Echo back in confirmation |
| Tally ID | `MasterID` | `TallyId` | integer \| null | |
| Alteration seq | `AlterID` | `AlterID` | integer \| null | |
| Sync action | `Action` | `Action` | string | `Create` / `Update` / `Delete` |
| Voucher type | `VoucherType` | `VoucherType` | string | See [Voucher Types](#voucher-types) |
| Voucher base type | `VoucherBaseType` | `VoucherBaseType` | string \| null | Optional. Connector-supplied base classification (e.g. `"Payment"`, `"Receipt"`, `"Invoice"`). Stored and echoed back as-is. |
| Voucher number | `VoucherNumber` | `VoucherNumber` | string | |
| Voucher date | `VoucherDate` | `VoucherDate` | string | `Ymd` — e.g. `"20250401"` |
| Total amount | `Voucher_Total` | `VoucherTotal` | decimal | Note: inbound uses underscore |
| Party name | `PartyName` | `PartyName` | string | |
| Reference | `Reference` | `Reference` | string \| null | |
| Reference date | `ReferenceDate` | `ReferenceDate` | string \| null | |
| Is invoice | `IsInvoice` | `IsInvoice` | string | `"Yes"` / `"No"` |
| Is deleted | `IsDeleted` | `IsDeleted` | string | `"Yes"` / `"No"` |
| Place of supply | `PlaceOfSupply` | `PlaceOfSupply` | string \| null | State name |
| Cost centre | `VoucherCostCentre` | `VoucherCostCentre` | string \| null | |
| Narration | `Narration` | `Narration` | string \| null | |
| e-Invoice IRN | `IRN` | `IRN` | string \| null | |
| Acknowledgement no | `AcknowledgementNo` | `AcknowledgementNo` | string \| null | |
| Acknowledgement date | `AcknowledgementDate` | `AcknowledgementDate` | string \| null | |
| QR code | `QRCode` | `QRCode` | string \| null | |

### Dispatch / shipping *(Sales, Purchase, CreditNote, DebitNote)*

| Field | Type |
|---|---|
| `DeliveryNoteNo` | string \| null |
| `DeliveryNoteDate` | string \| null |
| `DispatchDocNo` | string \| null |
| `DispatchThrough` | string \| null |
| `Destination` | string \| null |
| `CarrierName` | string \| null |
| `LRNo` | string \| null |
| `LRDate` | string \| null |
| `MotorVehicleNo` | string \| null |

### Order / terms *(Sales, Purchase)*

| Field | Type |
|---|---|
| `OrderNo` | string \| null |
| `OrderDate` | string \| null |
| `TermsOfPayment` | string \| null |
| `OtherReferences` | string \| null |
| `TermsOfDelivery` | string \| null |

### Buyer *(Sales, CreditNote)*

| Field | Type |
|---|---|
| `BuyerName` | string \| null |
| `BuyerAlias` | string \| null |
| `BuyerGSTIN` | string \| null |
| `BuyerPinCode` | string \| null |
| `BuyerState` | string \| null |
| `BuyerCountryName` | string \| null |
| `BuyerGSTRegistrationType` | string \| null |
| `BuyerEmail` | string \| null |
| `BuyerMobile` | string \| null |
| `BuyerAddress` | array of `{ "BuyerAddress": string }` |

### Consignee *(Sales)*

| Field | Type |
|---|---|
| `ConsigneeName` | string \| null |
| `ConsigneeGSTIN` | string \| null |
| `ConsigneeTallyGroup` | string \| null |
| `ConsigneePinCode` | string \| null |
| `ConsigneeState` | string \| null |
| `ConsigneeCountryName` | string \| null |
| `ConsigneeGSTRegistrationType` | string \| null |

### InventoryEntries *(Sales, Purchase, CreditNote, DebitNote)*

Inbound key: `InventoryEntries`. Outbound key: `InventoryEntries`. Empty array for non-inventory types.

| Field | Type | Notes |
|---|---|---|
| `StockItemName` | string | |
| `ItemCode` | string \| null | |
| `GroupName` | string \| null | Stock group |
| `HSNCode` | string \| null | |
| `Unit` | string \| null | |
| `IGSTRate` | decimal \| null | |
| `CessRate` | decimal \| null | |
| `IsDeemedPositive` | string | `"Yes"` / `"No"` |
| `ActualQty` | decimal | |
| `BilledQty` | decimal | |
| `Rate` | decimal | |
| `DiscountPercent` | decimal \| null | |
| `Amount` | decimal | |
| `TaxAmount` | decimal \| null | |
| `MRP` | decimal \| null | |
| `SalesLedger` | string \| null | |
| `GodownName` | string \| null | |
| `BatchName` | string \| null | |
| `BatchAllocations` | array | See below |
| `AccountingAllocations` | array | See below |

**BatchAllocations**

| Field | Type |
|---|---|
| `BatchName` | string |
| `ExpiryDate` | string \| null |
| `GodownName` | string \| null |
| `ActualQty` | decimal |
| `BilledQty` | decimal |
| `Rate` | decimal |
| `DiscountPercent` | decimal |
| `Amount` | decimal |

**AccountingAllocations**

| Field | Type |
|---|---|
| `LedgerName` | string |
| `LedgerGroup` | string \| null |
| `GSTClassification` | string \| null |
| `IGSTRate` | decimal \| null |
| `Amount` | decimal |

### LedgerEntries *(all types)*

Inbound key: `ledgerentries` (lowercase). Outbound key: `LedgerEntries` (PascalCase).

| Field | Type | Notes |
|---|---|---|
| `LedgerName` | string | |
| `LedgerGroup` | string \| null | |
| `LedgerAmount` | decimal | |
| `IsDeemedPositive` | string | `"Yes"` / `"No"` |
| `IsPartyLedger` | string | `"Yes"` / `"No"` |
| `IGSTRate` | decimal \| null | |
| `HSNCode` | string \| null | |
| `CessRate` | string \| null | Inbound: `Cess_Rate` |
| `BillsAllocation` | array | See below |
| `BankAllocationDetails` | array | See below |

**BillsAllocation**

| Field | Type | Notes |
|---|---|---|
| `AgstType` | string | `"New Ref"` / `"Agst Ref"` / `"On Account"` / `"Advance"` |
| `Reference` | string \| null | Bill reference |
| `CreditPeriod` | string \| null | e.g. `"120 Days"` |
| `Amount` | decimal | Negative = credit |

**BankAllocationDetails**

Tally uses ALL-CAPS keys for this object. Pass through unchanged; do not rename or strip fields.

| Field | Type | Notes |
|---|---|---|
| `TRANSACTIONTYPE` | string | e.g. `"Same Bank Transfer"`, `"Electronic Fund Transfer"` |
| `BANKNAME` | string \| null | Destination bank name |
| `AMOUNT` | string | Formatted amount, e.g. `"50,000.00"` |
| `Date` | string \| null | Transaction date `YYYY-MM-DD` |
| `InstrumentDate` | string \| null | Cheque/instrument date |
| `IFSCODE` | string \| null | Bank IFSC code |
| `ACCOUNTNUMBER` | string \| null | Destination account number |
| `PAYMENTFAVOURING` | string \| null | Payee name, e.g. `"Self"` |
| `TRANSFERMODE` | string \| null | Transfer mode |
| `INSTRUMENTNUMBER` | string \| null | Cheque/DD number |
| `BankersDate` | string \| null | Bank clearing date |

---

## Voucher Types

| `VoucherType` | `IsInvoice` | Inventory | BillRefs | BankAlloc | Consignee/Dispatch/Order/eInv | Reference | PlaceOfSupply |
|---|---|---|---|---|---|---|---|
| `Sales` | `"Yes"` | Yes | Yes | — | Yes | Yes | Yes |
| `Purchase` | `"Yes"` | Yes | Yes | — | Yes | Yes | Yes |
| `CreditNote` | `"Yes"` | Yes | Yes | — | Yes | Yes | Yes |
| `DebitNote` | `"Yes"` | Yes | Yes | — | Yes | Yes | Yes |
| `Receipt` | `"No"` | — | Yes | Yes | — | — | — |
| `Payment` | `"No"` | — | Yes | Yes | — | — | Yes |
| `Contra` | `"No"` | — | — | Yes | — | — | — |
| `Journal` | `"No"` | — | Yes | — | — | Yes | Yes |

The CRUD form in `Vouchers.vue` uses these columns to conditionally show/hide form sections per selected voucher type.
