# Tally Connector — API Reference

Complete request/response reference for all 63 Accobot-Tally API endpoints.  
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
| Boolean fields | `"Yes"` / `"No"` strings. `"Applicable"` / `"Not Applicable"` also accepted (Tally's GST field format). Also accepts `true`/`false`/`"1"`/`"0"` |
| Dates | `"YYYYMMDD"` (real connector format, e.g. `"20250401"`) or `"YYYY-MM-DD"` — both accepted |
| Numeric | Plain number or numeric string |
| ID field | Real connector sends `TallyId` for masters and `MasterID` for vouchers. `ID`/`Id` also accepted |
| `AlterID` | Tally's alter/version ID. `AlterId` also accepted |
| `Action` | `"Create"` (default) or `"Delete"` (soft-deletes the record) |
| `full_sync` | When `true`, any record **not** present in the payload is marked inactive |
| EOT prefix | Tally prefixes some strings with ASCII `\u0004` (EOT). Accobot strips this automatically |
| Field name variants | Real connector uses underscored variants (`GSTIN_Number`, `Mobile_Number`, `Voucher_Total`, etc.) and lowercase keys (`ledgerentries`). Both forms accepted — see L7 in `tally-integration.md` |

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
      "TallyId": 1,
      "AlterID": 42,
      "Action": "Create",
      "Name": "Sundry Debtors",
      "UnderId": 0,
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
| `TallyId` | integer | yes | Tally's internal group ID (also accepted as `ID`/`Id`) |
| `AlterID` | integer | yes | Tally's alter/version ID — used for skip logic (also `AlterId`) |
| `Action` | string | no | `"Create"` (default) or `"Delete"` |
| `Name` | string | yes | Group name |
| `UnderId` | integer | no | Parent group's Tally ID (also `UnderID`) |
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
      "TallyId": 101,
      "AlterID": 205,
      "Action": "Create",
      "LedgerName": "BLUE STAR LIMITED",
      "Group": "Sundry Debtors",
      "ParentGroup": "Current Assets",
      "IsBillWiseOn": "Yes",
      "InventoryAffected": false,
      "IsCostCentreApplicable": "No",
      "GSTIN_Number": "21AAACB4487D1Z4",
      "PAN_Number": "AAACB4487D",
      "TAN_Number": null,
      "GST_Type": "Regular",
      "IsRCMApplicable": "No",
      "MailingName": "BLUE STAR LIMITED",
      "Mobile_Number": "9358444502",
      "ContactPerson": "Akash",
      "ContactPerson_Email": "abc@gmail.com",
      "ContactPerson_EmailCC": null,
      "ContactPerson_Fax": null,
      "ContactPerson_Website": null,
      "ContactPerson_Mobile": "9358444502",
      "LedgerAddress": [
        { "Address": "Add1" },
        { "Address": "Add2" }
      ],
      "StateName": "Odisha",
      "CountryName": "India",
      "PinCode": "752101",
      "CreditPeriod": 120,
      "CreditLimit": 120000,
      "Opening_Balance": 100000.00,
      "Opening_Balance_Type": "Dr",
      "BankDetails": [
        {
          "BankName": "Icici Bank",
          "IFSCode": "ICIC0001234",
          "AccountNumber": "1234567890",
          "PaymentFavouring": "BLUE STAR LIMITED",
          "TransactionName": "Primary",
          "TransactionType": "Inter Bank Transfer"
        }
      ],
      "Aliases": [
        { "Alias": "BLUE STAR LIMITED" },
        { "Alias": "Ledger Alias" }
      ],
      "Description": "Ledger desc",
      "Notes": "Ledger notes"
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `full_sync` | boolean | no | If `true`, ledgers absent from payload are marked inactive |
| `Data` | array | yes | Array of ledger objects |
| `TallyId` | integer | yes | Tally ledger ID (also accepted as `ID`/`Id`) |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `LedgerName` | string | yes | Ledger name (also accepted as `Name`) |
| `Group` | string | no | Immediate group — used to derive category (also `GroupName`) |
| `ParentGroup` | string | no | Top-level group — used to derive category |
| `IsBillWiseOn` | string | no | Bill-by-bill tracking. Defaults to `false` if omitted |
| `InventoryAffected` | string/bool | no | Affects inventory. Defaults to `false` if omitted |
| `IsCostCentreApplicable` | string | no | Cost centre on. Defaults to `false` if omitted |
| `GSTIN_Number` | string | no | GST registration number (also `GSTINNumber`) |
| `PAN_Number` | string | no | PAN (also `PANNumber`) |
| `TAN_Number` | string | no | TAN (also `TANNumber`) |
| `GST_Type` | string | no | `"Regular"`, `"Composition"`, `"Unregistered"`, etc. (also `GSTType`) |
| `IsRCMApplicable` | string | no | Reverse Charge Mechanism |
| `MailingName` | string | no | Display / mailing name |
| `Mobile_Number` | string | no | Primary mobile (also `MobileNumber`) |
| `ContactPerson` | string | no | Contact name |
| `ContactPerson_Email` | string | no | Contact email (also `ContactPersonEmail`) |
| `ContactPerson_EmailCC` | string | no | CC email (also `ContactPersonEmailCC`) |
| `ContactPerson_Fax` | string | no | Fax (also `ContactPersonFax`) |
| `ContactPerson_Website` | string | no | Website (also `ContactPersonWebsite`) |
| `ContactPerson_Mobile` | string | no | Contact mobile (also `ContactPersonMobile`) |
| `LedgerAddress` | array | no | Array of `{"Address": "..."}` objects (also `Addresses`) |
| `StateName` | string | no | State |
| `CountryName` | string | no | Country |
| `PinCode` | string | no | PIN / postal code |
| `CreditPeriod` | integer | no | Credit period in days |
| `CreditLimit` | float | no | Credit limit amount |
| `Opening_Balance` | float | no | Opening balance value (also `OpeningBalance`) |
| `Opening_Balance_Type` | string | no | `"Dr"` or `"Cr"` (also `OpeningBalanceType`) |
| `BankDetails` | array | no | Bank account array |
| `Aliases` | array | no | Array of `{"Alias": "..."}` objects |
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
      "TallyId": 201,
      "AlterID": 88,
      "Action": "Create",
      "Name": "30Mbps Lease Line",
      "Description": "Item desc",
      "Remarks": null,
      "Aliases": [
        { "Alias": "30Mbps Lease Line" },
        { "Alias": "Item Alias" }
      ],
      "StockGroupID": 10,
      "StockGroupName": "Network Equipment",
      "StockCategoryID": 5,
      "Category": "Lease Line Services",
      "UnitID": 234,
      "Unit": "NOS",
      "AlternateUnit": null,
      "Conversion": 0,
      "Denominator": 1,
      "IsGSTApplicable": "Applicable",
      "Taxablity": "Taxable",
      "CalculationType": "",
      "IGST_Rate": 18,
      "SGST_Rate": 9,
      "CGST_Rate": 9,
      "CESS_Rate": 0,
      "HSNCode": 998415,
      "MRPRate": 0,
      "StandardCost": 0,
      "StandardPrice": 15000,
      "Opening_Balance": 25,
      "Opening_Rate": 12.00,
      "Opening_Value": 300.00,
      "Closing_Balance": 26,
      "Closing_Rate": 93.02,
      "Closing_Value": 2418.64,
      "CostingMethod": "FIFO",
      "IsBatchApplicable": "No",
      "IsExpiryDateApplicable": "No",
      "ReorderLevel": null,
      "ReorderQuantity": null,
      "MaximumQuantity": null,
      "BatchAllocations": [
        {
          "GodownName": "Main Location",
          "GodownID": 99,
          "OpeningBalnace": 25,
          "Rate": 12,
          "OpeningValue": 300
        }
      ]
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `full_sync` | boolean | no | Mark absent items inactive |
| `TallyId` | integer | yes | Tally stock item ID (also `ID`/`Id`) |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Item name |
| `Description` | string | no | Description |
| `Remarks` | string | no | Remarks |
| `Aliases` | array | no | Array of `{"Alias": "..."}` objects |
| `StockGroupID` | integer | no | Tally stock group ID |
| `StockGroupName` | string | no | Stock group name |
| `StockCategoryID` | integer | no | Tally category ID |
| `Category` | string | no | Category name (also `CategoryName`) |
| `UnitID` | integer | no | Unit of measure ID |
| `Unit` | string | no | Unit name e.g. `"NOS"`, `"KGS"` (also `UnitName`) |
| `AlternateUnit` | string | no | Alternate unit |
| `Conversion` | float | no | Conversion factor |
| `Denominator` | integer | no | Denominator for conversion |
| `IsGSTApplicable` | string | no | `"Applicable"` / `"Not Applicable"` (also `"Yes"` / `"No"`) |
| `Taxablity` | string | no | `"Taxable"`, `"Exempt"`, `"Nil Rated"` — note Tally's spelling (also `Taxability`) |
| `CalculationType` | string | no | GST calculation method |
| `IGST_Rate` | float | no | IGST % (also `IGSTRate`) |
| `SGST_Rate` | float | no | SGST % (also `SGSTRate`) |
| `CGST_Rate` | float | no | CGST % (also `CGSTRate`) |
| `CESS_Rate` | float | no | Cess % (also `CessRate`) |
| `HSNCode` | string/integer | no | HSN / SAC code |
| `MRPRate` | float | no | MRP |
| `StandardCost` | float | no | Standard cost |
| `StandardPrice` | float | no | Standard selling price |
| `Opening_Balance` | float | no | Opening stock quantity (also `OpeningBalance`) |
| `Opening_Rate` | float | no | Opening rate (also `OpeningRate`) |
| `Opening_Value` | float | no | Opening value (also `OpeningValue`) |
| `Closing_Balance` | float | no | Closing stock quantity (also `ClosingBalance`) |
| `Closing_Rate` | float | no | Closing rate (also `ClosingRate`) |
| `Closing_Value` | float | no | Closing value (also `ClosingValue`) |
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

### 1.6 POST `/api/tally/inbound/masters/statutory`

Syncs Tally statutory registrations — GST, TDS, TCS, PF, ESI, PT, etc.

**Request Body**

```json
{
  "Data": [
    {
      "ID": 301,
      "AlterID": 120,
      "Action": "Create",
      "Name": "GST - Maharashtra",
      "StatutoryType": "GST",
      "RegistrationNumber": "27AABCT1234A1Z5",
      "StateCode": "27",
      "RegistrationType": "Regular",
      "PAN": "AABCT1234A",
      "TAN": null,
      "ApplicableFrom": "2017-07-01"
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `ID` | integer | yes | Tally's internal ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Registration name |
| `StatutoryType` | string | no | `"GST"`, `"TDS"`, `"TCS"`, `"PF"`, `"ESI"`, `"PT"` |
| `RegistrationNumber` | string | no | GSTIN, TAN, PF reg number, etc. |
| `StateCode` | string | no | State code (for GST) |
| `RegistrationType` | string | no | `"Regular"`, `"Composition"`, etc. |
| `PAN` | string | no | PAN linked to this registration |
| `TAN` | string | no | TAN (for TDS/TCS) |
| `ApplicableFrom` | string | no | Date `"YYYY-MM-DD"` — effective from |
| *(any other fields)* | mixed | no | Stored in `details` jsonb |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

## 1b. Inbound: Payroll (Tally → Accobot)

---

### 1b.1 POST `/api/tally/inbound/payroll/employee-groups`

Syncs Tally employee group hierarchy.

**Request Body**

```json
{
  "Data": [
    {
      "ID": 401,
      "AlterID": 10,
      "Action": "Create",
      "Name": "Management",
      "ParentName": null
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `ID` | integer | yes | Tally group ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Group name |
| `ParentName` | string | no | Parent group name |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 1b.2 POST `/api/tally/inbound/payroll/employees`

Syncs Tally employee masters with full payroll and statutory details.

**Request Body**

```json
{
  "full_sync": false,
  "Data": [
    {
      "ID": 501,
      "AlterID": 200,
      "Action": "Create",
      "Name": "Arjun Mehta",
      "EmployeeNumber": "EMP001",
      "GroupName": "Management",
      "Designation": "General Manager",
      "Function": "Administration",
      "Department": "Corporate",
      "DateOfJoining": "2018-04-01",
      "DateOfLeaving": null,
      "DateOfBirth": "1985-06-15",
      "Gender": "Male",
      "PAN": "ABCPM1234A",
      "AadharNumber": "123456789012",
      "PFNumber": "MH/BAN/0123456/001",
      "UANNumber": "100123456789",
      "ESINumber": "1234567890",
      "BankName": "HDFC Bank",
      "BankAccountNumber": "50100123456789",
      "BankIFSC": "HDFC0001234",
      "Addresses": ["123 Main Street", "Mumbai - 400001"],
      "SalaryDetails": [
        { "PayHead": "Basic Salary", "Amount": 50000, "RatePeriod": "Monthly" }
      ]
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `full_sync` | boolean | no | Mark absent employees inactive |
| `ID` | integer | yes | Tally employee ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Employee name |
| `EmployeeNumber` | string | no | Employee number / code |
| `GroupName` | string | no | Employee group |
| `Designation` | string | no | Job title |
| `Function` | string | no | Function / role (stored as `employee_function`) |
| `Department` | string | no | Department |
| `DateOfJoining` | string | no | `"YYYY-MM-DD"` |
| `DateOfLeaving` | string | no | `"YYYY-MM-DD"` or null |
| `DateOfBirth` | string | no | `"YYYY-MM-DD"` |
| `Gender` | string | no | `"Male"` / `"Female"` / `"Other"` |
| `PAN` | string | no | PAN number |
| `AadharNumber` | string | no | Aadhaar number |
| `PFNumber` | string | no | PF registration number |
| `UANNumber` | string | no | UAN number |
| `ESINumber` | string | no | ESI number |
| `BankName` | string | no | Bank name |
| `BankAccountNumber` | string | no | Bank account number |
| `BankIFSC` | string | no | IFSC code |
| `Addresses` | array | no | Address lines array |
| `SalaryDetails` | array | no | Pay head / salary structure details |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 1b.3 POST `/api/tally/inbound/payroll/pay-heads`

Syncs Tally pay heads (salary components — earnings, deductions, statutory).

**Request Body**

```json
{
  "Data": [
    {
      "ID": 601,
      "AlterID": 30,
      "Action": "Create",
      "Name": "Basic Salary",
      "PayHeadType": "Earning",
      "PaySlipName": "Basic",
      "UnderGroup": "Indirect Expenses",
      "LedgerName": "Basic Salary Payable",
      "CalculationType": "As Computed Value",
      "Rate": null,
      "RatePeriod": "Monthly"
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `ID` | integer | yes | Tally pay head ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Pay head name |
| `PayHeadType` | string | no | `"Earning"`, `"Deduction"`, `"Employer Contributions"`, `"Statutory Deductions"` |
| `PaySlipName` | string | no | Label on pay slip |
| `UnderGroup` | string | no | Parent ledger group |
| `LedgerName` | string | no | Linked Tally ledger |
| `CalculationType` | string | no | `"As Computed Value"`, `"Flat Rate"`, `"On Attendance"`, etc. |
| `Rate` | float | no | Fixed rate (for Flat Rate type) |
| `RatePeriod` | string | no | `"Daily"`, `"Monthly"`, `"Annually"` |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

### 1b.4 POST `/api/tally/inbound/payroll/attendance-types`

Syncs Tally attendance and leave type definitions.

**Request Body**

```json
{
  "Data": [
    {
      "ID": 701,
      "AlterID": 5,
      "Action": "Create",
      "Name": "Present",
      "AttendanceType": "Attendance",
      "UnitOfMeasure": "Days"
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `ID` | integer | yes | Tally attendance type ID |
| `AlterID` | integer | yes | Alter/version ID |
| `Action` | string | no | `"Create"` or `"Delete"` |
| `Name` | string | yes | Type name |
| `AttendanceType` | string | no | `"Attendance"`, `"Leave with Pay"`, `"Leave without Pay"`, `"Productivity"` |
| `UnitOfMeasure` | string | no | `"Days"`, `"Hours"`, `"Pieces"` |

**Response**

```json
{ "status": "success", "created": 1, "updated": 0, "skipped": 0, "failed": 0 }
```

---

## 2. Inbound: Vouchers (Tally → Accobot)

All voucher endpoints share the same request structure and response. The key differences are:
- **Sales** vouchers: auto-creates/updates an **Invoice** in Accobot. Client is resolved via the party ledger; if the ledger hasn't synced yet, a placeholder Client is created from the voucher's buyer fields and linked later when the ledger arrives. Inventory entry products are similarly auto-created if no matching stock item exists yet.
- **Receipt / Payment / Contra / Journal**: stored in `tally_vouchers` only, no Accobot operational record created

### Common Voucher Fields

Every voucher object supports:

| Field | Type | Description |
|---|---|---|
| `MasterID` | integer | Tally's voucher master ID (also `ID` / `Id`) |
| `AlterID` | integer | Alter/version ID |
| `Action` | string | `"Create"` or `"Delete"` |
| `VoucherType` | string | Set automatically by endpoint (`"Sales"`, `"Receipt"`, etc.) |
| `VoucherNumber` | string | Voucher number (e.g. `"1"`, `"2024-25/001"`) |
| `VoucherDate` | string | Date in `"YYYYMMDD"` format (also `"YYYY-MM-DD"`) |
| `Reference` | string | Reference number |
| `ReferenceDate` | string | Reference date |
| `PartyName` | string | Party ledger name — resolved to `TallyLedger` FK |
| `Voucher_Total` | float | Total voucher amount (also `VoucherTotal`) |
| `IsInvoice` | string | `"Yes"` / `"No"` |
| `IsDeleted` | string | `"Yes"` / `"No"` — Tally deletion flag |
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
| `BuyerCountryName` | string | Buyer country (also `BuyerCountry`) |
| `BuyerGSTRegistrationType` | string | GST reg type |
| `BuyerEmail` | string | Buyer email |
| `BuyerMobile` | string | Buyer mobile |
| `BuyerAddress` | array | Array of `{"BuyerAddress": "..."}` objects |
| `ConsigneeName` | string | Consignee name |
| `ConsigneeGSTIN` | string | Consignee GSTIN |
| `ConsigneeTallyGroup` | string | Consignee group |
| `ConsigneePinCode` | string | Consignee PIN |
| `ConsigneeState` | string | Consignee state |
| `ConsigneeCountryName` | string | Consignee country (also `ConsigneeCountry`) |
| `ConsigneeGSTRegistrationType` | string | Consignee GST reg type |
| `IRN` | string | Invoice Reference Number (e-invoice) |
| `AcknowledgementNo` | string | IRN acknowledgement number |
| `AcknowledgementDate` | string | Acknowledgement date |
| `QRCode` | string | QR code string |
| `Narration` | string | Voucher narration |
| `VoucherCostCentre` | string | Cost centre (also `CostCentre`) |
| `InventoryEntries` | array | Line items — see below |
| `ledgerentries` | array | Accounting entries — see below (also `LedgerEntries`) |

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

### ledgerentries (accounting entries)

The real connector sends this key as lowercase `ledgerentries`. `LedgerEntries` is also accepted.

| Field | Type | Description |
|---|---|---|
| `LedgerName` | string | Ledger name — resolved to `TallyLedger` FK |
| `LedgerGroup` | string | Ledger group |
| `LedgerAmount` | float | Amount |
| `IsDeemedPositive` | string | `"Yes"` / `"No"` |
| `IsPartyLedger` | string | `"Yes"` / `"No"` |
| `IGSTRate` | string | IGST rate |
| `HSNCode` | string | HSN code |
| `Cess_Rate` | string | Cess rate (also `CessRate`) |
| `BillsAllocation` | array | Bill-wise allocation details (agst ref / on account) |
| `BankAllocationDetails` | array | Bank transfer details — stored alongside `BillsAllocation` |

---

### 2.1 POST `/api/tally/inbound/vouchers/sales`

**Sales invoice** — auto-creates an Accobot Invoice. If the party ledger hasn't synced yet, a placeholder Client is created from buyer fields (`BuyerName`/`PartyName`, GSTIN, email, mobile, address) and linked later. Missing stock items are similarly auto-created as placeholder Products.

**Request Body**

```json
{
  "full_sync": false,
  "data": [
    {
      "MasterID": 5001,
      "AlterID": 300,
      "Action": "Create",
      "VoucherNumber": "1",
      "VoucherDate": "20250401",
      "PartyName": "BLUE STAR LIMITED",
      "VoucherType": "Sales",
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
      "Narration": "",
      "VoucherCostCentre": "",
      "InventoryEntries": [
        {
          "StockItemName": "Supply of Goods Transport Service",
          "ItemCode": "",
          "GroupName": "Primary",
          "HSNCode": "996511",
          "Unit": "Not Applicable",
          "IGSTRate": 18,
          "CessRate": 0.00,
          "IsDeemedPositive": "No",
          "ActualQty": 0,
          "BilledQty": 0,
          "Rate": 0,
          "DiscountPercent": 0,
          "Amount": 30478.00,
          "TaxAmount": 5486.04,
          "SalesLedger": "Transportation Charges",
          "GodownName": "Main Location",
          "BatchName": "Primary Batch",
          "BatchAllocations": [
            {
              "BatchName": "Primary Batch",
              "GodownName": "Main Location",
              "ActualQty": 0,
              "BilledQty": 0,
              "Rate": 0,
              "DiscountPercent": 0,
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
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BillsAllocation": [
            { "AgstType": "New Ref", "Reference": "1", "CreditPeriod": "120 Days", "Amount": -35964.04 }
          ]
        },
        {
          "LedgerName": "CGST OUTPUT",
          "LedgerAmount": 2743.02,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BillsAllocation": [
            { "AgstType": "On Account", "Reference": "", "CreditPeriod": "", "Amount": 2743.02 }
          ]
        },
        {
          "LedgerName": "SGST OUTPUT",
          "LedgerAmount": 2743.02,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BillsAllocation": [
            { "AgstType": "On Account", "Reference": "", "CreditPeriod": "", "Amount": 2743.02 }
          ]
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
  "data": [
    {
      "MasterID": 6001,
      "AlterID": 410,
      "Action": "Create",
      "VoucherNumber": "PUR/2024-25/001",
      "VoucherDate": "20240405",
      "PartyName": "PUNJAB NATIONAL BANK",
      "VoucherType": "Purchase",
      "Voucher_Total": 50000.00,
      "IsInvoice": "Yes",
      "IsDeleted": "No",
      "Narration": "Bandwidth purchase",
      "VoucherCostCentre": "",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "InventoryEntries": [],
      "ledgerentries": [
        {
          "LedgerName": "PUNJAB NATIONAL BANK",
          "LedgerAmount": -50000,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": ""
        },
        {
          "LedgerName": "Purchase - Bandwidth",
          "LedgerAmount": 50000,
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": ""
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
  "data": [
    {
      "MasterID": 7001,
      "AlterID": 500,
      "Action": "Create",
      "VoucherNumber": "CN/2024-25/001",
      "VoucherDate": "20240410",
      "PartyName": "BLUE STAR LIMITED",
      "VoucherType": "Credit Note",
      "Voucher_Total": 5900,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "Partial credit for service downtime",
      "VoucherCostCentre": "",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "InventoryEntries": [],
      "ledgerentries": [
        {
          "LedgerName": "BLUE STAR LIMITED",
          "LedgerAmount": -5900,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": ""
        },
        {
          "LedgerName": "Sales - Lease Line",
          "LedgerAmount": 5000,
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": ""
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
  "data": [
    {
      "MasterID": 13,
      "AlterID": 35,
      "Action": "Update",
      "VoucherNumber": "3",
      "VoucherDate": "20250502",
      "PartyName": "LXPANTOS LOGISTIC SOLUTION INDIA PVT LTD",
      "VoucherType": "Receipt",
      "Voucher_Total": 1288538.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "BEING AMOUNT RECEIVED",
      "VoucherCostCentre": "",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "InventoryEntries": [],
      "ledgerentries": [
        {
          "LedgerName": "LXPANTOS LOGISTIC SOLUTION INDIA PVT LTD",
          "LedgerAmount": 1288538,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BillsAllocation": [
            { "AgstType": "Agst Ref", "Reference": "3", "CreditPeriod": "", "Amount": 1288538 }
          ]
        },
        {
          "LedgerName": "PUNJAB NATIONAL BANK STARLINE EXPRESS CC ACCOUNT (19194025001909)",
          "LedgerAmount": 1288538,
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BankAllocationDetails": [
            {
              "Date": "2025-05-02",
              "InstrumentDate": "2025-05-02",
              "TRANSACTIONTYPE": "Same Bank Transfer",
              "IFSCODE": "",
              "BANKNAME": "",
              "ACCOUNTNUMBER": "",
              "PAYMENTFAVOURING": "LXPANTOS LOGISTIC SOLUTION INDIA PVT LTD",
              "TRANSFERMODE": "",
              "INSTRUMENTNUMBER": "",
              "AMOUNT": "12,88,538.00",
              "BankersDate": ""
            }
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
  "data": [
    {
      "MasterID": 25,
      "AlterID": 30,
      "Action": "Update",
      "VoucherNumber": "7",
      "VoucherDate": "20250401",
      "PartyName": "Cash",
      "VoucherType": "Payment",
      "Voucher_Total": 10500.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "BEING CASH PAID FOR GROCERY MESS",
      "VoucherCostCentre": "",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "InventoryEntries": [],
      "ledgerentries": [
        {
          "LedgerName": "STAFF FOODING STARLINE CUTTACK BRANCH",
          "LedgerAmount": 10500,
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "0"
        },
        {
          "LedgerName": "Cash",
          "LedgerAmount": 10500,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": ""
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
  "data": [
    {
      "MasterID": 27,
      "AlterID": 29,
      "Action": "Create",
      "VoucherNumber": "2",
      "VoucherDate": "20250402",
      "PartyName": "PUNJAB NATIONAL BANK STARLINE EXPRESS CC ACCOUNT (19194025001909)",
      "VoucherType": "Contra",
      "Voucher_Total": 50000.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "BEING AMOUNT CASH WDL",
      "VoucherCostCentre": "",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "InventoryEntries": [],
      "ledgerentries": [
        {
          "LedgerName": "PUNJAB NATIONAL BANK STARLINE EXPRESS CC ACCOUNT (19194025001909)",
          "LedgerAmount": 50000,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BankAllocationDetails": [
            {
              "Date": "2025-04-02",
              "InstrumentDate": "2025-04-02",
              "TRANSACTIONTYPE": "Same Bank Transfer",
              "IFSCODE": "",
              "BANKNAME": "",
              "ACCOUNTNUMBER": "",
              "PAYMENTFAVOURING": "Self",
              "TRANSFERMODE": "",
              "INSTRUMENTNUMBER": "",
              "AMOUNT": "50,000.00",
              "BankersDate": ""
            }
          ]
        },
        {
          "LedgerName": "Cash",
          "LedgerAmount": 50000,
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": ""
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
  "data": [
    {
      "MasterID": 17,
      "AlterID": 38,
      "Action": "Update",
      "VoucherNumber": "5",
      "VoucherDate": "20250401",
      "PartyName": "",
      "VoucherType": "Journal",
      "Voucher_Total": 15000.00,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "BEING DAILY WAGES FOR THE MONTH OF APRIL-25",
      "VoucherCostCentre": "",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "InventoryEntries": [],
      "ledgerentries": [
        {
          "LedgerName": "Daily Wages",
          "LedgerAmount": 15000,
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "No",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BillsAllocation": [
            { "AgstType": "On Account", "Reference": "", "CreditPeriod": "", "Amount": -15000 }
          ]
        },
        {
          "LedgerName": "SATYAJIT DAS",
          "LedgerAmount": 15000,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": "",
          "BillsAllocation": [
            { "AgstType": "On Account", "Reference": "", "CreditPeriod": "", "Amount": 15000 }
          ]
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
  "data": [
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

### 4.6 GET `/api/MastersAPI/statutory-master`

Returns all active statutory masters.

**Response**

```json
{
  "Data": [
    {
      "ID": 301,
      "AlterID": 120,
      "Action": "Create",
      "Name": "GST - Maharashtra",
      "StatutoryType": "GST",
      "RegistrationNumber": "27AABCT1234A1Z5",
      "StateCode": "27",
      "RegistrationType": "Regular",
      "PAN": "AABCT1234A",
      "TAN": null,
      "ApplicableFrom": "2017-07-01",
      "Details": []
    }
  ]
}
```

---

## 4b. Outbound: Payroll (Tally reads Accobot)

---

### 4b.1 GET `/api/PayrollAPI/employee-group`

Returns all active employee groups.

**Response**

```json
{
  "Data": [
    {
      "ID": 401,
      "AlterID": 10,
      "Action": "Create",
      "Name": "Management",
      "ParentName": null
    }
  ]
}
```

---

### 4b.2 GET `/api/PayrollAPI/employee`

Returns all active employees with full payroll details.

**Response**

```json
{
  "Data": [
    {
      "ID": 501,
      "AlterID": 200,
      "Action": "Create",
      "Name": "Arjun Mehta",
      "EmployeeNumber": "EMP001",
      "GroupName": "Management",
      "Designation": "General Manager",
      "Function": "Administration",
      "Department": "Corporate",
      "DateOfJoining": "2018-04-01",
      "DateOfLeaving": null,
      "DateOfBirth": "1985-06-15",
      "Gender": "Male",
      "PAN": "ABCPM1234A",
      "AadharNumber": "123456789012",
      "PFNumber": "MH/BAN/0123456/001",
      "UANNumber": "100123456789",
      "ESINumber": "1234567890",
      "BankName": "HDFC Bank",
      "BankAccountNumber": "50100123456789",
      "BankIFSC": "HDFC0001234",
      "Addresses": ["123 Main Street", "Mumbai - 400001"],
      "SalaryDetails": []
    }
  ]
}
```

---

### 4b.3 GET `/api/PayrollAPI/pay-head`

Returns all active pay heads.

**Response**

```json
{
  "Data": [
    {
      "ID": 601,
      "AlterID": 30,
      "Action": "Create",
      "Name": "Basic Salary",
      "PayHeadType": "Earning",
      "PaySlipName": "Basic",
      "UnderGroup": "Indirect Expenses",
      "LedgerName": "Basic Salary Payable",
      "CalculationType": "As Computed Value",
      "Rate": null,
      "RatePeriod": "Monthly"
    }
  ]
}
```

---

### 4b.4 GET `/api/PayrollAPI/attendance-type`

Returns all active attendance types.

**Response**

```json
{
  "Data": [
    {
      "ID": 701,
      "AlterID": 5,
      "Action": "Create",
      "Name": "Present",
      "AttendanceType": "Attendance",
      "UnitOfMeasure": "Days"
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

### 5.5 GET `/api/VoucherAPI/receipt-voucher`

Returns all active Receipt vouchers. `"VoucherType": "Receipt"`. LedgerEntries describe the party and bank/cash accounts; `InventoryEntries` is always `[]`.

---

### 5.6 GET `/api/VoucherAPI/payment-voucher`

Returns all active Payment vouchers. `"VoucherType": "Payment"`. Same structure as Receipt.

---

### 5.7 GET `/api/VoucherAPI/contra-voucher`

Returns all active Contra vouchers. `"VoucherType": "Contra"`. Used for inter-bank / cash-bank transfers.

---

### 5.8 GET `/api/VoucherAPI/journal-voucher`

Returns all active Journal vouchers. `"VoucherType": "Journal"`. Adjustment and expense entries.

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

### 6.10 POST `/api/VoucherAPI/update-receipt-voucher/{companyId}`

Updates `tally_vouchers` (Receipt type).

---

### 6.11 POST `/api/VoucherAPI/update-payment-voucher/{companyId}`

Updates `tally_vouchers` (Payment type).

---

### 6.12 POST `/api/VoucherAPI/update-contra-voucher/{companyId}`

Updates `tally_vouchers` (Contra type).

---

### 6.13 POST `/api/VoucherAPI/update-journal-voucher/{companyId}`

Updates `tally_vouchers` (Journal type).

---

### 6.14 POST `/api/MastersAPI/update-statutory-master/{companyId}`

Updates `tally_statutory_masters` with Tally-assigned ID.

```json
{
  "Data": [
    { "Id": "uuid", "TallyId": 301, "IsSynced": false }
  ]
}
```

---

### 6.15 POST `/api/PayrollAPI/update-employee-group/{companyId}`

Updates `tally_employee_groups` with Tally-assigned ID.

```json
{
  "Data": [
    { "Id": "uuid", "TallyId": 401, "IsSynced": false }
  ]
}
```

---

### 6.16 POST `/api/PayrollAPI/update-employee/{companyId}`

Updates `tally_employees` with Tally-assigned ID.

```json
{
  "Data": [
    { "Id": "uuid", "TallyId": 501, "IsSynced": true }
  ]
}
```

---

### 6.17 POST `/api/PayrollAPI/update-pay-head/{companyId}`

Updates `tally_pay_heads` with Tally-assigned ID.

```json
{
  "Data": [
    { "Id": "uuid", "TallyId": 601, "IsSynced": false }
  ]
}
```

---

### 6.18 POST `/api/PayrollAPI/update-attendance-type/{companyId}`

Updates `tally_attendance_types` with Tally-assigned ID.

```json
{
  "Data": [
    { "Id": "uuid", "TallyId": 701, "IsSynced": false }
  ]
}
```

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

## 9. Quick Reference — All 63 Endpoints

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
| POST | `/api/tally/inbound/masters/statutory` | Sync Statutory masters (GST/TDS/TCS) |
| POST | `/api/tally/inbound/payroll/employee-groups` | Sync Employee groups |
| POST | `/api/tally/inbound/payroll/employees` | Sync Employees |
| POST | `/api/tally/inbound/payroll/pay-heads` | Sync Pay heads (salary components) |
| POST | `/api/tally/inbound/payroll/attendance-types` | Sync Attendance types |
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
| GET | `/api/VoucherAPI/receipt-voucher` | Fetch Receipt vouchers |
| GET | `/api/VoucherAPI/payment-voucher` | Fetch Payment vouchers |
| GET | `/api/VoucherAPI/contra-voucher` | Fetch Contra vouchers |
| GET | `/api/VoucherAPI/journal-voucher` | Fetch Journal vouchers |
| GET | `/api/MastersAPI/statutory-master` | Fetch Statutory masters |
| GET | `/api/PayrollAPI/employee-group` | Fetch Employee groups |
| GET | `/api/PayrollAPI/employee` | Fetch Employees |
| GET | `/api/PayrollAPI/pay-head` | Fetch Pay heads |
| GET | `/api/PayrollAPI/attendance-type` | Fetch Attendance types |

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
| POST | `/api/VoucherAPI/update-receipt-voucher/{companyId}` | Write back Tally ID to Receipt voucher |
| POST | `/api/VoucherAPI/update-payment-voucher/{companyId}` | Write back Tally ID to Payment voucher |
| POST | `/api/VoucherAPI/update-contra-voucher/{companyId}` | Write back Tally ID to Contra voucher |
| POST | `/api/VoucherAPI/update-journal-voucher/{companyId}` | Write back Tally ID to Journal voucher |
| POST | `/api/MastersAPI/update-statutory-master/{companyId}` | Write back Tally ID to Statutory master |
| POST | `/api/PayrollAPI/update-employee-group/{companyId}` | Write back Tally ID to Employee group |
| POST | `/api/PayrollAPI/update-employee/{companyId}` | Write back Tally ID to Employee |
| POST | `/api/PayrollAPI/update-pay-head/{companyId}` | Write back Tally ID to Pay head |
| POST | `/api/PayrollAPI/update-attendance-type/{companyId}` | Write back Tally ID to Attendance type |
