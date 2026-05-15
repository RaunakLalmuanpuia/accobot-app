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
        voucherNoLabel       = 'Voucher No',
        voucherNoPlaceholder = 'e.g. VCH-001',
        partyType            = 'debtor',   // 'debtor' | 'creditor'
    } = config

    // ── Mode ─────────────────────────────────────────────────────────────────
    const mode = ref('item')

    watch(mode, (m) => {
        if (m === 'accounting') props.form.inventory_entries = []
        grandTotalLocked.value = false
    })

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
    }

    function emptyInventory() {
        return {
            stock_item_name: '', item_code: '', group_name: '', hsn_code: '', unit: '',
            billed_qty: '', actual_qty: '', rate: '', igst_rate: '', cess_rate: '',
            discount_percent: '', amount: '', tax_amount: '', mrp: '',
            sales_ledger: '', godown_name: '', batch_name: '',
            is_deemed_positive: false,
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

    // ── Ledger entry helpers ──────────────────────────────────────────────────
    function onLedgerChange(le) {
        const m = ledgerMap.value[le.ledger_name]
        if (m?.group_name) le.ledger_group = m.group_name
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
        taxableTotal,
        ledgerTotal,
        autoTaxGroups,
        // Item functions
        toggleExpand,
        recalcItemAmount,
        onStockItemChange,
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
        // Constants
        INDIAN_STATES,
        GST_REG_TYPES,
        GST_CLASSIFICATIONS,
        fmt,
    }
}
