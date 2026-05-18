import { computed, ref, watch } from 'vue'
import { emptyBillRef, emptyBankAlloc, BANK_TRANSACTION_TYPES, TRANSFER_MODES, fmt } from './voucherHelpers.js'

/**
 * Shared logic for Receipt and Payment voucher forms.
 * Both have the same structure: one Account (Bank/Cash) entry at top,
 * then a list of Particulars (party ledgers). Only difference is the
 * Dr/Cr direction for account vs particulars.
 *
 * @param {Object} props  - Vue props: { form, ledgers, isEditing }
 * @param {Object} config - { voucherNoLabel, voucherNoPlaceholder,
 *                            accountDeemedPositive, particularDeemedPositive, tenantId }
 */
export function useAccountParticularsForm(props, config = {}) {
    const {
        voucherNoLabel            = 'Voucher No',
        voucherNoPlaceholder      = 'e.g. VCH-001',
        accountDeemedPositive     = true,
        particularDeemedPositive  = false,
        tenantId                  = null,
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
        if (accountEntry.value) accountEntry.value.ledger_amount = parseFloat(particularsTotal.value.toFixed(2)) || ''
        props.form.voucher_total = parseFloat(particularsTotal.value.toFixed(2)) || ''
    }

    // Reactively keep voucher_total, account amount, and party_name in sync
    watch(particularsTotal, (total) => {
        props.form.voucher_total = parseFloat(total.toFixed(2)) || ''
        if (accountEntry.value) accountEntry.value.ledger_amount = parseFloat(total.toFixed(2)) || ''
    })
    watch(() => particulars.value[0]?.ledger_name, (name) => {
        props.form.party_name = name || ''
    }, { immediate: true })

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

    // ── Outstanding bills ─────────────────────────────────────────────────────
    // billsMap: { [partyName]: Bill[] }  — null means "not yet fetched"
    const billsMap   = ref({})
    const loadingBills = ref(false)

    async function fetchBillsForParty(partyName) {
        if (!tenantId || !partyName) return
        if (billsMap.value[partyName] !== undefined) return  // already fetched
        loadingBills.value = true
        try {
            const { data } = await window.axios.get(
                route('tally.vouchers.outstanding-bills', { tenant: tenantId }),
                { params: { party_name: partyName } }
            )
            billsMap.value = { ...billsMap.value, [partyName]: data }
        } catch {
            billsMap.value = { ...billsMap.value, [partyName]: [] }
        } finally {
            loadingBills.value = false
        }
    }

    function outstandingBillsFor(partyName) {
        return billsMap.value[partyName] ?? []
    }

    function isBillSelected(bill, le) {
        return le.bills_allocation.some(br => br.AgstType === 'Agst Ref' && br.Reference === bill.reference)
    }

    function toggleBill(bill, le) {
        if (isBillSelected(bill, le)) {
            const idx = le.bills_allocation.findIndex(br => br.AgstType === 'Agst Ref' && br.Reference === bill.reference)
            le.bills_allocation.splice(idx, 1)
        } else {
            le.bills_allocation.push({
                AgstType: 'Agst Ref',
                Reference: bill.reference,
                CreditPeriod: bill.invoice_date,
                Amount: bill.outstanding,
            })
        }
        le.ledger_amount = le.bills_allocation.reduce((s, br) => s + (parseFloat(br.Amount) || 0), 0)
        syncAccountAmount()
    }

    function onParticularLedgerChange(le) {
        const m = props.ledgers.find(l => l.ledger_name === le.ledger_name)
        if (m?.group_name) le.ledger_group = m.group_name
        fetchBillsForParty(le.ledger_name)
    }

    function addBillRef(le)       { le.bills_allocation.push(emptyBillRef('Agst Ref')) }
    function removeBillRef(le, j) { le.bills_allocation.splice(j, 1) }

    function addBankAlloc(le)       { le.bank_allocation_details.push(emptyBankAlloc()) }
    function removeBankAlloc(le, j) { le.bank_allocation_details.splice(j, 1) }

    if (!accountEntry.value) ensureAccountEntry()

    // Pre-fetch bills for any party ledgers already present (edit mode)
    watch(particulars, (list) => {
        list.forEach(le => { if (le.ledger_name) fetchBillsForParty(le.ledger_name) })
    }, { immediate: true })

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
        // Outstanding bills
        loadingBills,
        outstandingBillsFor,
        isBillSelected,
        toggleBill,
        BANK_TRANSACTION_TYPES,
        TRANSFER_MODES,
        fmt,
    }
}
