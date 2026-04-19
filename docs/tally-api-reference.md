# Tally Connector — API Reference

Complete request/response reference for all 35 Accobot-Tally API endpoints.  
Base URL: `https://<your-accobot-domain>/api`

---

## Authentication

Every request must include a Bearer token in the `Authorization` header.  
The token is the **48-character `inbound_token`** generated per tenant in the Tally Connection settings.

```
Authorization: Bearer <inbound_token>
```

The token resolves the tenant automatically — no company ID needed in headers (though some endpoints accept `?companyId=` as a query parameter for verification).

---

## Data Conventions

| Convention | Detail |
|---|---|
| Boolean fields | `"Yes"` / `"No"` strings (Tally format). Also accepts `true`/`false`/`"1"`/`"0"` |
| Dates | `"YYYY-MM-DD"` string |
| Numeric | Plain number or numeric string |
| `ID` / `AlterID` | Tally's internal integer IDs. Both `ID`/`Id` and `AlterID`/`AlterId` are accepted |
| `Action` | `"Create"` (default) or `"Delete"` (soft-deletes the record) |
| `full_sync` | When `true`, any record **not** present in the payload is marked inactive |
| EOT prefix | Tally prefixes some strings with ASCII `\u0004` (EOT). Accobot strips this automatically |

---

## 1. Inbound: Masters (Tally → Accobot)

The connector **POSTs** master data to Accobot. Accobot upserts records using `ID` + `AlterID` deduplication (same AlterID = skip, no DB write).

### Standard Inbound Response

All inbound endpoints return the same structure:

```json
{
  "status": "success",
  "created": 3,
  "updated": 1,
  "skipped": 12,
  "failed": 0
}
```

| Field | Type | Description |
|---|---|---|
| `status` | string | `"success"` or `"failed"` |
| `created` | integer | New records inserted |
| `updated` | integer | Existing records updated |
| `skipped` | integer | Records with matching AlterID — no change needed |
| `failed` | integer | Records with missing `ID` or processing errors |

---

### 1.1 POST `/api/tally/inbound/masters/ledger-groups`

Syncs Tally ledger groups (account group hierarchy).

**Request Body**

