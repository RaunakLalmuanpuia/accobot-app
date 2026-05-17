# Tally Voucher — UI Input Examples

Concrete field-by-field examples for every voucher type. All examples use the
**Tally Business** tenant master data (`TEst Stock Item`, `Test Debtors`,
`Test Creditor`, `Test Sales`, `Cash`).

For rules and theory see `tally-voucher-entry-guide.md`.

---

## Key Rules (applies to all item invoice types)

- **Sales / Credit Note** — The **Sales Ledger** (violet row) sets the income
  account via `AccountingAllocations`. Do **not** add it again in the Ledger
  Allocations section — that causes a double-count and Tally rejects with
  "Voucher totals do not match".
- **Purchase / Debit Note** — Same rule for the **Purchase Ledger** violet row.
- In `Accounting Invoice` or `As Voucher` mode there is no violet row, so
  **both** the party and the sales/purchase ledger go in Ledger Allocations.

---

## Sales

### Item Invoice (no GST)

Sell stock items and issue an invoice with no tax.

**Header**
| Field | Value |
|---|---|
| Invoice No | `1` |
| Date | `2026-05-17` |
| Ref No | *(blank)* |
| Ref Date | *(blank)* |
| Mode | **Item Invoice** |

**Party & Supply**
| Field | Value |
|---|---|
| Party A/c Name | `Test Debtors` |
| Place of Supply | `Mizoram` |

**Item Grid**
| Name of Item | Qty | Per | Rate | Disc% | Amount |
|---|---|---|---|---|---|
| TEst Stock Item | 10 | Box | 120 | 0 | 1200 |

**Sales Ledger** (violet row): `Test Sales`

**Party & Tax Ledgers**
| Ledger Name | Amount | Dr / Cr & Party |
|---|---|---|
| Test Debtors | 1200 | Dr (Debit) + ☑ Party |

**Bill References** (on the Test Debtors ledger row):
| AgstType | Reference | CreditPeriod | Amount |
|---|---|---|---|
| New Ref | `1` | `30 Days` | 1200 |

**Grand Total:** `1200`

> Only the party goes in Ledger Allocations. Test Sales is already handled
> by the Sales Ledger row above.  
> Adding a `New Ref` bill reference creates a trackable outstanding in Tally's
> bill-wise ledger. The Reference (`1`) is the invoice number — use it when
> creating the matching Receipt later.

---

### Item Invoice (with GST — IGST 18%)

Sell stock items with GST to an out-of-state customer (inter-state → IGST).

**Header**
| Field | Value |
|---|---|
| Invoice No | `2` |
| Date | `2026-05-17` |
| Mode | **Item Invoice** |

**Party & Supply**
| Field | Value |
|---|---|
| Party A/c Name | `Test Debtors` |
| Place of Supply | `Delhi` *(different from company state — inter-state)* |

**Item Grid** — expand the row (▼) to set IGST Rate:
| Name of Item | Qty | Per | Rate | Disc% | Amount | IGST Rate% |
|---|---|---|---|---|---|---|
| TEst Stock Item | 10 | Box | 100 | 0 | 1000 | 18 |

**Sales Ledger** (violet row): `Test Sales`

Click **"Suggest Tax Lines"** — it calculates `1000 × 18% = 180` and adds a row.
The ledger name is **auto-picked** by matching a ledger in the "Duties & Taxes" group whose
name contains "igst" (or "cess" for cess lines). If no match is found the name is left blank
and you can type it manually.

**Party & Tax Ledgers**
| Ledger Name | Amount | Dr / Cr & Party | IGST% |
|---|---|---|---|
| Test Debtors | 1180 | Dr (Debit) + ☑ Party | — |
| Output IGST 18% | 180 | Cr (Credit) | 18 |

**Bill References** (on Test Debtors row):
| AgstType | Reference | CreditPeriod | Amount |
|---|---|---|---|
| New Ref | `2` | `30 Days` | 1180 |

