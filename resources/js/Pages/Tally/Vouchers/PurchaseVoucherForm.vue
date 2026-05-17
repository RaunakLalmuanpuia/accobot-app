<script setup>
import { computed, ref } from 'vue'
import { useInvoiceVoucherForm } from './useInvoiceVoucherForm.js'
import VoucherGuide from './VoucherGuide.vue'

const props = defineProps({
    form:        Object,
    ledgers:     Array,
    stockItems:  Array,
    godowns:     Array,
    isEditing:   Boolean,
    defaultMode: { type: String, default: 'item' },
})

const {
    voucherNoLabel, voucherNoPlaceholder,
    mode, expandedItems, grandTotalLocked,
    buyerAddressLines,
    partyLedgers, ledgerEntryOptions, taxableTotal, ledgerTotal, autoTaxGroups,
    commonSalesLedger, applyCommonSalesLedger,
    onPartyChange,
    toggleExpand, recalcItemAmount, onStockItemChange,
    addInventory, removeInventory,
    addBatchAlloc, removeBatchAlloc,
    addAccAlloc, removeAccAlloc,
    onLedgerChange, addLedger, removeLedger, addBillRef, removeBillRef,
    resetGrandTotal, suggestTaxLines,
    addAddressLine, removeAddressLine,
    INDIAN_STATES, GST_REG_TYPES, fmt,
} = useInvoiceVoucherForm(props, {
    voucherNoLabel:       'Voucher No',
    voucherNoPlaceholder: 'e.g. BILL-001',
    partyType:            'creditor',
    defaultMode:             props.defaultMode,
    syncIsInvoice:           true,
    inventoryDeemedPositive: true,
})

const purchaseLedgers = computed(() => {
    const groups = ['purchase account', 'direct expense']
    const filtered = props.ledgers.filter(l =>
        groups.some(g => l.group_name?.toLowerCase().includes(g))
    )
    return filtered.length ? filtered : props.ledgers
})

const showGuide = ref(false)

const MODE_LABELS = { item: 'Ledger Allocations', accounting: 'Accounting Lines', voucher: 'Dr / Cr Entries' }
const MODE_HINT   = {
    item:       'Add the supplier (Cr + Party) and any tax ledgers (Dr). The purchase ledger is already set via the Purchase Ledger row above — do not add it here.',
    accounting: 'Add the supplier ledger (Cr + Party) and the purchase / expense ledger (Dr).',
    voucher:    'Enter Dr and Cr entries. Mark the supplier ledger as Party.',
}
</script>

