<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
    form:       Object,
    ledgers:    Array,
    stockItems: Array,
    godowns:    Array,
    isEditing:  Boolean,
})

const mode          = ref('item')
const expandedItems = ref(new Set())

watch(mode, (val) => {
    if (val === 'accounting') {
        props.form.inventory_entries = []
        expandedItems.value = new Set()
    }
    grandTotalLocked.value = false
})

function toggleExpand(i) {
    const s = new Set(expandedItems.value)
    s.has(i) ? s.delete(i) : s.add(i)
    expandedItems.value = s
}

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

const sundryCreditorLedgers = computed(() => {
    const f = props.ledgers.filter(l =>
        l.group_name?.toLowerCase().includes('sundry creditor') ||
        l.group_name?.toLowerCase().includes('sundry supplier')
    )
    return f.length ? f : props.ledgers
})

function recalcItemAmount(ie) {
    const qty  = parseFloat(ie.billed_qty)       || 0
    const rate = parseFloat(ie.rate)             || 0
    const disc = parseFloat(ie.discount_percent) || 0
    if (qty && rate) ie.amount = parseFloat((qty * rate * (1 - disc / 100)).toFixed(2))
}

const taxableValue    = computed(() => props.form.inventory_entries.reduce((s, ie) => s + (parseFloat(ie.amount) || 0), 0))
const ledgerTotal     = computed(() => props.form.ledger_entries.reduce((s, le) => s + (parseFloat(le.ledger_amount) || 0), 0))
const grandTotalLocked = ref(false)

