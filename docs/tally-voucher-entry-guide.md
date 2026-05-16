# Tally Voucher Entry Guide

How to enter each voucher type in the Accobot UI so the outbound payload matches what Tally expects.

---

## Why `ledgerentries` was empty in your Sales voucher

The **Ledger Allocations** section at the bottom of the Sales form is where you add the accounting legs of the transaction. For a Sales voucher Tally needs **at least** the party (debtor) ledger entry — the receivable that balances the sale. If you skip this section, `ledgerentries` will be `[]` and Tally will reject or mispost the voucher.

**Minimum for a Sales Item Invoice:**

| Ledger | Amount | Dr/Cr | Party |
|---|---|---|---|
| Customer ledger (debtor) | full invoice amount | Dr | ✓ |
| Tax ledger (IGST / CGST+SGST) | tax amount | Cr | ✗ |

---

## Field Reference

### Dr / Cr meaning in ledger entries

| `IsDeemedPositive` | Meaning |
|---|---|
| `Yes` (Dr) | Debit — asset increases, liability decreases |
| `No` (Cr) | Credit — asset decreases, liability increases |

### `IsPartyLedger` (Party checkbox)

Mark **✓ Party** on the ledger that represents the customer/vendor named in the **Party A/c Name** field. Tally uses this to link the ledger entry to the party master for bill-wise tracking.

---

## Sales — Item Invoice

Used when you sell stock items and need a GST invoice.

**Header**
- **Mode:** Item Invoice (toggle at top)
- **Is Invoice:** ✓ checked
- **Party A/c Name:** Customer ledger (Sundry Debtors group) — auto-fills buyer fields on selection
- **Place of Supply:** Customer's state (for GST)

**Items grid** (add one row per stock item)
| Field | What to enter |
|---|---|
| Name of Item | Select from stock master |
| Qty | Billed quantity |
| Per | Unit — auto-fills from stock master |
| Rate | Auto-fills from Standard Price (or Opening Rate if Standard Price is 0); edit if needed |
| Disc% | Discount percent if any |
| Amount | Auto-calculated (Qty × Rate × (1 - Disc%)) |

Expand the row (▼) to fill:
| Field | What to enter |
|---|---|
| HSN Code | Auto-fills from stock master if configured in Tally — enter manually if blank |
| IGST Rate % | Auto-fills from stock master (0 if not set) |
| Godown | Select warehouse |
| Sales Ledger | Enter manually — Tally item master does not export a default sales ledger |
| Batch | Batch number if batch-tracked |

> **Why fields appear blank:** HSN Code and Rate only auto-fill if those values are configured on the stock item in Tally master (`Standard Price`, `HSN Code` fields). If an item was not fully configured in Tally, fill them manually here.

**Batch Allocations** — fill only if your item is batch-tracked (expiry dates, lot numbers). Leave empty otherwise.

**Accounting Allocations — leave this empty.** The system auto-generates it from the Sales Ledger you entered above. You do not need to fill it manually.

**Ledger Allocations** (mandatory)
After entering items, click **"Suggest Tax Lines"** if IGST rates are set — it auto-creates tax ledger entries.
Then manually add the party entry:

| Ledger | Amount | Dr/Cr | Party | Notes |
|---|---|---|---|---|
| Customer ledger | Invoice total | Dr | ✓ | The debtor — receivable |
| IGST Output ledger | IGST amount | Cr | ✗ | Auto-suggested if IGST rate set |
| CGST Output ledger | CGST amount | Cr | ✗ | For intra-state |
| SGST Output ledger | SGST amount | Cr | ✗ | For intra-state |

> **Grand Total** auto-computes from the items sub-total + ledger totals. Click ↺ to reset if you've overridden it.

**Real Tally output for reference:**
```
ledgerentries:
  Cash  Dr=Yes  Party=Yes  ₹85,000
```

---

## Sales — Accounting Invoice

Used when selling services (no stock items). Items grid is hidden in Accounting mode.

**Header**
- **Mode:** Accounting Invoice
- **Is Invoice:** ✓ checked
- **Party A/c Name:** Customer ledger

**Ledger Allocations** (two lines minimum)

| Ledger | Amount | Dr/Cr | Party |
|---|---|---|---|
| Customer ledger | invoice amount | Dr | ✓ |
| Sales / Income ledger | invoice amount | Cr | ✗ |

**Real Tally output:**
```
ledgerentries:
  Cash          Dr=Yes  Party=Yes  ₹1,000
  Test Payhead  Dr=No   Party=No   ₹1,000
```

---

## Sales — As Voucher

A sales transaction that is **not** a formal invoice (no GST invoice number needed). Same as Item Invoice but:
- **Is Invoice:** ✗ unchecked
- No Dispatch / e-Invoice sections needed