<template>
    <VoucherGuide :show="showGuide" voucher-type="Purchase" @close="showGuide = false" />

    <!-- ── HEADER ────────────────────────────────────────────────────────────── -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
        <div class="flex items-start justify-between gap-3">
        <div class="grid grid-cols-4 gap-3 flex-1">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ voucherNoLabel }}</label>
                <input v-model="form.voucher_number" type="text" :placeholder="voucherNoPlaceholder"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Date <span class="text-red-500">*</span></label>
                <input v-model="form.voucher_date" type="date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                <p v-if="form.errors?.voucher_date" class="mt-0.5 text-xs text-red-500">{{ form.errors.voucher_date }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Supplier's Inv. No.</label>
                <input v-model="form.reference" type="text" placeholder="e.g. SUP-INV-001"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Supplier's Inv. Date</label>
                <input v-model="form.reference_date" type="date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
        </div>
        <button type="button" @click="showGuide = true"
                class="flex items-center gap-1.5 text-xs text-violet-600 hover:text-violet-800 border border-violet-200 hover:border-violet-300 rounded-lg px-3 py-1.5 bg-white transition whitespace-nowrap mt-0.5">
            <span>?</span> Guide
        </button>
        </div>

        <!-- 3-mode toggle -->
        <div class="flex items-center gap-1 p-0.5 bg-gray-200 rounded-lg w-fit text-xs">
            <button type="button" @click="mode = 'item'"
                    :class="['px-3 py-1 rounded-md font-medium transition',
                             mode === 'item' ? 'bg-white text-violet-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                Item Invoice
            </button>
            <button type="button" @click="mode = 'accounting'"
                    :class="['px-3 py-1 rounded-md font-medium transition',
                             mode === 'accounting' ? 'bg-white text-violet-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                Accounting Invoice
            </button>
            <button type="button" @click="mode = 'voucher'"
                    :class="['px-3 py-1 rounded-md font-medium transition',
                             mode === 'voucher' ? 'bg-white text-violet-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                As Voucher
            </button>
        </div>
    </div>

    <!-- ── PARTY & SUPPLY ────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Party A/c Name <span class="text-red-500">*</span>
                <span class="text-xs font-normal text-gray-400 ml-1">(Sundry Creditors / Cash / Bank)</span>
            </label>
            <input v-model="form.party_name" type="text" list="purchase-party-list"
                   placeholder="Select supplier ledger…"
                   @change="onPartyChange(form)"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            <datalist id="purchase-party-list">
                <option v-for="l in partyLedgers" :key="l.id" :value="l.ledger_name" />
            </datalist>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Place of Supply</label>
            <select v-model="form.place_of_supply"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">— State —</option>
                <option v-for="s in INDIAN_STATES" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>
    </div>

    <!-- ── ITEM GRID (Item Invoice & As Voucher) ─────────────────────────────── -->
    <div v-if="mode !== 'accounting'" class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 grid grid-cols-[2.5fr_0.7fr_0.7fr_0.9fr_0.7fr_0.9fr_auto] gap-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">
            <span>Name of Item</span>
            <span>Qty</span>
            <span>Per</span>
            <span>Rate</span>
            <span>Disc%</span>
            <span class="text-right">Amount</span>
            <span class="w-10" />
        </div>

        <div v-for="(ie, i) in form.inventory_entries" :key="i" class="border-b border-gray-100 last:border-0">
            <!-- Main row -->
            <div class="grid grid-cols-[2.5fr_0.7fr_0.7fr_0.9fr_0.7fr_0.9fr_auto] gap-2 items-center px-4 py-2">
                <select v-model="ie.stock_item_name" @change="onStockItemChange(ie)"
                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                    <option value="">— Select Item —</option>
                    <option v-for="s in stockItems" :key="s.id" :value="s.name">{{ s.name }}</option>
                </select>
                <input v-model="ie.billed_qty" type="number" step="0.0001" placeholder="0"
                       @input="recalcItemAmount(ie)"
                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.unit" type="text" placeholder="Nos"
                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.rate" type="number" step="0.01" placeholder="0.00"
                       @input="recalcItemAmount(ie)"
                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.discount_percent" type="number" step="0.01" placeholder="0"
                       @input="recalcItemAmount(ie)"
                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <input v-model="ie.amount" type="number" step="0.01" placeholder="0.00"
                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-violet-500" />
                <div class="flex items-center gap-1">
                    <button type="button" @click="toggleExpand(i)"
                            :class="['text-gray-400 hover:text-violet-600 text-xs transition-transform leading-none',
                                     expandedItems.has(i) ? 'rotate-180' : '']">▼</button>
                    <button type="button" @click="removeInventory(i)"
                            class="text-red-400 hover:text-red-600 text-sm leading-none">✕</button>
                </div>
            </div>

            <!-- Expanded details -->
            <div v-if="expandedItems.has(i)" class="border-t border-gray-100 bg-gray-50 px-4 pt-3 pb-3 space-y-3">
                <div class="grid grid-cols-4 gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Actual Qty</label>
                        <input v-model="ie.actual_qty" type="number" step="0.0001"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">HSN Code</label>
                        <input v-model="ie.hsn_code" type="text"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">IGST Rate %</label>
                        <input v-model="ie.igst_rate" type="number" step="0.01"
                               @input="recalcItemAmount(ie)"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">CESS Rate %</label>
                        <input v-model="ie.cess_rate" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Godown</label>
                        <select v-model="ie.godown_name"
                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option value="">— Select —</option>
                            <option v-for="g in godowns" :key="g.id" :value="g.name">{{ g.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Batch / Lot No</label>
                        <input v-model="ie.batch_name" type="text"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">MRP</label>
                        <input v-model="ie.mrp" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Tax Amount</label>
                        <input v-model="ie.tax_amount" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">Item Code</label>
                    <input v-model="ie.item_code" type="text" placeholder="SKU / barcode"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                </div>

                <!-- Batch Allocations -->
                <details class="border border-gray-200 rounded-lg overflow-hidden">
                    <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 bg-white flex items-center justify-between select-none">
                        Batch Allocations
                        <button type="button" @click.stop="addBatchAlloc(i)" class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                    </summary>
                    <div class="px-3 pb-2 pt-1 space-y-1.5">
                        <div v-for="(ba, j) in ie.batch_allocations" :key="j"
                             class="grid grid-cols-4 gap-1.5 items-end">
                            <div><label class="block text-xs text-gray-400 mb-0.5">Batch</label>
                                <input v-model="ba.BatchName" type="text" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Expiry Date</label>
                                <input v-model="ba.ExpiryDate" type="date" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Godown</label>
                                <select v-model="ba.GodownName" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="">— Select —</option>
                                    <option v-for="g in godowns" :key="g.id" :value="g.name">{{ g.name }}</option>
                                </select></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Actual Qty</label>
                                <input v-model="ba.ActualQty" type="number" step="0.0001" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Billed Qty</label>
                                <input v-model="ba.BilledQty" type="number" step="0.0001" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Rate</label>
                                <input v-model="ba.Rate" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Disc %</label>
                                <input v-model="ba.DiscountPercent" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div class="flex gap-1">
                                <div class="flex-1"><label class="block text-xs text-gray-400 mb-0.5">Amount</label>
                                    <input v-model="ba.Amount" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                                <button type="button" @click="removeBatchAlloc(i,j)" class="text-red-400 hover:text-red-600 text-xs self-end pb-1">✕</button>
                            </div>
                        </div>
                        <p v-if="!ie.batch_allocations.length" class="text-xs text-gray-400 italic">No batch allocations. Add only if batch-tracked.</p>
                    </div>
                </details>

                <!-- Accounting Allocations (advanced / auto-managed) -->
                <details class="border border-gray-200 rounded-lg overflow-hidden">
                    <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-500 bg-white flex items-center justify-between select-none">
                        <span>Accounting Allocations <span class="font-normal text-gray-400">(auto-managed from Purchase Ledger)</span></span>
                        <button type="button" @click.stop="addAccAlloc(i)" class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                    </summary>
                    <div class="px-3 pb-2 pt-1 space-y-1.5">
                        <p class="text-xs text-gray-400 italic mb-2">Auto-filled when you select a Purchase Ledger below. Edit only for advanced corrections.</p>
                        <div v-for="(aa, j) in ie.accounting_allocations" :key="j"
                             class="grid grid-cols-5 gap-1.5 items-end">
                            <div><label class="block text-xs text-gray-400 mb-0.5">Ledger</label>
                                <input v-model="aa.LedgerName" type="text" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Group</label>
                                <input v-model="aa.LedgerGroup" type="text" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">GST Class</label>
                                <select v-model="aa.GSTClassification" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="">— Select —</option>
                                    <option v-for="c in ['Not Applicable','Taxable','Nil Rated','Exempt','Non-GST Supply']" :key="c" :value="c">{{ c }}</option>
                                </select></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">IGST Rate</label>
                                <input v-model="aa.IGSTRate" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div class="flex gap-1">
                                <div class="flex-1"><label class="block text-xs text-gray-400 mb-0.5">Amount</label>
                                    <input v-model="aa.Amount" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                                <button type="button" @click="removeAccAlloc(i,j)" class="text-red-400 hover:text-red-600 text-xs self-end pb-1">✕</button>
                            </div>
                        </div>
                        <p v-if="!ie.accounting_allocations.length" class="text-xs text-gray-400 italic">Select a Purchase Ledger below to auto-fill.</p>
                    </div>
                </details>
            </div>
        </div>

        <!-- Add item + subtotal -->
        <div class="px-4 py-2 bg-white flex items-center justify-between">
            <button type="button" @click="addInventory"
                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Item</button>
            <div class="text-xs text-gray-500">
                Sub-total: <span class="font-mono font-semibold text-gray-800 ml-1">{{ fmt(taxableTotal) }}</span>
            </div>
        </div>

        <!-- ── PURCHASE LEDGER (common for all items) ────────────────────── -->
        <div v-if="form.inventory_entries.length" class="border-t border-violet-100 bg-violet-50 px-4 py-3 flex items-center gap-3">
            <label class="text-sm font-medium text-violet-800 whitespace-nowrap">Purchase Ledger</label>
            <input v-model="commonSalesLedger" type="text" list="purchase-ledger-list"
                   placeholder="Select purchase / expense ledger…"
                   @change="applyCommonSalesLedger"
                   class="flex-1 rounded-lg border border-violet-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            <datalist id="purchase-ledger-list">
                <option v-for="l in purchaseLedgers" :key="l.id" :value="l.ledger_name" />
            </datalist>
            <span class="text-xs text-violet-500 whitespace-nowrap">
                Applies to all items · <span class="font-medium">Purchase Accounts</span> group
            </span>
        </div>
    </div>

    <!-- ── LEDGER ALLOCATIONS ────────────────────────────────────────────────── -->
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2.5 flex items-center justify-between">
            <div>
                <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    {{ MODE_LABELS[mode] }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ MODE_HINT[mode] }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button v-if="mode === 'item' && autoTaxGroups.length" type="button"
                        @click="suggestTaxLines"
                        class="text-xs text-violet-600 hover:text-violet-800 font-medium border border-violet-200 rounded px-2 py-0.5">
                    Suggest Tax Lines ({{ autoTaxGroups.map(g => g.label).join(', ') }})
                </button>
                <button type="button" @click="addLedger"
                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Line</button>
            </div>
        </div>

        <div v-if="form.ledger_entries.length">
            <div class="grid grid-cols-[2fr_1fr_1.1fr_0.5fr_0.5fr_0.5fr_1fr_auto] gap-2 px-4 py-1.5 bg-gray-50 border-b border-gray-100 text-xs font-medium text-gray-400">
                <span>Ledger Name</span>
                <span>Amount</span>
                <span>Dr / Cr &amp; Party</span>
                <span>IGST%</span>
                <span>HSN</span>
                <span>Cess%</span>
                <span>Bill Ref</span>
                <span class="w-6" />
            </div>

            <div v-for="(le, i) in form.ledger_entries" :key="i"
                 class="border-b border-gray-50 last:border-0 px-4 py-2 space-y-2">
                <div class="grid grid-cols-[2fr_1fr_1.1fr_0.5fr_0.5fr_0.5fr_1fr_auto] gap-2 items-center">
                    <div>
                        <select v-model="le.ledger_name" @change="onLedgerChange(le, form)"
                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option value="">{{ le._suggestLabel ? `— ${le._suggestLabel} Ledger —` : '— Select Ledger —' }}</option>
                            <option v-for="l in ledgerEntryOptions" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                        </select>
                        <p v-if="le.ledger_group" class="mt-0.5 px-2 text-xs text-gray-400">{{ le.ledger_group }}</p>
                    </div>
                    <input v-model="le.ledger_amount" type="number" step="0.01" placeholder="0.00"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <div class="flex items-center gap-1.5">
                        <select v-model="le.is_deemed_positive"
                                class="flex-1 rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option :value="true">Dr (Debit)</option>
                            <option :value="false">Cr (Credit)</option>
                        </select>
                        <label class="flex items-center gap-1 text-xs text-gray-500 whitespace-nowrap cursor-pointer" title="Is Party Ledger — mark for the supplier account">
                            <input v-model="le.is_party_ledger" type="checkbox"
                                   class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                            Party
                        </label>
                    </div>
                    <input v-model="le.igst_rate" type="text" placeholder="—"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <input v-model="le.hsn_code" type="text" placeholder="—"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <input v-model="le.cess_rate" type="text" placeholder="—"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <div>
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-gray-400">{{ le.bills_allocation.length || '' }} ref(s)</span>
                            <button type="button" @click="addBillRef(i)" class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                        </div>
                        <div v-for="(br, j) in le.bills_allocation" :key="j"
                             class="grid grid-cols-[0.8fr_1fr_0.7fr_0.7fr_auto] gap-1 mb-1 items-end">
                            <select v-model="br.AgstType" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                <option>New Ref</option><option>Agst Ref</option><option>On Account</option><option>Advance</option>
                            </select>
                            <input v-model="br.Reference" type="text" placeholder="Ref No"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            <input v-model="br.CreditPeriod" type="text" placeholder="30 Days"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            <input v-model="br.Amount" type="number" step="0.01"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            <button type="button" @click="removeBillRef(i,j)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </div>
                    </div>
                    <button type="button" @click="removeLedger(i)" class="text-red-400 hover:text-red-600 text-sm leading-none">✕</button>
                </div>
            </div>
        </div>
        <p v-else class="px-4 py-3 text-xs text-gray-400 italic">
            No ledger lines yet.
            <template v-if="mode === 'item'">Add the supplier ledger (Cr + Party) and tax/input ledgers (Dr).</template>
            <template v-else-if="mode === 'accounting'">Add the supplier ledger (Cr + Party) and purchase/expense ledger (Dr).</template>
            <template v-else>Add Dr and Cr entries for this transaction.</template>
            <span v-if="mode === 'item' && autoTaxGroups.length"> Click "Suggest Tax Lines" to auto-add GST entries.</span>
        </p>
    </div>

    <!-- ── INVOICE SUMMARY ────────────────────────────────────────────────────── -->
    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="text-gray-600">Taxable Value</span>
            <span class="font-mono font-medium text-gray-800">{{ fmt(taxableTotal) }}</span>
        </div>
        <div class="flex items-center justify-between text-sm mb-3">
            <span class="text-gray-600">Tax &amp; Duties</span>
            <span class="font-mono font-medium text-gray-800">{{ fmt(ledgerTotal) }}</span>
        </div>
        <div class="flex items-center justify-between pt-2 border-t border-violet-200">
            <span class="text-sm font-semibold text-violet-900">Grand Total</span>
            <div class="flex items-center gap-2">
                <input v-model="form.voucher_total" @input="grandTotalLocked = true"
                       type="number" step="0.01"
                       class="w-36 text-right rounded border border-violet-300 px-2 py-1 text-sm font-mono font-semibold focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white" />
                <button v-if="grandTotalLocked" type="button" @click="resetGrandTotal"
                        title="Reset to computed value" class="text-xs text-violet-600 hover:text-violet-800">↺</button>
            </div>
        </div>
    </div>

    <!-- ── NARRATION ──────────────────────────────────────────────────────────── -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="Notes / description…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
    </div>

    <!-- ── PARTY DETAILS ──────────────────────────────────────────────────────── -->
    <details class="border border-gray-200 rounded-xl overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50 flex items-center justify-between">
            <span>Party Details</span>
            <span class="text-xs text-gray-400 font-normal">Auto-filled from Party A/c Name · edit if different on bill</span>
        </summary>
        <div class="px-4 pb-4 pt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Party's Name</label>
                    <input v-model="form.buyer_name" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN/UIN</label>
                    <input v-model="form.buyer_gstin" type="text" placeholder="15-char GSTIN"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <button type="button" @click="addAddressLine" class="text-xs text-violet-600 hover:text-violet-800">+ Add Line</button>
                </div>
                <div v-for="(line, idx) in buyerAddressLines" :key="idx" class="flex gap-2 mb-1.5">
                    <input v-model="buyerAddressLines[idx]" type="text" :placeholder="`Address line ${idx + 1}`"
                           class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    <button v-if="buyerAddressLines.length > 1" type="button" @click="removeAddressLine(idx)"
                            class="text-red-400 hover:text-red-600 text-sm px-2">✕</button>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                    <select v-model="form.buyer_state"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select —</option>
                        <option v-for="s in INDIAN_STATES" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pin Code</label>
                    <input v-model="form.buyer_pin_code" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Registration Type</label>
                    <select v-model="form.buyer_gst_registration_type"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select —</option>
                        <option v-for="t in GST_REG_TYPES" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
            </div>
        </div>
    </details>

    <!-- ── DISPATCH DETAILS ───────────────────────────────────────────────────── -->
    <details class="border border-gray-200 rounded-xl overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50 flex items-center justify-between">
            <span>Dispatch Details</span>
            <span class="text-xs text-gray-400 font-normal">Delivery note, transport &amp; carrier info</span>
        </summary>
        <div class="px-4 pb-4 pt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Note No.</label>
                    <input v-model="form.delivery_note_no" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Note Date</label>
                    <input v-model="form.delivery_note_date" type="date"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Despatch Doc No.</label>
                    <input v-model="form.dispatch_doc_no" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Despatched Through</label>
                    <input v-model="form.dispatch_through" type="text" placeholder="e.g. Road / Courier"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">LR-RR No.</label>
                    <input v-model="form.lr_no" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">LR-RR Date</label>
                    <input v-model="form.lr_date" type="date"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motor Vehicle No.</label>
                    <input v-model="form.motor_vehicle_no" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Carrier/Agent Name</label>
                    <input v-model="form.carrier_name" type="text" placeholder="e.g. Bluedart"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Destination</label>
                    <input v-model="form.destination" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
        </div>
    </details>

    <!-- ── ORDER DETAILS ──────────────────────────────────────────────────────── -->
    <details class="border border-gray-200 rounded-xl overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50 flex items-center justify-between">
            <span>Order Details</span>
            <span class="text-xs text-gray-400 font-normal">Purchase order, payment &amp; delivery terms</span>
        </summary>
        <div class="px-4 pb-4 pt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order No.</label>
                    <input v-model="form.order_no" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order Date</label>
                    <input v-model="form.order_date" type="date"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Terms of Payment</label>
                    <input v-model="form.terms_of_payment" type="text" placeholder="e.g. Net 30"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Terms of Delivery</label>
                    <input v-model="form.terms_of_delivery" type="text" placeholder="e.g. CIF, FOB"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Other Reference(s)</label>
                    <input v-model="form.other_references" type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cost Centre</label>
                    <input v-model="form.cost_centre" type="text" placeholder="e.g. Head Office"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
            </div>
        </div>
    </details>
</template>