```json
{
  "Data": [
    {
      "ID": 1,
      "AlterID": 42,
      "Action": "Create",
      "Name": "Sundry Debtors",
      "UnderID": 0,
      "UnderName": "Current Assets",
      "NatureOfGroup": "Assets",
      "IsRevenue": "No",
      "AffectsGross": "No",
      "IsAddable": "Yes"
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `Data` | array | yes | Array of ledger group objects |
| `ID` | integer | yes | Tally's internal group ID |
| `AlterID` | integer | yes | Tally's alter/version ID — used for skip logic |
| `Action` | string | no | `"Create"` (default) or `"Delete"` |
| `Name` | string | yes | Group name |
| `UnderID` | integer | no | Parent group's Tally ID |
| `UnderName` | string | no | Parent group name |
| `NatureOfGroup` | string | no | e.g. `"Assets"`, `"Liabilities"` |
| `IsRevenue` | string | no | `"Yes"` / `"No"` |
| `AffectsGross` | string | no | `"Yes"` / `"No"` |
| `IsAddable` | string | no | `"Yes"` / `"No"` |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 1.2 POST `/api/tally/inbound/masters/ledgers`

Syncs Tally ledgers (accounts). Debtors → auto-creates/updates **Client**. Creditors/Suppliers → auto-creates/updates **Vendor**.

**Request Body**

```json
{
  "full_sync": false,
  "Data": [
    {
      "ID": 101,
      "AlterID": 205,
      "Action": "Create",
      "LedgerName": "BlueStar Technologies",
      "GroupName": "Sundry Debtors",
      "ParentGroup": "Current Assets",
      "IsBillWiseOn": "Yes",
      "InventoryAffected": "No",
      "IsCostCentreApplicable": "No",
      "GSTINNumber": "27AABCT1234A1Z5",
      "PANNumber": "AABCT1234A",
      "TANNumber": null,
      "GSTType": "Regular",
      "IsRCMApplicable": "No",
      "MailingName": "BlueStar Technologies Pvt Ltd",
      "MobileNumber": "9876543210",
      "ContactPerson": "Rajesh Kumar",
      "ContactPersonEmail": "rajesh@bluestar.in",
      "ContactPersonMobile": "9876543210",
      "Addresses": ["123 MG Road", "Bangalore - 560001"],
      "StateName": "Karnataka",
      "CountryName": "India",
      "PinCode": "560001",
      "CreditPeriod": 30,
      "CreditLimit": 500000,
      "OpeningBalance": 125000,
      "OpeningBalanceType": "Dr",
      "BankDetails": [],
      "Aliases": [],
      "Description": null,
      "Notes": null
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `full_sync` | boolean | no | If `true`, ledgers absent from payload are marked inactive |
| `Data` | array | yes | Array of ledger objects |
| `ID` | integer | yes | Tally ledger ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `LedgerName` | string | yes | Ledger name (also accepted as `Name`) |
| `GroupName` | string | no | Immediate group — used to derive category |
| `ParentGroup` | string | no | Top-level group — used to derive category |
| `IsBillWiseOn` | string | no | Bill-by-bill tracking on |
| `InventoryAffected` | string | no | Affects inventory |
| `IsCostCentreApplicable` | string | no | Cost centre on |
| `GSTINNumber` | string | no | GST registration number |
| `PANNumber` | string | no | PAN |
| `TANNumber` | string | no | TAN |
| `GSTType` | string | no | `"Regular"`, `"Composition"`, `"Unregistered"`, etc. |
| `IsRCMApplicable` | string | no | Reverse Charge Mechanism |
| `MailingName` | string | no | Display / mailing name |
| `MobileNumber` | string | no | Primary mobile |
| `ContactPerson` | string | no | Contact name |
| `ContactPersonEmail` | string | no | Contact email |
| `ContactPersonEmailCC` | string | no | CC email |
| `ContactPersonFax` | string | no | Fax |
| `ContactPersonWebsite` | string | no | Website |
| `ContactPersonMobile` | string | no | Contact mobile |
| `Addresses` | array | no | Array of address lines |
| `StateName` | string | no | State |
| `CountryName` | string | no | Country |
| `PinCode` | string | no | PIN / postal code |
| `CreditPeriod` | integer | no | Credit period in days |
| `CreditLimit` | float | no | Credit limit amount |
| `OpeningBalance` | float | no | Opening balance value |
| `OpeningBalanceType` | string | no | `"Dr"` or `"Cr"` |
| `BankDetails` | array | no | Bank account array |
| `Aliases` | array | no | Alternate names |
| `Description` | string | no | Description |
| `Notes` | string | no | Internal notes |

**Auto-mapping rules (derived from GroupName + ParentGroup):**

| Category derived | Action |
|---|---|
| `customer` (contains "debtor") | Creates/updates a **Client** record |
| `vendor` (contains "creditor" / "supplier") | Creates/updates a **Vendor** record |
| `bank`, `tax`, `income`, `expense`, `asset`, `liability`, `other` | Stored in `tally_ledgers` only, no Accobot record |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 1.3 POST `/api/tally/inbound/masters/stock-groups`

Syncs Tally stock groups.

**Request Body**

```json
{
  "Data": [
    {
      "ID": 10,
      "AlterID": 55,
      "Action": "Create",
      "Name": "Network Equipment",
      "ParentID": 0,
      "ParentName": "Primary",
      "NatureOfGroup": "Stock-in-Hand",
      "ShouldAddQuantities": "Yes"
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `ID` | integer | yes | Tally stock group ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Group name |
| `ParentID` | integer | no | Parent group Tally ID |
| `ParentName` | string | no | Parent group name |
| `NatureOfGroup` | string | no | Nature of the group |
| `ShouldAddQuantities` | string | no | `"Yes"` / `"No"` |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 1.4 POST `/api/tally/inbound/masters/stock-categories`

Syncs Tally stock categories.

**Request Body**

```json
{
  "Data": [
    {
      "ID": 5,
      "AlterID": 11,
      "Action": "Create",
      "Name": "Lease Line Services",
      "ParentName": "Primary"
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `ID` | integer | yes | Tally category ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Category name |
| `ParentName` | string | no | Parent category name |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 1.5 POST `/api/tally/inbound/masters/stock-items`

Syncs Tally stock items (products/services). Auto-creates/updates a **Product** record for every stock item.

**Request Body**

```json
{
  "full_sync": false,
  "Data": [
    {
      "ID": 201,
      "AlterID": 88,
      "Action": "Create",
      "Name": "30Mbps Lease Line",
      "Description": "Dedicated internet lease line 30Mbps",
      "Remarks": null,
      "Aliases": [],
      "StockGroupID": 10,
      "StockGroupName": "Network Equipment",
      "StockCategoryID": 5,
      "CategoryName": "Lease Line Services",
      "UnitID": 3,
      "UnitName": "Nos",
      "AlternateUnit": null,
      "Conversion": null,
      "Denominator": 1,
      "IsGSTApplicable": "Yes",
      "Taxability": "Taxable",
      "CalculationType": "On Value",
      "IGSTRate": 18,
      "SGSTRate": 9,
      "CGSTRate": 9,
      "CessRate": 0,
      "HSNCode": "998422",
      "MRPRate": null,
      "StandardCost": 0,
      "StandardPrice": 15000,
      "OpeningBalance": 0,
      "OpeningRate": 0,
      "OpeningValue": 0,
      "ClosingBalance": 0,
      "ClosingRate": 0,
      "ClosingValue": 0,
      "CostingMethod": "FIFO",
      "IsBatchApplicable": "No",
      "IsExpiryDateApplicable": "No",
      "ReorderLevel": null,
      "ReorderQuantity": null,
      "MaximumQuantity": null,
      "BatchAllocations": []
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `full_sync` | boolean | no | Mark absent items inactive |
| `ID` | integer | yes | Tally stock item ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Item name |
| `Description` | string | no | Description |
| `Remarks` | string | no | Remarks |
| `Aliases` | array | no | Alternate names |
| `StockGroupID` | integer | no | Tally stock group ID |
| `StockGroupName` | string | no | Stock group name |
| `StockCategoryID` | integer | no | Tally category ID |
| `CategoryName` | string | no | Category name |
| `UnitID` | integer | no | Unit of measure ID |
| `UnitName` | string | no | Unit name e.g. `"Nos"`, `"Kgs"` |
| `AlternateUnit` | string | no | Alternate unit |
| `Conversion` | float | no | Conversion factor |
| `Denominator` | integer | no | Denominator for conversion |
| `IsGSTApplicable` | string | no | `"Yes"` / `"No"` |
| `Taxability` | string | no | `"Taxable"`, `"Exempt"`, `"Nil Rated"` |
| `CalculationType` | string | no | GST calculation method |
| `IGSTRate` | float | no | IGST % |
| `SGSTRate` | float | no | SGST % |
| `CGSTRate` | float | no | CGST % |
| `CessRate` | float | no | Cess % |
| `HSNCode` | string | no | HSN / SAC code |
| `MRPRate` | float | no | MRP |
| `StandardCost` | float | no | Standard cost |
| `StandardPrice` | float | no | Standard selling price |
| `OpeningBalance` | float | no | Opening stock quantity |
| `OpeningRate` | float | no | Opening rate |
| `OpeningValue` | float | no | Opening value |
| `ClosingBalance` | float | no | Closing stock quantity |
| `ClosingRate` | float | no | Closing rate |
| `ClosingValue` | float | no | Closing value |
| `CostingMethod` | string | no | `"FIFO"`, `"Avg Cost"`, etc. |
| `IsBatchApplicable` | string | no | `"Yes"` / `"No"` |
| `IsExpiryDateApplicable` | string | no | `"Yes"` / `"No"` |
| `ReorderLevel` | float | no | Reorder level quantity |
| `ReorderQuantity` | float | no | Reorder quantity |
| `MaximumQuantity` | float | no | Maximum stock quantity |
| `BatchAllocations` | array | no | Batch allocation details |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

## 2. Inbound: Vouchers (Tally → Accobot)

All voucher endpoints share the same request structure and response. The key differences are:
- **Sales** vouchers: auto-creates/updates an **Invoice** in Accobot (if party ledger resolves to a Client)
- **Receipt / Payment / Contra / Journal**: stored in `tally_vouchers` only, no Accobot operational record created

### Common Voucher Fields

Every voucher object supports:

| Field | Type | Description |
|---|---|---|
| `MasterID` | integer | Tally's voucher master ID (also `ID` / `Id`) |
| `AlterID` | integer | Alter/version ID |
| `Action` | string | `"Create"` or `"Delete"` |
| `VoucherType` | string | Set automatically by endpoint (`"Sales"`, `"Receipt"`, etc.) |
| `VoucherNumber` | string | Voucher number (e.g. `"2024-25/001"`) |
| `VoucherDate` | string | Date `"YYYY-MM-DD"` |
| `Reference` | string | Reference number |
| `ReferenceDate` | string | Reference date |
| `PartyName` | string | Party ledger name — resolved to `TallyLedger` FK |
| `VoucherTotal` | float | Total voucher amount |
| `IsInvoice` | string | `"Yes"` / `"No"` |
| `PlaceOfSupply` | string | State / place of supply |
| `DeliveryNoteNo` | string | Delivery note number |
| `DeliveryNoteDate` | string | Delivery note date |
| `DispatchDocNo` | string | Dispatch document number |
| `DispatchThrough` | string | Dispatch mode |
| `Destination` | string | Destination |
| `CarrierName` | string | Carrier |
| `LRNo` | string | LR number |
| `LRDate` | string | LR date |
| `MotorVehicleNo` | string | Vehicle number |
| `OrderNo` | string | Order number |
| `OrderDate` | string | Order date |
| `TermsOfPayment` | string | Payment terms |
| `TermsOfDelivery` | string | Delivery terms |
| `OtherReferences` | string | Other refs |
| `BuyerName` | string | Buyer name |
| `BuyerAlias` | string | Buyer alias |
| `BuyerGSTIN` | string | Buyer GST number |
| `BuyerPinCode` | string | Buyer PIN |
| `BuyerState` | string | Buyer state |
| `BuyerCountry` | string | Buyer country |
| `BuyerGSTRegistrationType` | string | GST reg type |
| `BuyerEmail` | string | Buyer email |
| `BuyerMobile` | string | Buyer mobile |
| `BuyerAddress` | string | Buyer address |
| `ConsigneeName` | string | Consignee name |
| `ConsigneeGSTIN` | string | Consignee GSTIN |
| `ConsigneeTallyGroup` | string | Consignee group |
| `ConsigneePinCode` | string | Consignee PIN |
| `ConsigneeState` | string | Consignee state |
| `ConsigneeCountry` | string | Consignee country |
| `ConsigneeGSTRegistrationType` | string | Consignee GST reg type |
| `IRN` | string | Invoice Reference Number (e-invoice) |
| `AcknowledgementNo` | string | IRN acknowledgement number |
| `AcknowledgementDate` | string | Acknowledgement date |
| `QRCode` | string | QR code string |
| `Narration` | string | Voucher narration |
| `CostCentre` | string | Cost centre |
| `InventoryEntries` | array | Line items — see below |
| `LedgerEntries` | array | Accounting entries — see below |

### InventoryEntries (line items)

| Field | Type | Description |
|---|---|---|
| `StockItemName` | string | Stock item name — resolved to `TallyStockItem` FK |
| `ItemCode` | string | Item code |
| `GroupName` | string | Item group |
| `HSNCode` | string | HSN/SAC code |
| `Unit` | string | Unit of measure |
| `IGSTRate` | float | IGST % |
| `CessRate` | float | Cess % |
| `IsDeemedPositive` | string | `"Yes"` / `"No"` |
| `ActualQty` | float | Actual quantity |
| `BilledQty` | float | Billed quantity |
| `Rate` | float | Rate per unit |
| `DiscountPercent` | float | Discount % |
| `Amount` | float | Line amount |
| `TaxAmount` | float | Tax on line |
| `MRP` | float | MRP |
| `SalesLedger` | string | Mapped sales ledger |
| `GodownName` | string | Godown / warehouse |
| `BatchName` | string | Batch |
| `BatchAllocations` | array | Batch details |
| `AccountingAllocations` | array | Accounting allocations |

### LedgerEntries (accounting entries)

| Field | Type | Description |
|---|---|---|
| `LedgerName` | string | Ledger name — resolved to `TallyLedger` FK |
| `LedgerGroup` | string | Ledger group |
| `LedgerAmount` | float | Amount (positive = Dr, negative = Cr by convention) |
| `IsDeemedPositive` | string | `"Yes"` / `"No"` |
| `IsPartyLedger` | string | `"Yes"` / `"No"` |
| `IGSTRate` | string | IGST rate |
| `HSNCode` | string | HSN code |
| `CessRate` | string | Cess rate |
| `BillsAllocation` | array | Bill-wise allocation details |

---

### 2.1 POST `/api/tally/inbound/vouchers/sales`

**Sales invoice** — also auto-creates an Accobot Invoice when the party ledger maps to a Client.

**Request Body**

```json
{
  "full_sync": false,
  "Data": [
    {
      "MasterID": 5001,
      "AlterID": 300,
      "Action": "Create",
      "VoucherNumber": "2024-25/INV/001",
      "VoucherDate": "2024-04-01",
      "PartyName": "BlueStar Technologies",
      "VoucherTotal": 17700,
      "IsInvoice": "Yes",
      "PlaceOfSupply": "Karnataka",
      "BuyerName": "BlueStar Technologies Pvt Ltd",
      "BuyerGSTIN": "27AABCT1234A1Z5",
      "BuyerState": "Karnataka",
      "BuyerAddress": "123 MG Road, Bangalore",
      "Narration": "Monthly lease line charges for April 2024",
      "IRN": null,
      "AcknowledgementNo": null,
      "AcknowledgementDate": null,
      "QRCode": null,
      "InventoryEntries": [
        {
          "StockItemName": "30Mbps Lease Line",
          "HSNCode": "998422",
          "Unit": "Nos",
          "IGSTRate": 18,
          "ActualQty": 1,
          "BilledQty": 1,
          "Rate": 15000,
          "DiscountPercent": 0,
          "Amount": 15000,
          "TaxAmount": 2700
        }
      ],
      "LedgerEntries": [
        {
          "LedgerName": "BlueStar Technologies",
          "LedgerGroup": "Sundry Debtors",
          "LedgerAmount": 17700,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes"
        },
        {
          "LedgerName": "Sales - Lease Line",
          "LedgerGroup": "Sales Accounts",
          "LedgerAmount": -15000,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
        },
        {
          "LedgerName": "IGST @18%",
          "LedgerGroup": "Duties & Taxes",
          "LedgerAmount": -2700,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
        }
      ]
    }
  ]
}
```

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 2.2 POST `/api/tally/inbound/vouchers/purchase`

Purchase invoice. Stored in `tally_vouchers` with `voucher_type = "Purchase"`. No Accobot Invoice created.

**Request Body** — same structure as Sales. Typical `LedgerEntries` will have supplier ledger (Creditor group) + purchase account + tax ledgers.

```json
{
  "Data": [
    {
      "MasterID": 6001,
      "AlterID": 410,
      "Action": "Create",
      "VoucherNumber": "PUR/2024-25/001",
      "VoucherDate": "2024-04-05",
      "PartyName": "Punjab National Bank",
      "VoucherTotal": 50000,
      "IsInvoice": "Yes",
      "Narration": "Bandwidth purchase",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "Punjab National Bank",
          "LedgerGroup": "Sundry Creditors",
          "LedgerAmount": -50000,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes"
        },
        {
          "LedgerName": "Purchase - Bandwidth",
          "LedgerGroup": "Purchase Accounts",
          "LedgerAmount": 50000,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No"
        }
      ]
    }
  ]
}
```

---

### 2.3 POST `/api/tally/inbound/vouchers/credit-note`

Credit note (sales return). `voucher_type = "CreditNote"`.

**Request Body** — same structure as Sales with negative amounts.

```json
{
  "Data": [
    {
      "MasterID": 7001,
      "AlterID": 500,
      "Action": "Create",
      "VoucherNumber": "CN/2024-25/001",
      "VoucherDate": "2024-04-10",
      "PartyName": "BlueStar Technologies",
      "VoucherTotal": 5900,
      "IsInvoice": "No",
      "Narration": "Partial credit for service downtime",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "BlueStar Technologies",
          "LedgerGroup": "Sundry Debtors",
          "LedgerAmount": -5900,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes"
        },
        {
          "LedgerName": "Sales - Lease Line",
          "LedgerGroup": "Sales Accounts",
          "LedgerAmount": 5000,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No"
        }
      ]
    }
  ]
}
```

---

### 2.4 POST `/api/tally/inbound/vouchers/debit-note`

Debit note (purchase return). `voucher_type = "DebitNote"`. Same structure as Credit Note with supplier ledger entries.

---

### 2.5 POST `/api/tally/inbound/vouchers/receipt`

Receipt voucher — money received from party. `voucher_type = "Receipt"`.

**Request Body**

```json
{
  "Data": [
    {
      "MasterID": 8001,
      "AlterID": 601,
      "Action": "Create",
      "VoucherNumber": "RCT/2024-25/001",
      "VoucherDate": "2024-04-15",
      "PartyName": "BlueStar Technologies",
      "VoucherTotal": 17700,
      "IsInvoice": "No",
      "Narration": "Receipt against INV/001",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "HDFC Bank",
          "LedgerGroup": "Bank Accounts",
          "LedgerAmount": 17700,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No"
        },
        {
          "LedgerName": "BlueStar Technologies",
          "LedgerGroup": "Sundry Debtors",
          "LedgerAmount": -17700,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "BillsAllocation": [
            { "Name": "2024-25/INV/001", "Amount": 17700 }
          ]
        }
      ]
    }
  ]
}
```

---

### 2.6 POST `/api/tally/inbound/vouchers/payment`

Payment voucher — money paid to party. `voucher_type = "Payment"`.

**Request Body**

```json
{
  "Data": [
    {
      "MasterID": 9001,
      "AlterID": 700,
      "Action": "Create",
      "VoucherNumber": "PAY/2024-25/001",
      "VoucherDate": "2024-04-20",
      "PartyName": "Punjab National Bank",
      "VoucherTotal": 50000,
      "IsInvoice": "No",
      "Narration": "Payment for bandwidth purchase",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "Punjab National Bank",
          "LedgerGroup": "Sundry Creditors",
          "LedgerAmount": 50000,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes"
        },
        {
          "LedgerName": "HDFC Bank",
          "LedgerGroup": "Bank Accounts",
          "LedgerAmount": -50000,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
        }
      ]
    }
  ]
}
```

---

### 2.7 POST `/api/tally/inbound/vouchers/contra`

Contra voucher — inter-bank / cash-bank transfers. `voucher_type = "Contra"`.

**Request Body**

```json
{
  "Data": [
    {
      "MasterID": 10001,
      "AlterID": 800,
      "Action": "Create",
      "VoucherNumber": "CON/2024-25/001",
      "VoucherDate": "2024-04-25",
      "VoucherTotal": 100000,
      "IsInvoice": "No",
      "Narration": "Transfer from cash to HDFC bank",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "HDFC Bank",
          "LedgerGroup": "Bank Accounts",
          "LedgerAmount": 100000,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No"
        },
        {
          "LedgerName": "Cash",
          "LedgerGroup": "Cash-in-Hand",
          "LedgerAmount": -100000,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
        }
      ]
    }
  ]
}
```

---

### 2.8 POST `/api/tally/inbound/vouchers/journal`

Journal voucher — adjustment entries. `voucher_type = "Journal"`.

**Request Body**

```json
{
  "Data": [
    {
      "MasterID": 11001,
      "AlterID": 900,
      "Action": "Create",
      "VoucherNumber": "JV/2024-25/001",
      "VoucherDate": "2024-03-31",
      "VoucherTotal": 12000,
      "IsInvoice": "No",
      "Narration": "Depreciation for FY 2024-25",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "Depreciation",
          "LedgerGroup": "Indirect Expenses",
          "LedgerAmount": 12000,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No"
        },
        {
          "LedgerName": "Fixed Assets",
          "LedgerGroup": "Fixed Assets",
          "LedgerAmount": -12000,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
        }
      ]
    }
  ]
}
```

---

### Voucher Delete Example

To soft-delete a voucher, send `"Action": "Delete"` with the same `MasterID`:

```json
{
  "Data": [
    {
      "MasterID": 5001,
      "AlterID": 301,
      "Action": "Delete"
    }
  ]
}
```

Response: `{ "status": "success", "created": 0, "updated": 1, "skipped": 0, "failed": 0 }`

---

## 3. Inbound: Reports (Tally → Accobot)

Reports are stored as **insert-only snapshots** in `tally_reports`. Old snapshots are never deleted. The entire request payload is stored as JSON.

### Standard Reports Response

```json
{ "status": "success" }
```

---

### 3.1 POST `/api/tally/inbound/reports/balance-sheet`

**Request Body** — entire Tally Balance Sheet payload, any structure.

```json
{
  "period_from": "2024-04-01",
  "period_to": "2024-03-31",
  "generated_at": "2025-04-01T10:00:00",
  "data": {
    "Assets": {
      "FixedAssets": 500000,
      "CurrentAssets": {
        "SundryDebtors": 250000,
        "Cash": 45000,
        "BankAccounts": 300000
      }
    },
    "Liabilities": {
      "CapitalAccount": 800000,
      "SundryCreditors": 150000,
      "Loans": 145000
    }
  }
}
```

---

### 3.2 POST `/api/tally/inbound/reports/profit-loss`

```json
{
  "period_from": "2024-04-01",
  "period_to": "2025-03-31",
  "generated_at": "2025-04-01T10:00:00",
  "data": {
    "Income": {
      "SalesAccounts": 1200000,
      "OtherIncome": 15000
    },
    "Expenditure": {
      "PurchaseAccounts": 600000,
      "IndirectExpenses": 180000,
      "Depreciation": 12000
    },
    "NetProfit": 423000
  }
}
```

---

### 3.3 POST `/api/tally/inbound/reports/cash-flow`

```json
{
  "period_from": "2024-04-01",
  "period_to": "2025-03-31",
  "generated_at": "2025-04-01T10:00:00",
  "data": {
    "OperatingActivities": 350000,
    "InvestingActivities": -80000,
    "FinancingActivities": -50000,
    "NetCashFlow": 220000
  }
}
```

---

### 3.4 POST `/api/tally/inbound/reports/ratio-analysis`

```json
{
  "period_from": "2024-04-01",
  "period_to": "2025-03-31",
  "generated_at": "2025-04-01T10:00:00",
  "data": {
    "CurrentRatio": 2.1,
    "QuickRatio": 1.8,
    "DebtEquityRatio": 0.18,
    "GrossProfitRatio": 50.0,
    "NetProfitRatio": 35.25
  }
}
```

---

## 4. Outbound: Masters (Tally reads Accobot)

Tally calls these **GET** endpoints to pull master data from Accobot. All return only `is_active = true` records.

**Query Parameter (optional for all outbound GET endpoints):**

| Parameter | Type | Description |
|---|---|---|
| `companyId` | string | If provided, verified against the connection's `company_id`. Mismatch returns `403`. |

---

### 4.1 GET `/api/MastersAPI/ledger-group`

Returns all active ledger groups.

**Request**
```
GET /api/MastersAPI/ledger-group?companyId=COMP001
Authorization: Bearer <token>
```

**Response**

```json
{
  "Data": [
    {
      "ID": 1,
      "AlterID": 42,
      "Action": "Create",
      "Name": "Sundry Debtors",
      "UnderID": 0,
      "UnderName": "Current Assets",
      "NatureOfGroup": "Assets",
      "IsRevenue": "No",
      "AffectsGross": "No",
      "IsAddable": "Yes"
    }
  ]
}
```

---

### 4.2 GET `/api/MastersAPI/ledger-master`

Returns all active ledgers.

**Request**
```
GET /api/MastersAPI/ledger-master
Authorization: Bearer <token>
```

**Response**

```json
{
  "Data": [
    {
      "ID": 101,
      "AlterID": 205,
      "Action": "Create",
      "LedgerName": "BlueStar Technologies",
      "GroupName": "Sundry Debtors",
      "ParentGroup": "Current Assets",
      "IsBillWiseOn": "Yes",
      "InventoryAffected": "No",
      "IsCostCentreApplicable": "No",
      "GSTINNumber": "27AABCT1234A1Z5",
      "PANNumber": "AABCT1234A",
      "TANNumber": "",
      "GSTType": "Regular",
      "IsRCMApplicable": "No",
      "MailingName": "BlueStar Technologies Pvt Ltd",
      "MobileNumber": "9876543210",
      "ContactPerson": "Rajesh Kumar",
      "ContactPersonEmail": "rajesh@bluestar.in",
      "ContactPersonMobile": "9876543210",
      "Addresses": ["123 MG Road", "Bangalore - 560001"],
      "StateName": "Karnataka",
      "CountryName": "India",
      "PinCode": "560001",
      "CreditPeriod": 30,
      "CreditLimit": 500000,
      "OpeningBalance": 125000,
      "OpeningBalanceType": "Dr",
      "BankDetails": [],
      "Aliases": [],
      "Description": null,
      "Notes": null
    }
  ]
}
```

---

### 4.3 GET `/api/MastersAPI/stock-master`

Returns all active stock items.

**Response**

```json
{
  "Data": [
    {
      "ID": 201,
      "AlterID": 88,
      "Action": "Create",
      "Name": "30Mbps Lease Line",
      "Description": "Dedicated internet lease line 30Mbps",
      "Remarks": null,
      "Aliases": [],
      "StockGroupID": 10,
      "StockGroupName": "Network Equipment",
      "StockCategoryID": 5,
      "CategoryName": "Lease Line Services",
      "UnitID": 3,
      "UnitName": "Nos",
      "AlternateUnit": null,
      "Conversion": null,
      "Denominator": 1,
      "IsGSTApplicable": "Yes",
      "Taxability": "Taxable",
      "CalculationType": "On Value",
      "IGSTRate": 18,
      "SGSTRate": 9,
      "CGSTRate": 9,
      "CessRate": 0,
      "HSNCode": "998422",
      "MRPRate": null,
      "StandardCost": 0,
      "StandardPrice": 15000,
      "OpeningBalance": 0,
      "OpeningRate": 0,
      "OpeningValue": 0,
      "ClosingBalance": 0,
      "ClosingRate": 0,
      "ClosingValue": 0,
      "CostingMethod": "FIFO",
      "IsBatchApplicable": "No",
      "IsExpiryDateApplicable": "No",
      "ReorderLevel": null,
      "ReorderQuantity": null,
      "MaximumQuantity": null,
      "BatchAllocations": []
    }
  ]
}
```

---

### 4.4 GET `/api/MastersAPI/stock-group`

Returns all active stock groups.

**Response**

```json
{
  "Data": [
    {
      "ID": 10,
      "AlterID": 55,
      "Action": "Create",
      "Name": "Network Equipment",
      "ParentID": 0,
      "ParentName": "Primary",
      "NatureOfGroup": "Stock-in-Hand",
      "ShouldAddQuantities": "Yes"
    }
  ]
}
```

---

### 4.5 GET `/api/MastersAPI/stock-category`

Returns all active stock categories.

**Response**

```json
{
  "Data": [
    {
      "ID": 5,
      "AlterID": 11,
      "Action": "Create",
      "Name": "Lease Line Services",
      "ParentName": "Primary"
    }
  ]
}
```

---

## 5. Outbound: Vouchers (Tally reads Accobot)

---

### 5.1 GET `/api/VoucherAPI/sales-voucher`

Returns all active Sales vouchers with inventory and ledger entries.

**Response**

```json
{
  "Data": [
    {
      "MasterID": 5001,
      "AlterID": 300,
      "Action": "Create",
      "VoucherType": "Sales",
      "VoucherNumber": "2024-25/INV/001",
      "VoucherDate": "2024-04-01",
      "Reference": null,
      "PartyName": "BlueStar Technologies",
      "VoucherTotal": 17700,
      "IsInvoice": "Yes",
      "PlaceOfSupply": "Karnataka",
      "BuyerName": "BlueStar Technologies Pvt Ltd",
      "BuyerGSTIN": "27AABCT1234A1Z5",
      "BuyerState": "Karnataka",
      "BuyerAddress": "123 MG Road, Bangalore",
      "Narration": "Monthly lease line charges for April 2024",
      "IRN": null,
      "AcknowledgementNo": null,
      "AcknowledgementDate": null,
      "QRCode": null,
      "InventoryEntries": [
        {
          "StockItemName": "30Mbps Lease Line",
          "HSNCode": "998422",
          "Unit": "Nos",
          "IGSTRate": 18,
          "ActualQty": 1,
          "BilledQty": 1,
          "Rate": 15000,
          "DiscountPercent": 0,
          "Amount": 15000,
          "TaxAmount": 2700
        }
      ],
      "LedgerEntries": [
        {
          "LedgerName": "BlueStar Technologies",
          "LedgerGroup": "Sundry Debtors",
          "LedgerAmount": 17700,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes"
        },
        {
          "LedgerName": "Sales - Lease Line",
          "LedgerGroup": "Sales Accounts",
          "LedgerAmount": -15000,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
        },
        {
          "LedgerName": "IGST @18%",
          "LedgerGroup": "Duties & Taxes",
          "LedgerAmount": -2700,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
        }
      ]
    }
  ]
}
```

---

### 5.2 GET `/api/VoucherAPI/purchase-voucher`

Returns all active Purchase vouchers. Same structure as sales-voucher with `"VoucherType": "Purchase"`.

---

### 5.3 GET `/api/VoucherAPI/debitNote-voucher`

Returns all active Debit Note vouchers. `"VoucherType": "DebitNote"`.

---

### 5.4 GET `/api/VoucherAPI/creditNote-voucher`

Returns all active Credit Note vouchers. `"VoucherType": "CreditNote"`.

---

## 6. Confirmation: Tally writes back its IDs

After Tally creates records based on data it read from Accobot (outbound GET), it calls these endpoints to write back the Tally-assigned IDs. This closes the sync loop for Accobot-originated data.

**URL Parameter:** `{companyId}` — must match the tenant's configured `company_id`.

### Standard Confirmation Response

```json
{ "status": "ok", "updated": 3 }
```

| Field | Type | Description |
|---|---|---|
| `status` | string | Always `"ok"` |
| `updated` | integer | Number of records whose `tally_id` was written |

---

### 6.1 POST `/api/MastersAPI/update-ledger-master/{companyId}`

**Request Body**

```json
{
  "Data": [
    {
      "Id": "uuid-of-accobot-tally-ledger-record",
      "TallyId": 101,
      "IsSynced": true
    }
  ]
}
```

| Field | Type | Description |
|---|---|---|
| `Id` | string | Accobot's UUID for the `tally_ledgers` record |
| `TallyId` | integer | Tally's assigned ID (also accepted as `TallyID`) |
| `IsSynced` | boolean | If `true`, marks the mapped Client/Vendor as synced (`updated_at` touched) |

---

### 6.2 POST `/api/MastersAPI/update-stock-master/{companyId}`

Same structure as update-ledger-master. Updates `tally_stock_items`. If `IsSynced: true`, touches the mapped Product.

```json
{
  "Data": [
    {
      "Id": "uuid-of-accobot-tally-stock-item-record",
      "TallyId": 201,
      "IsSynced": true
    }
  ]
}
```

---

### 6.3 POST `/api/MastersAPI/update-ledger-group/{companyId}`

Updates `tally_ledger_groups` with Tally-assigned ID.

```json
{
  "Data": [
    {
      "Id": "uuid-of-accobot-tally-ledger-group-record",
      "TallyId": 1,
      "IsSynced": false
    }
  ]
}
```

---

### 6.4 POST `/api/MastersAPI/update-stock-group/{companyId}`

Updates `tally_stock_groups`.

```json
{
  "Data": [
    { "Id": "uuid", "TallyId": 10, "IsSynced": false }
  ]
}
```

---

### 6.5 POST `/api/MastersAPI/update-stock-category/{companyId}`

Updates `tally_stock_categories`.

```json
{
  "Data": [
    { "Id": "uuid", "TallyId": 5, "IsSynced": false }
  ]
}
```

---

### 6.6 POST `/api/VoucherAPI/update-sales-voucher/{companyId}`

Updates `tally_vouchers` (Sales type). If `IsSynced: true`, touches the mapped Invoice.

```json
{
  "Data": [
    {
      "Id": "uuid-of-accobot-tally-voucher-record",
      "TallyId": 5001,
      "IsSynced": true
    }
  ]
}
```

---

### 6.7 POST `/api/VoucherAPI/update-purchase-voucher/{companyId}`

Updates `tally_vouchers` (Purchase type).

---

### 6.8 POST `/api/VoucherAPI/update-debitnote-voucher/{companyId}`

Updates `tally_vouchers` (DebitNote type).

---

### 6.9 POST `/api/VoucherAPI/update-creditnote-voucher/{companyId}`

Updates `tally_vouchers` (CreditNote type).

---

## 7. Known Limitations

### Accobot-side edits are invisible to the connector

The `AlterID` field in all outbound GET responses is **Tally's own version number** — stored as-is when Tally pushed the record inbound. Accobot does not maintain its own version counter.

**Consequence:** If you edit a master record directly in Accobot (e.g. rename a ledger group, change a ledger's address), the connector has no signal that anything changed. The next GET response will return the updated field values, but the `AlterID` is unchanged — so a connector that uses AlterID for change detection will skip the record.

**What works reliably:**
- Changes made in Tally → pushed inbound → always reflected in GET responses with a new `AlterID` ✓
- Accobot-side edits → reflected in GET field values, but no AlterID bump → connector may or may not detect the change ✗

**Workaround options:**
1. **Full resync** — instruct the connector to do a full field-level diff (not just AlterID comparison) on demand
2. **Edit in Tally, not Accobot** — for master data that originates in Tally, always make changes there; let them flow inbound

This is a current architectural gap. A future fix would be to maintain an `accobot_alter_id` counter on every Accobot-side update and expose it in GET responses alongside `AlterID`.

---

### Deletions in Accobot are not propagated to Tally

Deleting or deactivating a Client, Vendor, or Product in Accobot does **not** send any signal to Tally. The connector will continue to see the record in GET responses until it is also marked inactive in `tally_ledgers` / `tally_stock_items` (which only happens when Tally sends `Action: Delete` inbound).

---

## 8. Error Responses



### 401 Unauthorized

Missing or invalid Bearer token.

```json
{ "message": "Unauthenticated." }
```

### 403 Forbidden

`companyId` query param does not match the connection's configured company ID.

```json
{ "message": "Company ID mismatch." }
```

### 422 Unprocessable / Inbound Failure

If the entire sync fails due to an exception, the inbound response returns `status: failed`:

```json
{
  "status": "failed",
  "created": 0,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

Check the `tally_sync_logs` table for the `error_message` field.

### 429 Too Many Requests

All Tally API routes are throttled at **120 requests per minute**. Exceeding this returns:

```json
{ "message": "Too Many Attempts." }
```

---

## 9. Quick Reference — All 35 Endpoints

### Inbound POST — `Authorization: Bearer <token>`

| Method | Path | Description |
|---|---|---|
| POST | `/api/tally/inbound/masters/ledger-groups` | Sync ledger groups |
| POST | `/api/tally/inbound/masters/ledgers` | Sync ledgers → auto-maps Clients/Vendors |
| POST | `/api/tally/inbound/masters/stock-groups` | Sync stock groups |
| POST | `/api/tally/inbound/masters/stock-categories` | Sync stock categories |
| POST | `/api/tally/inbound/masters/stock-items` | Sync stock items → auto-maps Products |
| POST | `/api/tally/inbound/vouchers/sales` | Sync Sales vouchers → auto-maps Invoices |
| POST | `/api/tally/inbound/vouchers/purchase` | Sync Purchase vouchers |
| POST | `/api/tally/inbound/vouchers/credit-note` | Sync Credit Notes |
| POST | `/api/tally/inbound/vouchers/debit-note` | Sync Debit Notes |
| POST | `/api/tally/inbound/vouchers/receipt` | Sync Receipt vouchers |
| POST | `/api/tally/inbound/vouchers/payment` | Sync Payment vouchers |
| POST | `/api/tally/inbound/vouchers/contra` | Sync Contra vouchers |
| POST | `/api/tally/inbound/vouchers/journal` | Sync Journal vouchers |
| POST | `/api/tally/inbound/reports/balance-sheet` | Store Balance Sheet snapshot |
| POST | `/api/tally/inbound/reports/profit-loss` | Store P&L snapshot |
| POST | `/api/tally/inbound/reports/cash-flow` | Store Cash Flow snapshot |
| POST | `/api/tally/inbound/reports/ratio-analysis` | Store Ratio Analysis snapshot |

### Outbound GET — `Authorization: Bearer <token>`

| Method | Path | Description |
|---|---|---|
| GET | `/api/MastersAPI/ledger-group` | Fetch all ledger groups |
| GET | `/api/MastersAPI/ledger-master` | Fetch all ledgers |
| GET | `/api/MastersAPI/stock-master` | Fetch all stock items |
| GET | `/api/MastersAPI/stock-group` | Fetch all stock groups |
| GET | `/api/MastersAPI/stock-category` | Fetch all stock categories |
| GET | `/api/VoucherAPI/sales-voucher` | Fetch Sales vouchers |
| GET | `/api/VoucherAPI/purchase-voucher` | Fetch Purchase vouchers |
| GET | `/api/VoucherAPI/debitNote-voucher` | Fetch Debit Note vouchers |
| GET | `/api/VoucherAPI/creditNote-voucher` | Fetch Credit Note vouchers |

### Confirmation POST — `Authorization: Bearer <token>`

| Method | Path | Description |
|---|---|---|
| POST | `/api/MastersAPI/update-ledger-master/{companyId}` | Write back Tally ID to ledger |
| POST | `/api/MastersAPI/update-stock-master/{companyId}` | Write back Tally ID to stock item |
| POST | `/api/MastersAPI/update-ledger-group/{companyId}` | Write back Tally ID to ledger group |
| POST | `/api/MastersAPI/update-stock-group/{companyId}` | Write back Tally ID to stock group |
| POST | `/api/MastersAPI/update-stock-category/{companyId}` | Write back Tally ID to stock category |
| POST | `/api/VoucherAPI/update-sales-voucher/{companyId}` | Write back Tally ID to Sales voucher |
| POST | `/api/VoucherAPI/update-purchase-voucher/{companyId}` | Write back Tally ID to Purchase voucher |
| POST | `/api/VoucherAPI/update-debitnote-voucher/{companyId}` | Write back Tally ID to Debit Note |
| POST | `/api/VoucherAPI/update-creditnote-voucher/{companyId}` | Write back Tally ID to Credit Note |