watch(taxableValue, (v) => {
    if (!grandTotalLocked.value && mode.value === 'item') props.form.voucher_total = v || ''
})
watch(ledgerTotal, (v) => {
    if (!grandTotalLocked.value && mode.value === 'accounting') props.form.voucher_total = v || ''
})
function resetGrandTotal() {
    grandTotalLocked.value = false
    props.form.voucher_total = (mode.value === 'item' ? taxableValue.value : ledgerTotal.value) || ''
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
function onLedgerChange(le) {
    const m = ledgerMap.value[le.ledger_name]
    if (m?.group_name) le.ledger_group = m.group_name
}

function emptyInventory() {
    return {
        stock_item_name: '', item_code: '', group_name: '', hsn_code: '', unit: '',
        billed_qty: '', actual_qty: '', rate: '', igst_rate: '', cess_rate: '',
        discount_percent: '', amount: '', tax_amount: '', mrp: '',
        sales_ledger: '', godown_name: '', batch_name: '',
        is_deemed_positive: false, batch_allocations: [], accounting_allocations: [],
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

function emptyLedger() {
    return {
        ledger_name: '', ledger_group: '', ledger_amount: '',
        is_deemed_positive: true, is_party_ledger: false,
        igst_rate: '', hsn_code: '', cess_rate: '',
        bills_allocation: [], bank_allocation_details: [],
    }
}
function addLedger()     { props.form.ledger_entries.push(emptyLedger()) }
function removeLedger(i) { props.form.ledger_entries.splice(i, 1) }

function emptyBillRef() { return { AgstType: 'New Ref', Reference: '', CreditPeriod: '', Amount: '' } }
function addBillRef(i)       { props.form.ledger_entries[i].bills_allocation.push(emptyBillRef()) }
function removeBillRef(i, j) { props.form.ledger_entries[i].bills_allocation.splice(j, 1) }

const INDIAN_STATES = [
    'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam',
    'Bihar', 'Chandigarh', 'Chhattisgarh',
    'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa', 'Gujarat',
    'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand',
    'Karnataka', 'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh',
    'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland',
    'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim',
    'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand',
    'West Bengal',
]
const GST_REG_TYPES = [
    'Regular', 'Composition', 'Unregistered/Consumer', 'Unknown',
    'Input Service Distributor', 'SEZ', 'Overseas', 'Deemed Export',
]
function fmt(v) {
    if (v === null || v === undefined || v === '') return '—'
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(v)
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Debit Note Number</label>
            <input v-model="form.voucher_number" type="text" placeholder="e.g. DN-001"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
            <input v-model="form.voucher_date" type="date"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            <p v-if="form.errors.voucher_date" class="mt-1 text-xs text-red-500">{{ form.errors.voucher_date }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Original Purchase Ref</label>
            <input v-model="form.reference" type="text" placeholder="Original purchase number"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reference Date</label>
            <input v-model="form.reference_date" type="date"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Name <span class="text-red-500">*</span></label>
            <input v-model="form.party_name" type="text" list="dn-party-list"
                   placeholder="Select creditor ledger…"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            <datalist id="dn-party-list">
                <option v-for="l in sundryCreditorLedgers" :key="l.id" :value="l.ledger_name" />
            </datalist>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cost Centre</label>
            <input v-model="form.cost_centre" type="text" placeholder="e.g. Head Office"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>

    <div class="flex items-center gap-1 p-1 bg-gray-100 rounded-lg w-fit">
        <button type="button" @click="mode = 'item'"
                :class="['px-4 py-1.5 text-sm font-medium rounded-md transition',
                         mode === 'item' ? 'bg-white text-violet-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
            Item Debit Note
        </button>
        <button type="button" @click="mode = 'accounting'"
                :class="['px-4 py-1.5 text-sm font-medium rounded-md transition',
                         mode === 'accounting' ? 'bg-white text-violet-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
            Accounting Debit Note
        </button>
    </div>

    <div v-if="mode === 'item'">
        <div class="grid grid-cols-[2.5fr_0.8fr_0.9fr_0.9fr_0.7fr_1fr_auto] gap-2 px-2 mb-1">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Item</span>
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">UOM</span>
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Return Qty</span>
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Rate</span>
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Disc%</span>
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Amount</span>
            <span class="w-14" />
        </div>
        <div v-for="(ie, i) in form.inventory_entries" :key="i"
             class="border border-gray-200 rounded-lg mb-2 overflow-hidden">
            <div class="grid grid-cols-[2.5fr_0.8fr_0.9fr_0.9fr_0.7fr_1fr_auto] gap-2 items-center px-2 py-2 bg-white">
                <select v-model="ie.stock_item_name" @change="onStockItemChange(ie)"
                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                    <option value="">— Item —</option>
                    <option v-for="s in stockItems" :key="s.id" :value="s.name">{{ s.name }}</option>
                </select>
                <input v-model="ie.unit" type="text" placeholder="Nos"
                       class="rounded border border-gray-300 px-2 py-1.5 text-xs w-full focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.billed_qty" type="number" step="0.0001" @input="recalcItemAmount(ie)"
                       class="rounded border border-gray-300 px-2 py-1.5 text-xs w-full focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.rate" type="number" step="0.01" @input="recalcItemAmount(ie)"
                       class="rounded border border-gray-300 px-2 py-1.5 text-xs w-full focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.discount_percent" type="number" step="0.01" @input="recalcItemAmount(ie)"
                       class="rounded border border-gray-300 px-2 py-1.5 text-xs w-full focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.amount" type="number" step="0.01"
                       class="rounded border border-gray-300 px-2 py-1.5 text-xs w-full focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <div class="flex items-center gap-1 pr-1">
                    <button type="button" @click="toggleExpand(i)"
                            :class="['text-gray-400 hover:text-violet-600 transition-transform text-sm leading-none',
                                     expandedItems.has(i) ? 'rotate-180' : '']">▼</button>
                    <button type="button" @click="removeInventory(i)"
                            class="text-red-400 hover:text-red-600 text-sm leading-none">✕</button>
                </div>
            </div>
            <div v-if="expandedItems.has(i)" class="border-t border-gray-100 bg-gray-50 px-3 pt-3 pb-2 space-y-3">
                <div class="grid grid-cols-4 gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">HSN Code</label>
                        <input v-model="ie.hsn_code" type="text"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">IGST Rate %</label>
                        <input v-model="ie.igst_rate" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">CESS Rate %</label>
                        <input v-model="ie.cess_rate" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Tax Amount</label>
                        <input v-model="ie.tax_amount" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Godown</label>
                        <select v-model="ie.godown_name"
                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option value="">— Select —</option>
                            <option v-for="g in godowns" :key="g.id" :value="g.name">{{ g.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Item Code</label>
                        <input v-model="ie.item_code" type="text"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">MRP</label>
                        <input v-model="ie.mrp" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                </div>
            </div>
        </div>
        <button type="button" @click="addInventory"
                class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Item</button>
        <p v-if="!form.inventory_entries.length" class="text-xs text-gray-400 mt-1">No items. Click + Add Item.</p>
    </div>

    <div v-if="mode === 'accounting'">
        <p class="text-xs text-gray-400 mb-2">Add ledger entries directly (no inventory tracking).</p>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700">Ledger Entries</h3>
            <button type="button" @click="addLedger"
                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Entry</button>
        </div>
        <div v-for="(le, i) in form.ledger_entries" :key="i"
             class="border border-gray-200 rounded-lg p-3 space-y-2 mb-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">Entry {{ i + 1 }}
                    <span v-if="le.is_party_ledger" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-violet-100 text-violet-700">Party</span>
                </span>
                <button type="button" @click="removeLedger(i)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">Ledger Name *</label>
                    <select v-model="le.ledger_name" @change="onLedgerChange(le)"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <option value="">— Select Ledger —</option>
                        <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">Ledger Group</label>
                    <input v-model="le.ledger_group" type="text"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">Amount *</label>
                    <input v-model="le.ledger_amount" type="number" step="0.01"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">Dr / Cr</label>
                    <select v-model="le.is_deemed_positive"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <option :value="true">Dr (Debit)</option>
                        <option :value="false">Cr (Credit)</option>
                    </select>
                </div>
                <div class="flex items-end pb-1.5">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input v-model="le.is_party_ledger" type="checkbox"
                               class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                        <span class="text-xs text-gray-600">Party Ledger</span>
                    </label>
                </div>
            </div>
            <div class="pt-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-gray-500">Bill References</span>
                    <button type="button" @click="addBillRef(i)"
                            class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                </div>
                <div v-for="(br, j) in le.bills_allocation" :key="j"
                     class="grid grid-cols-4 gap-1.5 mb-1.5 items-end">
                    <div>
                        <label class="block text-xs text-gray-400 mb-0.5">Type</label>
                        <select v-model="br.AgstType"
                                class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option>New Ref</option><option>Agst Ref</option>
                            <option>On Account</option><option>Advance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-0.5">Reference</label>
                        <input v-model="br.Reference" type="text"
                               class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-0.5">Credit Period</label>
                        <input v-model="br.CreditPeriod" type="text"
                               class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div class="flex gap-1">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-400 mb-0.5">Amount</label>
                            <input v-model="br.Amount" type="number" step="0.01"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        </div>
                        <button type="button" @click="removeBillRef(i, j)"
                                class="text-red-400 hover:text-red-600 text-xs self-end pb-1">✕</button>
                    </div>
                </div>
                <p v-if="!le.bills_allocation.length" class="text-xs text-gray-400 italic">No bill references.</p>
            </div>
        </div>
        <p v-if="!form.ledger_entries.length" class="text-xs text-gray-400">No ledger entries. Click + Add Entry.</p>
    </div>

    <div class="rounded-lg bg-red-50 border border-red-200 p-4 space-y-2">
        <h3 class="text-sm font-semibold text-red-800 mb-3">Debit Note Summary</h3>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600">Return Value (from items)</span>
            <span class="font-mono font-medium text-gray-900">{{ fmt(taxableValue) }}</span>
        </div>
        <div class="flex items-center justify-between text-sm pt-1 border-t border-red-200">
            <span class="font-medium text-gray-800">Total</span>
            <div class="flex items-center gap-2">
                <input v-model="form.voucher_total" @input="grandTotalLocked = true"
                       type="number" step="0.01"
                       class="w-36 text-right rounded border border-red-300 px-2 py-1 text-sm font-mono font-medium focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white" />
                <button v-if="grandTotalLocked" type="button" @click="resetGrandTotal"
                        class="text-xs text-violet-600 hover:text-violet-800">↺</button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Place of Supply</label>
            <select v-model="form.place_of_supply"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">— Select State —</option>
                <option v-for="s in INDIAN_STATES" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>
        <div class="flex items-end pb-2">
            <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.is_invoice" type="checkbox"
                       class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                <span class="text-sm text-gray-700">Is Invoice</span>
            </label>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="Reason for debit note…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
    </div>

    <details class="border border-gray-200 rounded-lg overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50">
            Consignee Details
        </summary>
        <div class="px-4 pb-4 pt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consignee Name</label>
                    <input v-model="form.consignee_name" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consignee GSTIN</label>
                    <input v-model="form.consignee_gstin" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                    <select v-model="form.consignee_state"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select —</option>
                        <option v-for="s in INDIAN_STATES" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                    <input v-model="form.consignee_country" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
        </div>
    </details>

    <details class="border border-gray-200 rounded-lg overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50">
            e-Invoice
        </summary>
        <div class="px-4 pb-4 pt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IRN</label>
                    <input v-model="form.irn" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Acknowledgement No</label>
                    <input v-model="form.acknowledgement_no" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Acknowledgement Date</label>
                <input v-model="form.acknowledgement_date" type="text" placeholder="e.g. 20250401"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
        </div>
    </details>
</template>