Ledger entries: same pattern as Item Invoice.

**Real Tally output:**
```
ledgerentries:
  U Bank Ledger Test  Dr=Yes  Party=Yes  ₹1,000
  U Direct Incomes    Dr=No   Party=No   ₹1,000
```

---

## Purchase — Item Invoice

Receiving goods from a supplier with a tax invoice.

**Header**
- **Mode:** Item Invoice
- **Is Invoice:** ✓ checked
- **Vendor A/c Name:** Supplier ledger (Sundry Creditors group)
- **Supplier Inv No / Date:** The supplier's original invoice number and date (goes into Reference / ReferenceDate)

**Items grid** — same columns as Sales, but set:
- **Purchase Ledger** (shown as "Purchase Ledger" in the expand row): the purchase account (e.g. "Purchase Accounts")

**Ledger Allocations** (minimum one line)

| Ledger | Amount | Dr/Cr | Party |
|---|---|---|---|
| Supplier ledger | invoice total | Cr | ✓ |
| Tax ledger (IGST input) | tax amount | Dr | ✗ |

> Supplier is **Cr** because the purchase increases your liability to the creditor.

**Real Tally output:**
```
ledgerentries:
  Cash  Dr=No  Party=Yes  ₹10,000
```

---

## Purchase — Accounting Invoice

Purchasing services (no items).

**Header**
- **Mode:** Accounting Invoice
- **Is Invoice:** ✓ checked

**Ledger Allocations**

| Ledger | Amount | Dr/Cr | Party |
|---|---|---|---|
| Supplier ledger | total | Cr | ✓ |
| Purchase / Expense ledger | total | Dr | ✗ |

**Real Tally output:**
```
ledgerentries:
  U Sundry Creditors    Dr=No   Party=Yes  ₹40,000
  U Purchase Accounts   Dr=Yes  Party=No   ₹40,000
```

---

## Purchase — As Voucher

Same as Purchase Item Invoice but **Is Invoice: unchecked**.

**Real Tally output:**
```
ledgerentries:
  U Branc/Division    Dr=No   Party=Yes  ₹1,200
  U Purchase Accounts Dr=Yes  Party=No   ₹1,200
```

---

## Receipt

Money received from a customer (payment against an invoice or advance).

The Receipt form has two sections:

### Account (Bank / Cash)
The bank or cash account **into which** money is received.
- Select the bank/cash ledger
- Amount auto-syncs from Particulars (click ↺ Sync)
- Expand **Bank Transaction Details** to add UTR, transfer mode, date for bank transfers

### Particulars
One row per source of the receipt (usually one customer, or multiple if split).
- Select the customer ledger
- Enter amount received from that customer
- **Bill References** — add "Agst Ref" entries to knock off specific invoices:
  - AgstType: `Agst Ref` (against an existing invoice) or `New Ref` (fresh)
  - Reference: the invoice number being settled
  - Amount: amount being knocked off

**What this produces in ledgerentries:**

| Ledger | Dr/Cr | Party | Notes |
|---|---|---|---|
| Customer ledger | Cr (`No`) | ✓ | Reduces the receivable |
| Bank/Cash ledger | Dr (`Yes`) | ✓ | Increases bank balance |

**Real Tally output (Single receipt):**
```
ledgerentries:
  U Sundry Debtors  Dr=No   Party=Yes  ₹1,000   (customer pays — receivable reduces)
  Cash              Dr=Yes  Party=Yes  ₹1,000   (cash increases)
```

**Real Tally output (Double — split to two accounts):**
```
ledgerentries:
  U Sundry Debtors  Dr=No   Party=Yes  ₹500
  Cash              Dr=Yes  Party=Yes  ₹300
  U Bank Ledger     Dr=Yes  Party=Yes  ₹200
```
> Add a second row in Particulars for the split, or note that the Account section amount is the total and you can split using multiple "Account" entries via the ↺ sync approach.

---

## Payment

Money paid to a creditor or for an expense.

Same two-section layout as Receipt but roles are reversed.

### Account (Bank / Cash)
The account **from which** money goes out (marked **Cr** internally).

### Particulars
Who is being paid (creditor ledger or expense ledger). Each row is **Dr**.
- Bill References: use `Agst Ref` to knock off specific purchase invoices.

**What this produces in ledgerentries:**

| Ledger | Dr/Cr | Party | Notes |
|---|---|---|---|
| Creditor / Expense ledger | Dr (`Yes`) | ✓ | Reduces liability |
| Bank/Cash ledger | Cr (`No`) | ✓ | Reduces bank balance |

