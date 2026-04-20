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
| `company_id` | `COMP001` | Tally company ID (used in confirmation endpoints) |

### Auth (apply to collection)

Set **Authorization** → **Bearer Token** → `{{tally_token}}` at the collection level so all requests inherit it.

### Headers (apply to collection)

```
Content-Type: application/json
Accept: application/json
```

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
| 23 | GET | `/api/MastersAPI/ledger-group` | ⬜ Pending |
| 24 | GET | `/api/MastersAPI/ledger-master` | ⬜ Pending |
| 25 | GET | `/api/MastersAPI/stock-master` | ⬜ Pending |
| 26 | GET | `/api/MastersAPI/stock-group` | ⬜ Pending |
| 27 | GET | `/api/MastersAPI/stock-category` | ⬜ Pending |
| 28 | GET | `/api/MastersAPI/statutory-master` | ⬜ Pending |
| 29 | GET | `/api/PayrollAPI/employee-group` | ⬜ Pending |
| 30 | GET | `/api/PayrollAPI/employee` | ⬜ Pending |
| 31 | GET | `/api/PayrollAPI/pay-head` | ⬜ Pending |
| 32 | GET | `/api/PayrollAPI/attendance-type` | ⬜ Pending |
| 33 | GET | `/api/VoucherAPI/sales-voucher` | ⬜ Pending |
| 34 | GET | `/api/VoucherAPI/purchase-voucher` | ⬜ Pending |
| 35 | GET | `/api/VoucherAPI/debitNote-voucher` | ⬜ Pending |
| 36 | GET | `/api/VoucherAPI/creditNote-voucher` | ⬜ Pending |
| 37 | GET | `/api/VoucherAPI/receipt-voucher` | ⬜ Pending |
| 38 | GET | `/api/VoucherAPI/payment-voucher` | ⬜ Pending |
| 39 | GET | `/api/VoucherAPI/contra-voucher` | ⬜ Pending |
| 40 | GET | `/api/VoucherAPI/journal-voucher` | ⬜ Pending |
| 41 | POST | `/api/MastersAPI/update-ledger-group/{companyId}` | ⬜ Pending |
| 42 | POST | `/api/MastersAPI/update-ledger-master/{companyId}` | ⬜ Pending |
| 43 | POST | `/api/MastersAPI/update-stock-master/{companyId}` | ⬜ Pending |
| 44 | POST | `/api/MastersAPI/update-stock-group/{companyId}` | ⬜ Pending |
| 45 | POST | `/api/MastersAPI/update-stock-category/{companyId}` | ⬜ Pending |
| 46 | POST | `/api/MastersAPI/update-statutory-master/{companyId}` | ⬜ Pending |
| 47 | POST | `/api/PayrollAPI/update-employee-group/{companyId}` | ⬜ Pending |
| 48 | POST | `/api/PayrollAPI/update-employee/{companyId}` | ⬜ Pending |
| 49 | POST | `/api/PayrollAPI/update-pay-head/{companyId}` | ⬜ Pending |
| 50 | POST | `/api/PayrollAPI/update-attendance-type/{companyId}` | ⬜ Pending |
| 51 | POST | `/api/VoucherAPI/update-sales-voucher/{companyId}` | ⬜ Pending |
| 52 | POST | `/api/VoucherAPI/update-purchase-voucher/{companyId}` | ⬜ Pending |
| 53 | POST | `/api/VoucherAPI/update-debitnote-voucher/{companyId}` | ⬜ Pending |
| 54 | POST | `/api/VoucherAPI/update-creditnote-voucher/{companyId}` | ⬜ Pending |
| 55 | POST | `/api/VoucherAPI/update-receipt-voucher/{companyId}` | ⬜ Pending |
| 56 | POST | `/api/VoucherAPI/update-payment-voucher/{companyId}` | ⬜ Pending |
| 57 | POST | `/api/VoucherAPI/update-contra-voucher/{companyId}` | ⬜ Pending |
| 58 | POST | `/api/VoucherAPI/update-journal-voucher/{companyId}` | ⬜ Pending |

---

## Test Results

---

### 1. POST `/api/tally/inbound/masters/ledger-groups` ✅

**Request**
```json
{
  "Data": [
    {
      "ID": 1,
      "AlterID": 101,
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
      "ContactPersonEmailCC": null,
      "ContactPersonFax": null,
      "ContactPersonWebsite": null,
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

**Note:** `ID` must be all-caps in the request — the code checks `$item['ID'] ?? $item['Id']`. Lowercase `"id"` resolves to 0 and counts as failed.

---

---

### 4. POST `/api/tally/inbound/masters/stock-categories` ✅

**Request**
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

### 5. POST `/api/tally/inbound/masters/stock-items` ✅

**Request**
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
      "ID": 301,
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
      "ID": 401,
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

**Request**
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

### 9. POST `/api/tally/inbound/payroll/pay-heads` ✅

**Request**
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
      "BuyerEmail": "rajesh@bluestar.in",
      "BuyerMobile": "9876543210",
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
      "Narration": "Bandwidth purchase April 2024",
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
  "Data": [
    {
      "MasterID": 8001,
      "AlterID": 600,
      "Action": "Create",
      "VoucherNumber": "DN/2024-25/001",
      "VoucherDate": "2024-04-12",
      "PartyName": "Punjab National Bank",
      "VoucherTotal": 10000,
      "IsInvoice": "No",
      "Narration": "Debit note for purchase return",
      "InventoryEntries": [],
      "LedgerEntries": [
        {
          "LedgerName": "Punjab National Bank",
          "LedgerGroup": "Sundry Creditors",
          "LedgerAmount": 10000,
          "IsDeemedPositive": "Yes",
          "IsPartyLedger": "Yes"
        },
        {
          "LedgerName": "Purchase - Bandwidth",
          "LedgerGroup": "Purchase Accounts",
          "LedgerAmount": -10000,
          "IsDeemedPositive": "No",
          "IsPartyLedger": "No"
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
  "Data": [
    {
      "MasterID": 9001,
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
  "Data": [
    {
      "MasterID": 10001,
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
  "Data": [
    {
      "MasterID": 11001,
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
  "Data": [
    {
      "MasterID": 12001,
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

*More results will be added here as endpoints are tested.*
