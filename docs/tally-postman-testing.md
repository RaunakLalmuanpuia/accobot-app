# Tally API — Postman Testing Log

Tracks test results for all 63 Tally API endpoints. Full request/response reference is in [`tally-api-reference.md`](./tally-api-reference.md).

---

## Postman Setup

### Environment Variables

Create a Postman environment with these variables:

| Variable | Example Value | Description |
|---|---|---|
| `base_url` | `http://localhost:8000` | Accobot app base URL |
| `tally_token` | `abc123...` (48 chars) | Bearer token from Tally Connection settings |

### Auth (apply to collection)

Set **Authorization** → **Bearer Token** → `{{tally_token}}` at the collection level so all requests inherit it.

### Headers (apply to collection)

```
Content-Type: application/json
Accept: application/json
```

### Company fields (add to all future test payloads)

The connector now sends four company-level fields inside each `Data` record (after the ID). Include them in all new tests so `tally_companies` is populated:

```json
"CompanyGUID": "248b1a3e-7f9f-443c-ae33-1984824e53f7",
"CompanyName": "Acme Corp",
"LicenceType": "Gold",
"LicenceNumber": "TLY-123456"
```

These are optional — if `CompanyGUID` is absent, the upsert is silently skipped.

---

## Endpoint Status

| # | Method | Path | Status |
|---|---|---|---|
| 1 | POST | `/api/tally/inbound/masters/ledger-groups` | ✅ Tested |
| 2 | POST | `/api/tally/inbound/masters/ledgers` | ✅ Tested |
| 3 | POST | `/api/tally/inbound/masters/stock-groups` | ✅ Tested |
| 4 | POST | `/api/tally/inbound/masters/stock-categories` | ✅ Tested |
| 5 | POST | `/api/tally/inbound/masters/stock-items` | ✅ Tested |
| 6 | POST | `/api/tally/inbound/masters/statutory` | ✅ Tested |
| 7 | POST | `/api/tally/inbound/payroll/employee-groups` | ✅ Tested |
| 8 | POST | `/api/tally/inbound/payroll/employees` | ✅ Tested |
| 9 | POST | `/api/tally/inbound/payroll/pay-heads` | ✅ Tested |
| 10 | POST | `/api/tally/inbound/payroll/attendance-types` | ✅ Tested |
| 11 | POST | `/api/tally/inbound/vouchers/sales` | ✅ Tested |
| 12 | POST | `/api/tally/inbound/vouchers/purchase` | ✅ Tested |
| 13 | POST | `/api/tally/inbound/vouchers/credit-note` | ✅ Tested |
| 14 | POST | `/api/tally/inbound/vouchers/debit-note` | ✅ Tested |
| 15 | POST | `/api/tally/inbound/vouchers/receipt` | ✅ Tested |
| 16 | POST | `/api/tally/inbound/vouchers/payment` | ✅ Tested |
| 17 | POST | `/api/tally/inbound/vouchers/contra` | ✅ Tested |
| 18 | POST | `/api/tally/inbound/vouchers/journal` | ✅ Tested |
| 19 | POST | `/api/tally/inbound/reports/balance-sheet` | ✅ Tested |
| 20 | POST | `/api/tally/inbound/reports/profit-loss` | ✅ Tested |
| 21 | POST | `/api/tally/inbound/reports/cash-flow` | ✅ Tested |
| 22 | POST | `/api/tally/inbound/reports/ratio-analysis` | ✅ Tested |
| 23 | POST | `/api/tally/inbound/masters/godowns` | ✅ Tested |
| 24 | POST | `/api/tally/inbound/masters/company` | ⬜ Pending |
| 25 | GET | `/api/MastersAPI/ledger-group` | ⬜ Pending |
| 26 | GET | `/api/MastersAPI/ledger-master` | ⬜ Pending |
| 27 | GET | `/api/MastersAPI/stock-master` | ⬜ Pending |
| 28 | GET | `/api/MastersAPI/stock-group` | ⬜ Pending |
| 29 | GET | `/api/MastersAPI/stock-category` | ⬜ Pending |
| 30 | GET | `/api/MastersAPI/statutory-master` | ⬜ Pending |
| 31 | GET | `/api/PayrollAPI/employee-group` | ⬜ Pending |
| 32 | GET | `/api/PayrollAPI/employee` | ⬜ Pending |
| 33 | GET | `/api/PayrollAPI/pay-head` | ⬜ Pending |
| 34 | GET | `/api/PayrollAPI/attendance-type` | ⬜ Pending |
| 35 | GET | `/api/VoucherAPI/sales-voucher` | ⬜ Pending |
| 36 | GET | `/api/VoucherAPI/purchase-voucher` | ⬜ Pending |
| 37 | GET | `/api/VoucherAPI/debitNote-voucher` | ⬜ Pending |
| 38 | GET | `/api/VoucherAPI/creditNote-voucher` | ⬜ Pending |
| 39 | GET | `/api/VoucherAPI/receipt-voucher` | ⬜ Pending |
| 40 | GET | `/api/VoucherAPI/payment-voucher` | ⬜ Pending |
| 41 | GET | `/api/VoucherAPI/contra-voucher` | ⬜ Pending |
| 42 | GET | `/api/VoucherAPI/journal-voucher` | ⬜ Pending |
| 43 | POST | `/api/MastersAPI/update-ledger-group` | ⬜ Pending |
| 44 | POST | `/api/MastersAPI/update-ledger-master` | ⬜ Pending |
| 45 | POST | `/api/MastersAPI/update-stock-master` | ⬜ Pending |
| 46 | POST | `/api/MastersAPI/update-stock-group` | ⬜ Pending |
| 47 | POST | `/api/MastersAPI/update-stock-category` | ⬜ Pending |
| 48 | POST | `/api/MastersAPI/update-statutory-master` | ⬜ Pending |
| 49 | POST | `/api/PayrollAPI/update-employee-group` | ⬜ Pending |
| 50 | POST | `/api/PayrollAPI/update-employee` | ⬜ Pending |
| 51 | POST | `/api/PayrollAPI/update-pay-head` | ⬜ Pending |
| 52 | POST | `/api/PayrollAPI/update-attendance-type` | ⬜ Pending |
| 53 | POST | `/api/VoucherAPI/update-sales-voucher` | ⬜ Pending |
| 54 | POST | `/api/VoucherAPI/update-purchase-voucher` | ⬜ Pending |
| 55 | POST | `/api/VoucherAPI/update-debitnote-voucher` | ⬜ Pending |
| 56 | POST | `/api/VoucherAPI/update-creditnote-voucher` | ⬜ Pending |
| 57 | POST | `/api/VoucherAPI/update-receipt-voucher` | ⬜ Pending |
| 58 | POST | `/api/VoucherAPI/update-payment-voucher` | ⬜ Pending |
| 59 | POST | `/api/VoucherAPI/update-contra-voucher` | ⬜ Pending |
| 60 | POST | `/api/VoucherAPI/update-journal-voucher` | ⬜ Pending |