**Grand Total:** `1180`

> The `Output IGST 18%` ledger must be in the **Duties & Taxes** group in Tally.
> For intra-state sales (same state) use two lines instead:
> `Output CGST 9%` (90) Cr and `Output SGST 9%` (90) Cr.

---

### Accounting Invoice

Sell a service (no stock items). Items grid is hidden.

**Header**
| Field | Value |
|---|---|
| Invoice No | `1` |
| Date | `2026-05-17` |
| Mode | **Accounting Invoice** |

**Party & Supply**
| Field | Value |
|---|---|
| Party A/c Name | `Test Debtors` |
| Place of Supply | `Mizoram` |

**Ledger Entries**
| Ledger Name | Amount | Dr / Cr & Party |
|---|---|---|
| Test Debtors | 1200 | Dr (Debit) + ☑ Party |
| Test Sales | 1200 | Cr (Credit) |

**Grand Total:** `1200`

> In Accounting Invoice mode there is no violet Sales Ledger row, so
> Test Sales must be added manually here as Cr.

---

### As Voucher

A sales transaction that is not a formal GST invoice (no invoice number
needed). Same as Item Invoice except:
- Mode toggle set to **As Voucher**
- `IsInvoice` is automatically set to `No`

All other fields identical to Item Invoice.

---

## Purchase

### Item Invoice

Receive goods from a supplier with a tax bill.

