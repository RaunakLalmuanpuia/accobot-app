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
| 11 | POST | `/api/tally/inbound/vouchers/sales` | ⬜ Pending |
| 12 | POST | `/api/tally/inbound/vouchers/purchase` | ⬜ Pending |
| 13 | POST | `/api/tally/inbound/vouchers/credit-note` | ⬜ Pending |
| 14 | POST | `/api/tally/inbound/vouchers/debit-note` | ⬜ Pending |
| 15 | POST | `/api/tally/inbound/vouchers/receipt` | ⬜ Pending |
| 16 | POST | `/api/tally/inbound/vouchers/payment` | ⬜ Pending |
| 17 | POST | `/api/tally/inbound/vouchers/contra` | ⬜ Pending |
| 18 | POST | `/api/tally/inbound/vouchers/journal` | ⬜ Pending |
| 19 | POST | `/api/tally/inbound/reports/balance-sheet` | ⬜ Pending |
| 20 | POST | `/api/tally/inbound/reports/profit-loss` | ⬜ Pending |
| 21 | POST | `/api/tally/inbound/reports/cash-flow` | ⬜ Pending |
| 22 | POST | `/api/tally/inbound/reports/ratio-analysis` | ⬜ Pending |
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

*More results will be added here as endpoints are tested.*