---

## Test Results

---

### 1. POST `/api/tally/inbound/masters/ledger-groups` ✅

**Request**
```json
{
  "Data": [
    {
      "TallyId": 1,
      "AlterID": 101,
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Behaviours verified:**
- Record created successfully on first call
- Same payload again → `skipped: 1` (AlterID match)
- `ID: 0` → `failed: 1`
- `Action: "Delete"` → sets `is_active: false`

---

---

### 2. POST `/api/tally/inbound/masters/ledgers` ✅

**Request**
```json
{
  "full_sync": false,
  "Data": [
    {
      "TallyId": 214,
      "AlterID": 522,
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
        { "Address": "Add2" },
        { "Address": "Add3" }
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
        { "Alias": "Ledger Alias" },
        { "Alias": "Ledger Alias 2" }
      ],
      "Description": "Ledger desc",
      "Notes": "Ledger notes"
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Bug fixed:** Non-nullable boolean columns (`is_bill_wise_on`, `inventory_affected`, `is_cost_centre_applicable`, `is_rcm_applicable`) were receiving `null` from `parseBool()` when fields were omitted. Fixed with `?? false` fallback in `TallyInboundSync::syncLedgers()`.

**Behaviours verified:**
- `GroupName: "Sundry Debtors"` → auto-creates a Client record
- Same payload again → `skipped: 1`
- `Action: "Delete"` → sets `is_active: false`

---

---

### 3. POST `/api/tally/inbound/masters/stock-groups` ✅

**Request**
```json
{
  "Data": [
    {
      "TallyId": 4599,
      "Action": "Update",
      "Name": "Adaf",
      "Parent": "Primary",
      "Aliases": [
        { "Alias": "Adaf" }
      ]
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Note:** Real connector sends `Parent` (not `ParentName`) and `Aliases` array. Both `Parent` and `ParentName` are accepted. `AlterID` is optional — omitting it sets `alter_id = 0` and deduplication still works.

---

---

### 4. POST `/api/tally/inbound/masters/stock-categories` ✅

**Request**
```json
{
  "Data": [
    {
      "TallyId": 4591,
      "Action": "Update",
      "Name": "Category1",
      "Parent": "Primary",
      "Aliases": [
        { "Alias": "Category1" },
        { "Alias": "A" }
      ]
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Note:** Real connector sends `Parent` (not `ParentName`) and `Aliases` array. Both forms accepted.

---

---

### 5. POST `/api/tally/inbound/masters/stock-items` ✅

**Request**
```json
{
  "full_sync": false,
  "Data": [
    {
      "TallyId": 241,
      "AlterID": 533,
      "Action": "Update",
      "Name": "30Mbps Lease Line",
      "Description": "Item desc",
      "Remarks": null,
      "Aliases": [
        { "Alias": "30Mbps Lease Line" },
        { "Alias": "Item Alias" }
      ],
      "StockGroupID": 0,
      "StockGroupName": "Stock Group Test1",
      "StockCategoryID": 0,
      "Category": "Stock Category Test 1",
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Note:** If all counters return 0, check Postman body is set to **raw → JSON** and `Data` key is capital D. An unparsed body silently returns all zeros.

---

---

### 6. POST `/api/tally/inbound/masters/statutory` ✅

**Note:** Named fields (`ID`, `AlterID`, `Action`, `Name`, `StatutoryType`, `RegistrationNumber`/`GSTIN`, `StateCode`, `RegistrationType`, `PAN`, `TAN`, `ApplicableFrom`) map to dedicated columns. Any additional fields (e.g. `DeductorType`, `GSTReturnType`) are automatically stored in the `details` jsonb column.

**Request**
```json
{
  "Data": [
    {
      "TallyId": 301,
      "AlterID": 120,
      "Action": "Create",
      "Name": "GST - Maharashtra",
      "StatutoryType": "GST",
      "RegistrationNumber": "27AABCT1234A1Z5",
      "GSTIN": "27AABCT1234A1Z5",
      "StateCode": "27",
      "RegistrationType": "Regular",
      "PAN": "AABCT1234A",
      "TAN": null,
      "ApplicableFrom": "2017-07-01",
      "DeductorType": null,
      "TCSApplicable": "No",
      "GSTReturnType": "GSTR-1",
      "EInvoiceApplicable": "Yes",
      "EWayBillApplicable": "No"
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

---

---

### 7. POST `/api/tally/inbound/payroll/employee-groups` ✅

**Request**
```json
{
  "Data": [
    {
      "TallyId": 401,
      "AlterID": 10,
      "Action": "Create",
      "Name": "Management",
      "ParentName": null
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

---

---

### 8. POST `/api/tally/inbound/payroll/employees` ✅

**Request** (real connector payload format)
```json
{
  "full_sync": false,
  "Data": [
    {
      "TallyId": 4588,
      "Action": "Update",
      "Name": "Akash",
      "Parent": "Employee Group",
      "JoiningDate": "1-4-2024",
      "ResignationDate": "1-4-2025",
      "EmployeeNumber": "536546",
      "Designation": "Designation",
      "Function": "Function",
      "Location": "Location",
      "Gender": "Female",
      "DOB": "1-Mar-1991",
      "FatherName": "Father",
      "SpouseName": "Spouse",
      "Aliases": [
        { "Alias": "Akash" },
        { "Alias": "A" },
        { "Alias": "B" }
      ]
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Field mappings (real connector → DB column):**
- `Parent` → `group_name` (also accepts legacy `GroupName`)
- `JoiningDate` → `date_of_joining` (also accepts `DateOfJoining`)
- `ResignationDate` → `date_of_leaving` (also accepts `DateOfLeaving`)
- `DOB` → `date_of_birth` (also accepts `DateOfBirth`)
- `Location` → `location` (new)
- `FatherName` → `father_name` (new)
- `SpouseName` → `spouse_name` (new)
- `Aliases[].Alias` → `aliases` json array (new)

---

---

### 9. POST `/api/tally/inbound/payroll/pay-heads` ✅

**Request**
```json
{
  "Data": [
    {
      "TallyId": 601,
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

---

---

### 10. POST `/api/tally/inbound/payroll/attendance-types` ✅

**Request**
```json
{
  "Data": [
    {
      "TallyId": 701,
      "AlterID": 5,
      "Action": "Create",
      "Name": "Present",
      "AttendanceType": "Attendance",
      "UnitOfMeasure": "Days"
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

---

---

### 11. POST `/api/tally/inbound/vouchers/sales` ✅

**Request**
```json
{
  "full_sync": false,
  "data": [
    {
      "MasterID": 1,
      "AlterID": 31,
      "Action": "Update",
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Bug fixed:** `autoMapSalesVoucher()` was inserting `status = "unpaid"` into `invoices`, which violates the `invoices_status_check` constraint. Valid values are `draft/sent/paid/partial/overdue/cancelled`. Fixed to use `"sent"`.

**Behaviours verified:**
- Voucher created in `tally_vouchers` with `voucher_type = "Sales"`, `tally_id = 5001`
- 1 `tally_voucher_inventory_entries` row linked to stock item "30Mbps Lease Line"
- 3 `tally_voucher_ledger_entries` rows
- Auto-map: `invoices` row created with `status = "sent"`, linked to client "BlueStar Technologies Pvt Ltd"
- Same payload again → `skipped: 1`

---

---

### 12. POST `/api/tally/inbound/vouchers/purchase` ✅

**Request**
```json
{
  "full_sync": false,
  "data": [
    {
      "MasterID": 6001,
      "AlterID": 410,
      "Action": "Create",
      "VoucherNumber": "PUR/2024-25/001",
      "VoucherDate": "20240405",
      "PartyName": "PUNJAB NATIONAL BANK",
      "VoucherType": "Purchase",
      "Voucher_Total": 50000,
      "IsInvoice": "Yes",
      "IsDeleted": "No",
      "Narration": "Bandwidth purchase April 2024",
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Behaviours verified:**
- Voucher created with `voucher_type = "Purchase"`, `tally_id = 6001`
- 2 `tally_voucher_ledger_entries` rows, 0 inventory entries
- No invoice auto-created (Purchase type is not mapped)
- Same payload → `skipped: 1`

---

---

### 13. POST `/api/tally/inbound/vouchers/credit-note` ✅

**Request**
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Behaviours verified:**
- Voucher created with `voucher_type = "CreditNote"`, `tally_id = 7001`
- 2 `tally_voucher_ledger_entries`, 0 inventory entries
- No invoice auto-created
- Same payload → `skipped: 1`

---

---

### 14. POST `/api/tally/inbound/vouchers/debit-note` ✅

**Request**
```json
{
  "data": [
    {
      "MasterID": 8001,
      "AlterID": 600,
      "Action": "Create",
      "VoucherNumber": "DN/2024-25/001",
      "VoucherDate": "20240412",
      "PartyName": "PUNJAB NATIONAL BANK",
      "VoucherType": "Debit Note",
      "Voucher_Total": 10000,
      "IsInvoice": "No",
      "IsDeleted": "No",
      "Narration": "Debit note for purchase return",
      "VoucherCostCentre": "",
      "BuyerAddress": [{ "BuyerAddress": "" }],
      "InventoryEntries": [],
      "ledgerentries": [
        {
          "LedgerName": "PUNJAB NATIONAL BANK",
          "LedgerAmount": 10000,
          "LedgerGroup": "",
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes",
          "IGSTRate": "",
          "HSNCode": "",
          "Cess_Rate": ""
        },
        {
          "LedgerName": "Purchase - Bandwidth",
          "LedgerAmount": -10000,
          "LedgerGroup": "",
          "IsDeemedPositive": "No",
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Behaviours verified:**
- Voucher created with `voucher_type = "DebitNote"`, `tally_id = 8001`
- 2 `tally_voucher_ledger_entries`, 0 inventory entries
- Same payload → `skipped: 1`

---

---

### 15. POST `/api/tally/inbound/vouchers/receipt` ✅

**Request**
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
              "PAYMENTFAVOURING": "LXPANTOS LOGISTIC SOLUTION INDIA PVT LTD",
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Note:** API reference uses `MasterID: 8001` which conflicts with debit-note. Use `9001` (or any unique ID) in tests.

**Behaviours verified:**
- Voucher created with `voucher_type = "Receipt"`, `tally_id = 9001`
- 2 `tally_voucher_ledger_entries` — party entry has `bills_allocation` JSON stored
- Same payload → `skipped: 1`

---

---

### 16. POST `/api/tally/inbound/vouchers/payment` ✅

**Request**
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Note:** API reference uses `MasterID: 9001` which conflicts with receipt. Use `10001` (or any unique ID).

**Behaviours verified:**
- Voucher created with `voucher_type = "Payment"`, `tally_id = 10001`
- 2 `tally_voucher_ledger_entries`, 0 inventory entries
- Same payload → `skipped: 1`

---

---

### 17. POST `/api/tally/inbound/vouchers/contra` ✅

**Request**
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
              "PAYMENTFAVOURING": "Self",
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Note:** API reference uses `MasterID: 10001` which conflicts with payment. Use `11001` (or any unique ID).

**Behaviours verified:**
- Voucher created with `voucher_type = "Contra"`, `tally_id = 11001`
- 2 `tally_voucher_ledger_entries`, 0 inventory entries, no `PartyName`
- Same payload → `skipped: 1`

---

---

### 18. POST `/api/tally/inbound/vouchers/journal` ✅

**Request**
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

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Note:** API reference uses `MasterID: 11001` which conflicts with contra. Use `12001` (or any unique ID).

**Behaviours verified:**
- Voucher created with `voucher_type = "Journal"`, `tally_id = 12001`
- 2 `tally_voucher_ledger_entries`, 0 inventory entries
- Same payload → `skipped: 1`

---

---

### 19. POST `/api/tally/inbound/reports/balance-sheet` ✅

**Request**
```json
{
  "period_from": "2024-04-01",
  "period_to": "2025-03-31",
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

**Response** — `200 OK`
```json
{ "status": "success" }
```

**Note:** The `data` key is not formally defined in the Tally connector output. Same behaviour as all 4 report endpoints.

**Behaviours verified:**
- New snapshot row inserted in `tally_reports` with `report_type = "balance_sheet"`
- Insert-only: sending same payload again creates a second snapshot row (no deduplication)

---

---

### 20. POST `/api/tally/inbound/reports/profit-loss` ✅

**Request**
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

**Response** — `200 OK`
```json
{ "status": "success" }
```

**Note:** The `data` key is not formally defined in the Tally connector output. Same behaviour as all 4 report endpoints.

**Behaviours verified:**
- New snapshot row inserted in `tally_reports` with `report_type = "profit_loss"`

---

---

### 21. POST `/api/tally/inbound/reports/cash-flow` ✅

**Request**
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

**Response** — `200 OK`
```json
{ "status": "success" }
```

**Note:** The `data` key is not formally defined in the Tally connector output. Same behaviour as all 4 report endpoints.

**Behaviours verified:**
- New snapshot row inserted in `tally_reports` with `report_type = "cash_flow"`

---

---

### 22. POST `/api/tally/inbound/reports/ratio-analysis` ✅

**Request**
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

**Response** — `200 OK`
```json
{ "status": "success" }
```

**Note:** The `data` key is not formally defined in the Tally connector output. Same behaviour as all 4 report endpoints.

**Behaviours verified:**
- New snapshot row inserted in `tally_reports` with `report_type = "ratio_analysis"`

---

---

### 23. POST `/api/tally/inbound/masters/godowns` ✅

**Request**
```json
{
  "Data": [
    {
      "TallyId": 4608,
      "AlterID": 11964,
      "Action": "Create",
      "Guid": "248b1a3e-7f9f-443c-ae33-1984824e53f7-00001200",
      "Name": "Delhi",
      "Under": "Primary"
    }
  ]
}
```

**Response** — `200 OK`
```json
{
  "status": "success",
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "failed": 0
}
```

**Behaviours verified:**
- Record created in `tally_godowns` with `name = "Delhi"`, `under = "Primary"`, `guid` stored
- Same payload again → `skipped: 1` (AlterID match)
- `Action: "Delete"` → sets `is_active: false`

---

---

## Outbound API Testing Notes

Outbound GET endpoints (rows 24–41) return records that are **pending** in the outbound queue. A record enters the queue only when it has been created or modified in Accobot (or when the Tally inbound sync creates/updates a Tally master record).

### Pre-requisites for testing outbound endpoints

1. Ensure at least one record is pending in `tally_outbound_queue` (`status = 'pending'`).  
   Trigger one by editing any ledger/stock item/voucher in the Accobot UI, or by inserting a row manually:
   ```sql
   INSERT INTO tally_outbound_queue (tenant_id, entity_type, entity_id, status, queued_at)
   VALUES (1, 'App\\Models\\TallyLedger', 42, 'pending', now());
   ```
2. The Bearer token used must belong to the same tenant as the pending record.

### Outbound GET — no request body

All GET endpoints take no request body. Auth header is the only requirement.

**Example: GET `/api/MastersAPI/ledger-master`**

Response when a ledger is pending:
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
      ...
    }
  ]
}
```

Response when nothing is pending:
```json
{ "Data": [] }
```

### Confirmation POST — after connector processes the GET

After Tally processes the record, it posts back with `AccobotId` and `TallyId`:

**Example: POST `/api/MastersAPI/update-ledger-master`**

```json
{
  "Data": [
    {
      "AccobotId": 42,
      "TallyId": 5501,
      "IsSynced": true
    }
  ]
}
```

Response:
```json
{ "status": "ok", "updated": 1 }
```

After a successful confirmation:
- The `tally_outbound_queue` row moves to `status = 'confirmed'`
- The Accobot record gets `tally_id = 5501` written back
- If `IsSynced: true` and the record maps to a Client/Vendor/Product/Invoice, `tally_synced_at` is set on that mapped record

*More results will be added here as endpoints are tested.*