**Header**
| Field | Value |
|---|---|
| Voucher No | `1` |
| Date | `2026-05-17` |
| Ref No | `BILL-001` *(supplier's invoice number)* |
| Ref Date | `2026-05-17` |
| Mode | **Item Invoice** |

**Party & Supply**
| Field | Value |
|---|---|
| Supplier A/c Name | `Test Creditor` |
| Place of Supply | `Mizoram` |

**Item Grid**
| Name of Item | Qty | Per | Rate | Disc% | Amount |
|---|---|---|---|---|---|
| TEst Stock Item | 10 | Box | 120 | 0 | 1200 |

**Purchase Ledger** (violet row): `Test Purchase` *(Purchase Accounts group)*

**Ledger Allocations**
| Ledger Name | Amount | Dr / Cr & Party |
|---|---|---|
| Test Creditor | 1200 | Cr (Credit) + ☑ Party |

**Grand Total:** `1200`

> Supplier is Cr (you owe them). Test Purchase is Dr via AccountingAllocations
> — do not add it in Ledger Allocations.

---

### Accounting Invoice

Purchase a service (no stock items).

**Header**
| Field | Value |
|---|---|
| Voucher No | `1` |
| Date | `2026-05-17` |
| Mode | **Accounting Invoice** |

**Party & Supply**
| Field | Value |
|---|---|
| Supplier A/c Name | `Test Creditor` |
| Place of Supply | `Mizoram` |

**Ledger Entries**
| Ledger Name | Amount | Dr / Cr & Party |
|---|---|---|
| Test Creditor | 1200 | Cr (Credit) + ☑ Party |
| Test Purchase | 1200 | Dr (Debit) |

**Grand Total:** `1200`

---

### As Voucher

Same as Purchase Item Invoice with Mode set to **As Voucher** (`IsInvoice: No`).

---

## Receipt

Money received from a customer. The form shows an **Outstanding Bills** table
automatically when you select a party that has unsettled `New Ref` invoices —
tick the ones being settled and the `Agst Ref` entries are filled in for you.

### Simple receipt (full settlement, one invoice)

**Header**
| Field | Value |
|---|---|
| Receipt No | `REC-001` |
| Date | `2026-05-17` |

**Account Ledger** *(Dr — where money lands)*
| Field | Value |
|---|---|
| Ledger | `Cash` |
| Amount | `1200` |

**Particulars / Received From** *(Cr — who paid)*
| Ledger | Amount |
|---|---|
| Test Debtors | 1200 |

Select `Test Debtors` → the Outstanding Bills table appears:

| Reference | Date | Invoiced | Settled | Outstanding | ☑ |
|---|---|---|---|---|---|
| 1 | 1-May-26 | 1200.00 | 0.00 | 1200.00 | ☑ |

Ticking invoice `1` auto-fills Bill References:
| AgstType | Reference | CreditPeriod | Amount |
|---|---|---|---|
| Agst Ref | `1` | `1-May-26` | 1200 |

**Narration:** *(blank)*

> `Cash` Dr (money in), `Test Debtors` Cr (receivable closes).
> The `Agst Ref` entry links this receipt to the original Sales invoice `1`,
> closing it in Tally's bill-wise ledger.

---

### Partial settlement across two invoices

Customer pays 1500 against two outstanding invoices (1200 + 800).

**Account Ledger**: `Cash` / Amount: `1500`

**Particulars**: `Test Debtors` / Amount: `1500`

Outstanding Bills — tick both invoices:

| Reference | Outstanding | ☑ |
|---|---|---|
| 1 | 1200.00 | ☑ |
| 2 | 800.00 | ☑ |

This adds two `Agst Ref` entries totalling 2000. Since the customer only paid
1500, manually adjust `Amount` on one of the bill refs — e.g. set invoice `2`
to 300 so total = 1500. Invoice `2` remains partly outstanding (500 left).

Bill References after adjustment:
| AgstType | Reference | CreditPeriod | Amount |
|---|---|---|---|
| Agst Ref | `1` | `1-May-26` | 1200 |
| Agst Ref | `2` | `3-May-26` | 300 |

---

## Payment

Money paid to a supplier or for an expense. Same outstanding bills flow as
Receipt — selecting a creditor party shows unsettled purchase bills to tick.

### Simple payment (full settlement)

**Header**
| Field | Value |
|---|---|
| Payment No | `PMT-001` |
| Date | `2026-05-17` |

**Account Ledger** *(Cr — where money goes out from)*
| Field | Value |
|---|---|
| Ledger | `Cash` |
| Amount | `1200` |

**Particulars / Paid To** *(Dr — who receives)*
| Ledger | Amount |
|---|---|
| Test Creditor | 1200 |

Select `Test Creditor` → Outstanding Bills table appears (populated from
`New Ref` entries on purchase vouchers). Tick the invoice to settle:

| AgstType | Reference | CreditPeriod | Amount |
|---|---|---|---|
| Agst Ref | `BILL-001` | `1-May-26` | 1200 |

**Narration:** *(blank)*

> `Cash` Cr (money out), `Test Creditor` Dr (liability reduces).
> Mirror image of Receipt — same two fields, directions flipped.

---

## Contra

Transfer between two bank/cash accounts (cash deposit to bank, or bank
withdrawal to cash).

**Header**
| Field | Value |
|---|---|
| Contra No | `CTR-001` |
| Date | `2026-05-17` |

**Bank / Cash Account** *(To — Dr, money arriving)*
| Field | Value |
|---|---|
| Ledger | `HDFC Bank` *(Bank Accounts group)* |
| Amount | `5000` |

**Particulars (From — Withdraw From)** *(Cr, money leaving)*
| Ledger | Amount |
|---|---|
| Cash | 5000 |

**Narration:** `Cash deposited to HDFC Bank`

> Both ledgers must be Cash-in-Hand or Bank Accounts group only.
> No debtors, creditors, or expense ledgers allowed in Contra.

---

## Journal

Free-form double-entry for adjustments, provisions, TDS, depreciation, etc.

**Header**
| Field | Value |
|---|---|
| Journal No | `JNL-001` |
| Date | `2026-05-17` |
| Reference | `GST-ADJ-01` |

**Ledger Lines**
| Ledger Name | Dr Amount | Cr Amount |
|---|---|---|
| IGST | 1000 | — |
| SGST | — | 1000 |

**Grand Total:** `1000`

**Narration:** `SGST adjusted against IGST`

> Each row has its own Dr or Cr amount column — click the `+ Dr` / `+ Cr`
> button to set the side. Total Dr must equal Total Cr before saving.
> No inventory, no party ledger, no mode toggle — fully free-form.

---

## Credit Note

Reduce a previously issued sales invoice (customer return, price correction).
Identical form to Sales.

**Header**
| Field | Value |
|---|---|
| Credit Note No | `CN-001` |
| Date | `2026-05-17` |
| Ref No | `1` *(original sales invoice number)* |
| Ref Date | `2026-05-17` |
| Mode | **Item Invoice** |

**Party & Supply**
| Field | Value |
|---|---|
| Party A/c Name | `Test Debtors` |
| Place of Supply | `Mizoram` |

**Item Grid** *(goods being returned)*
| Name of Item | Qty | Per | Rate | Disc% | Amount |
|---|---|---|---|---|---|
| TEst Stock Item | 5 | Box | 120 | 0 | 600 |

**Sales Ledger** (violet row): `Test Sales`

**Party & Tax Ledgers**
| Ledger Name | Amount | Dr / Cr & Party |
|---|---|---|
| Test Debtors | 600 | **Cr (Credit)** + ☑ Party |

**Grand Total:** `600`

> Key difference from Sales: party is **Cr** (reducing the customer's balance).
> `IsDeemedPositive` on inventory is `"Yes"` — stock comes back IN.
> Ref No links back to the original sales invoice.

---

## Debit Note

Reduce a purchase bill (supplier return, overcharge correction).
Identical form to Purchase.

**Header**
| Field | Value |
|---|---|
| Debit Note No | `DN-001` |
| Date | `2026-05-17` |
| Ref No | `1` *(original purchase bill number)* |
| Ref Date | `2026-05-17` |
| Mode | **Item Invoice** |

**Party & Supply**
| Field | Value |
|---|---|
| Supplier A/c Name | `Test Creditor` |
| Place of Supply | `Mizoram` |

**Item Grid** *(goods being returned to supplier)*
| Name of Item | Qty | Per | Rate | Disc% | Amount |
|---|---|---|---|---|---|
| TEst Stock Item | 5 | Box | 120 | 0 | 600 |

**Purchase Ledger** (violet row): `Test Purchase` *(Purchase Accounts group)*

**Ledger Allocations**
| Ledger Name | Amount | Dr / Cr & Party |
|---|---|---|
| Test Creditor | 600 | **Dr (Debit)** + ☑ Party |

**Grand Total:** `600`

> Key difference from Purchase: party is **Dr** (reducing what you owe the
> supplier). `IsDeemedPositive` on inventory is `"No"` — stock goes back OUT.

---

## Bill-Wise Settlement — Full Lifecycle

Tally's bill-wise ledger tracks which invoices are outstanding and which
receipts/payments settle them. Every step happens via `BillsAllocation` inside
the party ledger entry.

### Step 1 — Sales invoice creates a `New Ref`

When you save a Sales voucher with a bill reference on the party ledger:

```
Party: Test Debtors  Amount: 1180  IsDeemedPositive: Yes (Dr)
BillsAllocation: [{ AgstType: "New Ref", Reference: "INV-001", CreditPeriod: "30 Days", Amount: 1180 }]
```

Tally registers INV-001 as an **open receivable** of ₹1,180.

### Step 2 — Receipt partially settles it

```
Party: Test Debtors  Amount: 700  IsDeemedPositive: No (Cr)
BillsAllocation: [{ AgstType: "Agst Ref", Reference: "INV-001", CreditPeriod: "17-May-26", Amount: 700 }]
```

The Outstanding Bills picker shows: INV-001 — invoiced ₹1,180, settled ₹0, **outstanding ₹1,180**.
Tick it → auto-fills the Agst Ref at ₹1,180. Reduce to ₹700 for partial payment.
Tally now shows INV-001 as partly paid: ₹480 remaining.

### Step 3 — Second receipt closes it

```
BillsAllocation: [{ AgstType: "Agst Ref", Reference: "INV-001", CreditPeriod: "17-May-26", Amount: 480 }]
```

The Outstanding Bills picker now shows: invoiced ₹1,180, settled ₹700, **outstanding ₹480**.
Tick → auto-fills ₹480. INV-001 is now fully closed.

### `CreditPeriod` meaning

| AgstType | CreditPeriod means |
|---|---|
| `New Ref` | Credit terms, e.g. `"30 Days"` or `"60 Days"` |
| `Agst Ref` | Original invoice date, e.g. `"17-May-26"` |
| `On Account` | Blank or any note |
| `Advance` | Blank |

---

## Quick Reference

| Voucher | Mode | Party Dr/Cr | Sales/Purchase ledger goes in |
|---|---|---|---|
| Sales | Item Invoice | Dr | Sales Ledger violet row (AccountingAllocations) |
| Sales | Accounting Invoice | Dr | Ledger Allocations (Cr) |
| Sales | As Voucher | Dr | Sales Ledger violet row (AccountingAllocations) |
| Purchase | Item Invoice | Cr | Purchase Ledger violet row (AccountingAllocations) |
| Purchase | Accounting Invoice | Cr | Ledger Allocations (Dr) |
| Purchase | As Voucher | Cr | Purchase Ledger violet row (AccountingAllocations) |
| Receipt | — | Cr | N/A — no sales ledger |
| Payment | — | Dr | N/A — no purchase ledger |
| Contra | — | Dr (destination) | N/A — cash/bank only |
| Journal | — | Either | Ledger Allocations |
| Credit Note | Item Invoice | **Cr** | Sales Ledger violet row (AccountingAllocations) |
| Credit Note | Accounting Invoice | **Cr** | Ledger Allocations (Dr) |
| Debit Note | Item Invoice | **Dr** | Purchase Ledger violet row (AccountingAllocations) |
| Debit Note | Accounting Invoice | **Dr** | Ledger Allocations (Cr) |

---

## Common Mistakes

| Mistake | Error in Tally | Fix |
|---|---|---|
| Adding Sales/Purchase ledger in Ledger Allocations on Item Invoice | "Voucher totals do not match" — Cr doubled | Remove it — it's already in AccountingAllocations via the violet row |
| Credit Note party set to Dr instead of Cr | Totals mismatch | Party is Cr on Credit Note (reducing receivable) |
| Debit Note party set to Cr instead of Dr | Totals mismatch | Party is Dr on Debit Note (reducing payable) |
| Empty Ledger Allocations | `ledgerentries: []` — Tally rejects | Always add at least the party ledger entry |
| Party checkbox not ticked | Bill-wise tracking breaks | Tick ☑ Party on the customer/supplier ledger line |
| Wrong Place of Supply | Wrong GST state code on invoice | Set to customer's state for inter-state, company state for intra-state |
| GST ledger added but IGST% not filled in the ledger row | GST return shows ₹0 tax | Fill the `IGST%` field on the GST ledger entry row |
| Using IGST for intra-state sale | Wrong GST return bucket | Use CGST + SGST (each at half the rate) when buyer state = seller state |
| `New Ref` missing on Sales party ledger | Invoice doesn't appear in Outstanding Bills picker | Always add a `New Ref` bill reference on the party ledger when bill-wise is enabled |
| `Agst Ref` amount > outstanding balance | Tally shows negative balance for the bill | Reduce Amount to the actual outstanding; use a second `On Account` line for excess |
| `Reference` in `Agst Ref` doesn't match any `New Ref` | Bill remains open, double outstanding | Use the exact same reference string as the original `New Ref` |
