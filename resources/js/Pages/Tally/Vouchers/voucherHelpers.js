/**
 * Shared helpers used across all Tally voucher forms.
 * Pure functions and constants — no reactive state.
 */

export function fmt(v) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency', currency: 'INR', maximumFractionDigits: 2,
    }).format(v || 0)
}

export function emptyBillRef(defaultAgst = 'New Ref') {
    return { AgstType: defaultAgst, Reference: '', CreditPeriod: '', Amount: '' }
}

export function emptyBankAlloc() {
    return {
        TRANSACTIONTYPE: '', BANKNAME: '', AMOUNT: '',
        Date: '', InstrumentDate: '', IFSCODE: '',
        ACCOUNTNUMBER: '', PAYMENTFAVOURING: '', TRANSFERMODE: '',
        INSTRUMENTNUMBER: '', BankersDate: '',
    }
}

export const BANK_TRANSACTION_TYPES = [
    'Same Bank Transfer', 'Inter Bank Transfer', 'Cash', 'Cheque', 'DD',
    'Electronic Cheque', 'Electronic DD/PO', 'E-Payments',
]

export const TRANSFER_MODES = ['NEFT', 'RTGS', 'IMPS', 'UPI', 'Cheque', 'DD', 'Cash']