**Real Tally output (Payment Double):**
```
ledgerentries:
  U Sundry Debtors    Dr=Yes  Party=Yes  ₹100  bills=[{AgstRef, Debtors Test}, {Advance, 1}]
  U Bank Ledger Test  Dr=No   Party=Yes  ₹100
```

---

## Contra

A transfer between two bank/cash accounts (e.g. withdraw cash from bank, or deposit cash to bank).

The Contra form has two sections:

### Account (To — Deposit Into)
The account **receiving** the money (Dr). Example: if depositing cash to bank, this is the Bank account.

### Particulars (From — Withdraw From)
The account **sending** the money (Cr). Example: Cash account.

Enter the amount in Particulars; click ↺ in the Account section to sync the total automatically.

**What this produces in ledgerentries:**

| Ledger | Dr/Cr | Party | Notes |
|---|---|---|---|
| Destination (bank) | Dr (`Yes`) | ✓ | |
| Source (cash) | Cr (`No`) | ✓ | |

**Real Tally output (Single):**
```
ledgerentries:
  Cash  Dr=No   Party=Yes  ₹100   (withdraw from cash)
  Cash  Dr=Yes  Party=Yes  ₹100   (deposit to bank — same ledger in this example)
```

---

## Journal

A free-form double-entry voucher for adjustments, accruals, provisions, TDS, forex, etc.

The Journal form shows a two-column grid: **Ledger | Dr Amount | Cr Amount | Bill Ref**.

- Click **+ Add Line** for each accounting leg
- Enter the amount in the **Dr** column for debit entries, **Cr** column for credit entries (clicking the dashed button switches the side)
- The balance indicator at the bottom shows **✓ Balanced** when Dr total = Cr total
- Set **voucher_total** = the total Dr (or Cr) side amount
- Mark `Party` checkbox on any ledger that is the party (for bill-wise tracking)

**Typical patterns:**

| Use case | Dr | Cr |
|---|---|---|
| TDS payable | Expense ledger | TDS Payable ledger |
| Depreciation | Depreciation expense | Fixed Asset (Accumulated Dep.) |
| Provision | Expense ledger | Provision ledger |
| Forex gain | Bank ledger | Forex Gain/Loss ledger |
| Inter-company | Debtor / Creditor | Bank / Income |

**Real Tally output:**
```
ledgerentries:
  U Bank Ledger Test   Dr=Yes  Party=Yes  ₹200
  U Test Again         Dr=No   Party=No   ₹200
```

---

## Credit Note

A reduction to a previously issued sales invoice (customer return, price correction).

Same form as Sales. Fill it exactly like a Sales voucher but:
- **Original Inv No / Date** (header): the invoice being reversed
- Items: return quantities (will be negative in Tally's accounting)
- Ledger Allocations: same pattern as Sales — party Dr, sales Cr

The `voucher_base_type = CreditNote` tells Tally this reverses a sales entry.

---

## Debit Note

A reduction to a purchase bill (supplier return, overcharge correction).

Same form as Purchase but:
- **Original Bill No / Date** (header): the purchase bill being reversed
- Ledger Allocations: same pattern as Purchase — party Cr, purchase Dr

---

## Quick Reference — Ledger Entry Rules

| Voucher | Party ledger | Dr/Cr | Other leg | Dr/Cr |
|---|---|---|---|---|
| Sales | Customer (debtor) | **Dr** | Sales / Tax | **Cr** |
| Purchase | Supplier (creditor) | **Cr** | Purchase / Tax | **Dr** |
| Receipt | Customer | **Cr** | Bank/Cash | **Dr** |
| Payment | Creditor / Expense | **Dr** | Bank/Cash | **Cr** |
| Contra | Destination (bank) | **Dr** | Source (cash) | **Cr** |
| Journal | (any) | Dr | (any) | Cr — must balance |
| Credit Note | Customer | **Dr** | Sales | **Cr** |
| Debit Note | Supplier | **Cr** | Purchase | **Dr** |

---

## Common Mistakes

| Mistake | Symptom | Fix |
|---|---|---|
| Skipped Ledger Allocations | `ledgerentries: []` | Always add at least the party ledger entry |
| Wrong Dr/Cr on party | Tally posts to wrong side | Sales party = Dr; Purchase party = Cr |
| Party checkbox not set | Bill-wise tracking breaks | Check ✓ Party on the customer/supplier ledger line |
| No Sales Ledger on item | `SalesLedger` is blank | Expand each item row and select the income/purchase account |
| Filling Accounting Allocations manually | Duplicate or wrong entries | Leave it empty — auto-generated from Sales Ledger by the system |
| Grand Total mismatch | Tally rejects voucher | Click ↺ to recompute from items + ledger totals |
| `IsInvoice` unchecked on tax invoice | No invoice number generated | Check ✓ Is Invoice for all formal GST invoices |
