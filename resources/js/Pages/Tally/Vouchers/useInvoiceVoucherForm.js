import { ref, computed, watch } from 'vue'
import { fmt, emptyBillRef } from './voucherHelpers.js'

/**
 * Shared logic for Sales, Purchase, CreditNote, DebitNote voucher forms.
 * Each form calls this composable with its own config so it can have its own
 * independent template without duplicating ~200 lines of reactive state.
 *
 * @param {Object} props  - Vue props: { form, ledgers, stockItems, godowns, isEditing }
 * @param {Object} config - { voucherNoLabel, voucherNoPlaceholder, partyType }
 */
export function useInvoiceVoucherForm(props, config = {}) {
    const {
        voucherNoLabel           = 'Voucher No',
        voucherNoPlaceholder     = 'e.g. VCH-001',
        partyType                = 'debtor',   // 'debtor' | 'creditor'
        defaultMode              = 'item',     // 'item' | 'accounting' | 'voucher'
        syncIsInvoice            = false,      // if true, mode drives form.is_invoice
        inventoryDeemedPositive  = false,      // true for Purchase/DebitNote (stock in)
    } = config

    // ── Mode ─────────────────────────────────────────────────────────────────
    const mode = ref(defaultMode)

    watch(mode, (m) => {
        if (m === 'accounting') props.form.inventory_entries = []
        grandTotalLocked.value = false
    })

    // Keep is_invoice in sync with mode (opt-in per form)
    if (syncIsInvoice) {
        watch(mode, (m) => { props.form.is_invoice = m !== 'voucher' }, { immediate: true })
    }

    // ── Maps ──────────────────────────────────────────────────────────────────
    const stockItemMap = computed(() => {
        const m = {}
        props.stockItems.forEach(s => { m[s.name] = s })
        return m
    })

    const ledgerMap = computed(() => {
        const m = {}
        props.ledgers.forEach(l => { m[l.ledger_name] = l })
        return m
    })

    const partyLedgers = computed(() => {
        const keyword = partyType === 'creditor' ? 'sundry creditor' : 'sundry debtor'
        const filtered = props.ledgers.filter(l => l.group_name?.toLowerCase().includes(keyword))
        return filtered.length ? filtered : props.ledgers
    })

    // Ledgers suitable as a sales/income ledger (Sales Accounts or Direct Incomes)
    const salesLedgers = computed(() => {
        const salesGroups = ['sales account', 'direct income']
        const filtered = props.ledgers.filter(l =>
            salesGroups.some(g => l.group_name?.toLowerCase().includes(g))
        )
        return filtered.length ? filtered : props.ledgers
    })

    // In item invoice mode the sales/purchase ledger is handled by AccountingAllocations
    // inside the inventory entry — it must NOT appear in ledger entries too or Tally
    // will double-count the credit/debit and reject with "Voucher totals do not match".
    const itemModeExcludedGroups = partyType === 'creditor'
        ? ['purchase account', 'direct expense']
        : ['sales account', 'direct income']

    const ledgerEntryOptions = computed(() => {
        if (mode.value !== 'item') return props.ledgers
        return props.ledgers.filter(l =>
            !itemModeExcludedGroups.some(g => l.group_name?.toLowerCase().includes(g))
        )
    })

    // ── Common sales ledger (applies to all inventory items) ──────────────────
    const commonSalesLedger = ref('')

    function applyCommonSalesLedger() {
        if (!commonSalesLedger.value) return
        props.form.inventory_entries.forEach(ie => {
            ie.sales_ledger = commonSalesLedger.value
            onSalesLedgerChange(ie)
        })
    }

    // ── Item helpers ──────────────────────────────────────────────────────────
    const expandedItems = ref(new Set())

    function toggleExpand(i) {
        const s = new Set(expandedItems.value)
        s.has(i) ? s.delete(i) : s.add(i)
        expandedItems.value = s
    }

    function recalcItemAmount(ie) {
        const qty  = parseFloat(ie.billed_qty)      || 0
        const rate = parseFloat(ie.rate)             || 0
        const disc = parseFloat(ie.discount_percent) || 0
        if (qty && rate) ie.amount = parseFloat((qty * rate * (1 - disc / 100)).toFixed(2))
        // Keep auto-generated accounting allocation in sync
        const alloc = ie.accounting_allocations
        if (alloc?.length === 1 && alloc[0].LedgerName === ie.sales_ledger) {
            const igst = parseFloat(ie.igst_rate) || 0
            alloc[0].Amount            = parseFloat(ie.amount) || 0
            alloc[0].IGSTRate          = igst
            alloc[0].GSTClassification = igst > 0 ? 'Taxable' : 'Not Applicable'
        }
    }

    function onSalesLedgerChange(ie) {
        if (!ie.sales_ledger) {
            ie.accounting_allocations = []
            return
        }
        const ledger = ledgerMap.value[ie.sales_ledger]
        const igst   = parseFloat(ie.igst_rate) || 0
        ie.accounting_allocations = [{
            LedgerName:        ie.sales_ledger,
            LedgerGroup:       ledger?.group_name || '',
            GSTClassification: igst > 0 ? 'Taxable' : 'Not Applicable',
            IGSTRate:          igst,
            Amount:            parseFloat(ie.amount) || 0,
        }]
    }

    function onStockItemChange(ie) {
        const m = stockItemMap.value[ie.stock_item_name]
        if (!m) return
        if (m.hsn_code)          ie.hsn_code  = m.hsn_code
        if (m.unit_name)         ie.unit       = m.unit_name
        if (m.igst_rate != null) ie.igst_rate  = m.igst_rate
        if (m.cess_rate != null) ie.cess_rate  = m.cess_rate
        if (m.stock_group_name)  ie.group_name = m.stock_group_name
        if (m.mrp_rate  != null) ie.mrp        = m.mrp_rate
        if (m.opening_rate != null && m.opening_rate !== 0) ie.rate = m.opening_rate
    }

    function emptyInventory() {
        return {
            stock_item_name: '', item_code: '', group_name: '', hsn_code: '', unit: '',
            billed_qty: '', actual_qty: '', rate: '', igst_rate: '', cess_rate: '',
            discount_percent: '', amount: '', tax_amount: '', mrp: '',
            sales_ledger: '', godown_name: '', batch_name: '',
            is_deemed_positive: inventoryDeemedPositive,
            batch_allocations: [], accounting_allocations: [],
        }
    }

    function addInventory() { props.form.inventory_entries.push(emptyInventory()) }

    function removeInventory(i) {
        props.form.inventory_entries.splice(i, 1)
        const s = new Set()
        for (const idx of expandedItems.value) {
            if (idx < i) s.add(idx)
            else if (idx > i) s.add(idx - 1)
        }
        expandedItems.value = s
    }

    // ── Party auto-fill (Tally-style: party select → populate all buyer fields) ─
    function onPartyChange(form) {
        const m = ledgerMap.value[form.party_name]
        if (!m) return

        // Buyer name — prefer MailingName (the "print name" in Tally) over LedgerName
        form.buyer_name  = m.mailing_name  || form.party_name
        form.buyer_alias = ''

        // GST / tax details
        form.buyer_gstin                 = m.gstin_number || ''
        form.buyer_gst_registration_type = m.gst_type     || ''

        // Address — Tally stores addresses as [{Address:"line1"},{Address:"line2"}]
        const lines = Array.isArray(m.addresses)
            ? m.addresses.map(a => a.Address || a.address || '').filter(Boolean)
            : []
        form.buyer_address = lines.join('\n')

        // Geographic
        form.buyer_state   = m.state_name   || ''
        form.buyer_country = m.country_name || 'India'
        form.buyer_pin_code = m.pin_code    || ''

        // Contact
        form.buyer_mobile = m.mobile_number       || ''
        form.buyer_email  = m.contact_person_email || ''

        // Also mark any already-added ledger entry that matches the party as IsPartyLedger
        form.ledger_entries.forEach(le => {
            le.is_party_ledger = le.ledger_name === form.party_name
        })
    }

    // ── Ledger entry helpers ──────────────────────────────────────────────────
    function onLedgerChange(le, form) {
        const m = ledgerMap.value[le.ledger_name]
        if (!m) return
        if (m.group_name) le.ledger_group = m.group_name
        // Auto-fill tax fields from the ledger master (mirrors Tally behaviour)
        // Only fill if the ledger entry itself doesn't already have values
        // (Tally stores igst_rate on the stock item, not the ledger — leave blank for tax ledgers)
        // Mark as party ledger when this entry's ledger matches the voucher's party
        if (form?.party_name && le.ledger_name === form.party_name) {
            le.is_party_ledger = true
        }
    }

    function emptyLedger() {
        return {
            ledger_name: '', ledger_group: '', ledger_amount: '',
            is_deemed_positive: false, is_party_ledger: false,
            igst_rate: '', hsn_code: '', cess_rate: '',
            bills_allocation: [], bank_allocation_details: [],
        }
    }

    function addLedger()     { props.form.ledger_entries.push(emptyLedger()) }
    function removeLedger(i) { props.form.ledger_entries.splice(i, 1) }

    function addBillRef(i)       { props.form.ledger_entries[i].bills_allocation.push(emptyBillRef()) }
    function removeBillRef(i, j) { props.form.ledger_entries[i].bills_allocation.splice(j, 1) }

    function emptyBatchAlloc() {
        return { BatchName: '', ExpiryDate: '', GodownName: '', ActualQty: '', BilledQty: '', Rate: '', DiscountPercent: '', Amount: '' }
    }
    function addBatchAlloc(i)       { props.form.inventory_entries[i].batch_allocations.push(emptyBatchAlloc()) }
    function removeBatchAlloc(i, j) { props.form.inventory_entries[i].batch_allocations.splice(j, 1) }

    function emptyAccAlloc() {
        return { LedgerName: '', LedgerGroup: '', GSTClassification: '', IGSTRate: '', Amount: '' }
    }
    function addAccAlloc(i)       { props.form.inventory_entries[i].accounting_allocations.push(emptyAccAlloc()) }
    function removeAccAlloc(i, j) { props.form.inventory_entries[i].accounting_allocations.splice(j, 1) }

    // ── Totals ────────────────────────────────────────────────────────────────
    const taxableTotal = computed(() =>
        props.form.inventory_entries.reduce((s, ie) => s + (parseFloat(ie.amount) || 0), 0)
    )
    const ledgerTotal = computed(() =>
        props.form.ledger_entries.reduce((s, le) => s + (parseFloat(le.ledger_amount) || 0), 0)
    )
    const grandTotalLocked = ref(false)

    watch(taxableTotal, (v) => {
        if (!grandTotalLocked.value && mode.value === 'item') props.form.voucher_total = v || ''
    })
    watch(ledgerTotal, (v) => {
        if (!grandTotalLocked.value && mode.value === 'accounting') props.form.voucher_total = v || ''
    })

    function resetGrandTotal() {
        grandTotalLocked.value = false
        props.form.voucher_total = (mode.value === 'item' ? taxableTotal.value : ledgerTotal.value) || ''
    }

    // ── Auto tax suggestion ───────────────────────────────────────────────────
    const autoTaxGroups = computed(() => {
        const groups = {}
        props.form.inventory_entries.forEach(ie => {
            const igst = parseFloat(ie.igst_rate) || 0
            const cess = parseFloat(ie.cess_rate) || 0
            const amt  = parseFloat(ie.amount) || 0
            if (igst > 0) {
                const k = `igst_${igst}`
                if (!groups[k]) groups[k] = { label: `IGST ${igst}%`, rate: igst, taxable: 0, type: 'igst' }
                groups[k].taxable += amt
            }
            if (cess > 0) {
                const k = `cess_${cess}`
                if (!groups[k]) groups[k] = { label: `CESS ${cess}%`, rate: cess, taxable: 0, type: 'cess' }
                groups[k].taxable += amt
            }
        })
        return Object.values(groups).map(g => ({
            ...g,
            amount: parseFloat((g.taxable * g.rate / 100).toFixed(2)),
        }))
    })

    function suggestTaxLines() {
        autoTaxGroups.value.forEach(g => {
            props.form.ledger_entries.push({
                ledger_name: '', ledger_group: 'Duties & Taxes',
                ledger_amount: g.amount,
                is_deemed_positive: false, is_party_ledger: false,
                igst_rate: g.type === 'igst' ? g.rate : '',
                hsn_code: '', cess_rate: g.type === 'cess' ? g.rate : '',
                bills_allocation: [], bank_allocation_details: [],
                _suggestLabel: g.label,
            })
        })
    }

    // ── Buyer address lines ───────────────────────────────────────────────────
    const buyerAddressLines = ref(
        props.form.buyer_address ? String(props.form.buyer_address).split('\n') : ['']
    )
    watch(buyerAddressLines, (lines) => { props.form.buyer_address = lines.join('\n') }, { deep: true })
    watch(() => props.form.buyer_address, (val) => {
        const inc = val ? String(val).split('\n') : ['']
        if (JSON.stringify(inc) !== JSON.stringify(buyerAddressLines.value)) buyerAddressLines.value = inc
    })
    function addAddressLine()     { buyerAddressLines.value.push('') }
    function removeAddressLine(i) { if (buyerAddressLines.value.length > 1) buyerAddressLines.value.splice(i, 1) }

    // ── Consignee address lines ───────────────────────────────────────────────
    const consigneeAddressLines = ref(
        props.form.consignee_address ? String(props.form.consignee_address).split('\n') : ['']
    )
    watch(consigneeAddressLines, (lines) => { props.form.consignee_address = lines.join('\n') }, { deep: true })
    watch(() => props.form.consignee_address, (val) => {
        const inc = val ? String(val).split('\n') : ['']
        if (JSON.stringify(inc) !== JSON.stringify(consigneeAddressLines.value)) consigneeAddressLines.value = inc
    })
    function addConsigneeAddressLine()     { consigneeAddressLines.value.push('') }
    function removeConsigneeAddressLine(i) { if (consigneeAddressLines.value.length > 1) consigneeAddressLines.value.splice(i, 1) }

    // ── Constants ─────────────────────────────────────────────────────────────
    const INDIAN_STATES = [
        'Andaman and Nicobar Islands','Andhra Pradesh','Arunachal Pradesh','Assam',
        'Bihar','Chandigarh','Chhattisgarh','Dadra and Nagar Haveli and Daman and Diu',
        'Delhi','Goa','Gujarat','Haryana','Himachal Pradesh','Jammu and Kashmir',
        'Jharkhand','Karnataka','Kerala','Ladakh','Lakshadweep','Madhya Pradesh',
        'Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha',
        'Puducherry','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana',
        'Tripura','Uttar Pradesh','Uttarakhand','West Bengal',
    ]
    const GST_REG_TYPES = [
        'Regular','Composition','Unregistered/Consumer','Unknown',
        'Input Service Distributor','SEZ','Overseas','Deemed Export',
    ]
    const GST_CLASSIFICATIONS = ['Not Applicable','Taxable','Nil Rated','Exempt','Non-GST Supply']

    return {
        // Config-derived labels
        voucherNoLabel,
        voucherNoPlaceholder,
        // Reactive state
        mode,
        expandedItems,
        grandTotalLocked,
        buyerAddressLines,
        // Computed
        partyLedgers,
        salesLedgers,
        ledgerEntryOptions,
        taxableTotal,
        ledgerTotal,
        autoTaxGroups,
        // Common sales ledger
        commonSalesLedger,
        applyCommonSalesLedger,
        // Party / ledger functions
        onPartyChange,
        // Item functions
        toggleExpand,
        recalcItemAmount,
        onStockItemChange,
        onSalesLedgerChange,
        addInventory,
        removeInventory,
        addBatchAlloc,
        removeBatchAlloc,
        addAccAlloc,
        removeAccAlloc,
        // Ledger functions
        onLedgerChange,
        addLedger,
        removeLedger,
        addBillRef,
        removeBillRef,
        // Total functions
        resetGrandTotal,
        suggestTaxLines,
        // Address functions
        addAddressLine,
        removeAddressLine,
        consigneeAddressLines,
        addConsigneeAddressLine,
        removeConsigneeAddressLine,
        // Constants
        INDIAN_STATES,
        GST_REG_TYPES,
        GST_CLASSIFICATIONS,
        fmt,
    }
}
