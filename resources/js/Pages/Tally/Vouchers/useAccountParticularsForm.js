import { computed } from 'vue'
import { emptyBillRef, emptyBankAlloc, BANK_TRANSACTION_TYPES, TRANSFER_MODES, fmt } from './voucherHelpers.js'

/**
 * Shared logic for Receipt and Payment voucher forms.
 * Both have the same structure: one Account (Bank/Cash) entry at top,
 * then a list of Particulars (party ledgers). Only difference is the
 * Dr/Cr direction for account vs particulars.
 *
 * @param {Object} props  - Vue props: { form, ledgers, isEditing }
 * @param {Object} config - { voucherNoLabel, voucherNoPlaceholder,
 *                            accountDeemedPositive, particularDeemedPositive }
 */
export function useAccountParticularsForm(props, config = {}) {
    const {
        voucherNoLabel            = 'Voucher No',
        voucherNoPlaceholder      = 'e.g. VCH-001',
        accountDeemedPositive     = true,
        particularDeemedPositive  = false,
    } = config

    const accountEntry = computed(() => props.form.ledger_entries.find(le => !le.is_party_ledger) ?? null)
    const particulars  = computed(() => props.form.ledger_entries.filter(le => le.is_party_ledger))

    function ensureAccountEntry() {
        if (!accountEntry.value) {
            props.form.ledger_entries.unshift({
                ledger_name: '', ledger_group: '', ledger_amount: '',
                is_deemed_positive: accountDeemedPositive, is_party_ledger: false,
                igst_rate: '', hsn_code: '', cess_rate: '',
                bills_allocation: [], bank_allocation_details: [],
            })
        }
    }

    function onAccountLedgerChange() {
        const m = props.ledgers.find(l => l.ledger_name === accountEntry.value?.ledger_name)
        if (m?.group_name && accountEntry.value) accountEntry.value.ledger_group = m.group_name
    }

    const particularsTotal = computed(() =>
        particulars.value.reduce((s, le) => s + (parseFloat(le.ledger_amount) || 0), 0)
    )

    function syncAccountAmount() {
        if (accountEntry.value) accountEntry.value.ledger_amount = particularsTotal.value || ''
        props.form.voucher_total = particularsTotal.value || ''
    }

    function addParticular() {
        props.form.ledger_entries.push({
            ledger_name: '', ledger_group: '', ledger_amount: '',
            is_deemed_positive: particularDeemedPositive, is_party_ledger: true,
            igst_rate: '', hsn_code: '', cess_rate: '',
            bills_allocation: [], bank_allocation_details: [],
        })
    }

    function removeParticular(le) {
        const idx = props.form.ledger_entries.indexOf(le)
        if (idx !== -1) props.form.ledger_entries.splice(idx, 1)
    }

    function onParticularLedgerChange(le) {
        const m = props.ledgers.find(l => l.ledger_name === le.ledger_name)
        if (m?.group_name) le.ledger_group = m.group_name
    }

    function addBillRef(le)       { le.bills_allocation.push(emptyBillRef('Agst Ref')) }
    function removeBillRef(le, j) { le.bills_allocation.splice(j, 1) }

    function addBankAlloc(le)       { le.bank_allocation_details.push(emptyBankAlloc()) }
    function removeBankAlloc(le, j) { le.bank_allocation_details.splice(j, 1) }

    if (!accountEntry.value) ensureAccountEntry()

    return {
        voucherNoLabel,
        voucherNoPlaceholder,
        accountEntry,
        particulars,
        particularsTotal,
        ensureAccountEntry,
        onAccountLedgerChange,
        syncAccountAmount,
        addParticular,
        removeParticular,
        onParticularLedgerChange,
        addBillRef,
        removeBillRef,
        addBankAlloc,
        removeBankAlloc,
        BANK_TRANSACTION_TYPES,
        TRANSFER_MODES,
        fmt,
    }
}
