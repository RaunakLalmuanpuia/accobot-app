<script setup>
import { useInvoiceVoucherForm } from './useInvoiceVoucherForm.js'

const props = defineProps({
    form:       Object,
    ledgers:    Array,
    stockItems: Array,
    godowns:    Array,
    isEditing:  Boolean,
})

const {
    voucherNoLabel, voucherNoPlaceholder,
    mode, expandedItems, grandTotalLocked, buyerAddressLines,
    partyLedgers, taxableTotal, ledgerTotal, autoTaxGroups,
    toggleExpand, recalcItemAmount, onStockItemChange, addInventory, removeInventory,
    addBatchAlloc, removeBatchAlloc, addAccAlloc, removeAccAlloc,
    onLedgerChange, addLedger, removeLedger, addBillRef, removeBillRef,
    resetGrandTotal, suggestTaxLines,
    addAddressLine, removeAddressLine,
    INDIAN_STATES, GST_REG_TYPES, GST_CLASSIFICATIONS, fmt,
} = useInvoiceVoucherForm(props, {
    voucherNoLabel:       'Debit Note No',
    voucherNoPlaceholder: 'e.g. DN-001',
    partyType:            'creditor',
})
</script>

<template>
    <!-- ── HEADER BAR ─────────────────────────────────────────────────────── -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
        <div class="grid grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ voucherNoLabel }}</label>
                <input v-model="form.voucher_number" type="text" :placeholder="voucherNoPlaceholder"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Date <span class="text-red-500">*</span></label>
                <input v-model="form.voucher_date" type="date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                <p v-if="form.errors.voucher_date" class="mt-0.5 text-xs text-red-500">{{ form.errors.voucher_date }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Original Bill No</label>
                <input v-model="form.reference" type="text" placeholder="e.g. BILL-001"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Original Bill Date</label>
                <input v-model="form.reference_date" type="date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
        </div>

        <!-- Mode toggle -->
        <div class="flex items-center gap-4">
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
            </div>
            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-600">
                <input v-model="form.is_invoice" type="checkbox"
                       class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                Is Invoice
            </label>
        </div>
    </div>

    <!-- ── PARTY & SUPPLY ─────────────────────────────────────────────────── -->
    <div class="grid grid-cols-3 gap-3">
        <div class="col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Vendor A/c Name <span class="text-red-500">*</span>
            </label>
            <input v-model="form.party_name" type="text" list="dn-party-list"
                   placeholder="Select creditor ledger…"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            <datalist id="dn-party-list">
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
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cost Centre</label>
            <input v-model="form.cost_centre" type="text" placeholder="e.g. Head Office"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>

    <!-- ── ITEM GRID ──────────────────────────────────────────────────────── -->
    <div v-if="mode === 'item'" class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 grid grid-cols-[2.5fr_0.7fr_0.7fr_0.9fr_0.7fr_0.9fr_auto] gap-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">
            <span>Name of Item</span>
            <span>Qty</span>
            <span>per</span>
            <span>Rate</span>
            <span>Disc%</span>
            <span class="text-right">Amount</span>
            <span class="w-10" />
        </div>

        <div v-for="(ie, i) in form.inventory_entries" :key="i" class="border-b border-gray-100 last:border-0">
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

            <!-- Expanded row details -->
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
                        <label class="block text-xs text-gray-500 mb-0.5">Batch</label>
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
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Purchase Ledger</label>
                        <input v-model="ie.sales_ledger" type="text" list="dn-item-ledger-list"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">Item Code</label>
                        <input v-model="ie.item_code" type="text"
                               class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-600">
                            <input v-model="ie.is_deemed_positive" type="checkbox"
                                   class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                            Deemed Positive
                        </label>
                    </div>
                </div>

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
                            <div><label class="block text-xs text-gray-400 mb-0.5">Godown</label>
                                <select v-model="ba.GodownName" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="">— Select —</option>
                                    <option v-for="g in godowns" :key="g.id" :value="g.name">{{ g.name }}</option>
                                </select></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Billed Qty</label>
                                <input v-model="ba.BilledQty" type="number" step="0.0001" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div class="flex gap-1">
                                <div class="flex-1"><label class="block text-xs text-gray-400 mb-0.5">Amount</label>
                                    <input v-model="ba.Amount" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                                <button type="button" @click="removeBatchAlloc(i,j)" class="text-red-400 hover:text-red-600 text-xs self-end pb-1">✕</button>
                            </div>
                        </div>
                        <p v-if="!ie.batch_allocations.length" class="text-xs text-gray-400 italic">No batch allocations.</p>
                    </div>
                </details>

                <details class="border border-gray-200 rounded-lg overflow-hidden">
                    <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 bg-white flex items-center justify-between select-none">
                        Accounting Allocations
                        <button type="button" @click.stop="addAccAlloc(i)" class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                    </summary>
                    <div class="px-3 pb-2 pt-1 space-y-1.5">
                        <div v-for="(aa, j) in ie.accounting_allocations" :key="j"
                             class="grid grid-cols-5 gap-1.5 items-end">
                            <div><label class="block text-xs text-gray-400 mb-0.5">Ledger</label>
                                <input v-model="aa.LedgerName" type="text" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Group</label>
                                <input v-model="aa.LedgerGroup" type="text" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">GST Class</label>
                                <select v-model="aa.GSTClassification" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="">— Select —</option>
                                    <option v-for="c in GST_CLASSIFICATIONS" :key="c" :value="c">{{ c }}</option>
                                </select></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">IGST Rate</label>
                                <input v-model="aa.IGSTRate" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div class="flex gap-1">
                                <div class="flex-1"><label class="block text-xs text-gray-400 mb-0.5">Amount</label>
                                    <input v-model="aa.Amount" type="number" step="0.01" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                                <button type="button" @click="removeAccAlloc(i,j)" class="text-red-400 hover:text-red-600 text-xs self-end pb-1">✕</button>
                            </div>
                        </div>
                        <p v-if="!ie.accounting_allocations.length" class="text-xs text-gray-400 italic">No accounting allocations.</p>
                    </div>
                </details>
            </div>
        </div>

        <div class="px-4 py-2 bg-white flex items-center justify-between">
            <button type="button" @click="addInventory"
                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Item</button>
            <div class="text-xs text-gray-500">
                Sub-total: <span class="font-mono font-semibold text-gray-800 ml-1">{{ fmt(taxableTotal) }}</span>
            </div>
        </div>
    </div>

    <!-- ── LEDGER ALLOCATIONS ─────────────────────────────────────────────── -->
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 flex items-center justify-between">
            <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                {{ mode === 'item' ? 'Ledger Allocations (Tax & Charges)' : 'Accounting Lines' }}
            </h3>
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
            <div class="grid grid-cols-[2fr_1fr_0.7fr_1fr_auto] gap-2 px-4 py-1.5 bg-gray-50 border-b border-gray-100 text-xs font-medium text-gray-400">
                <span>Ledger Name</span>
                <span>Amount</span>
                <span>Dr/Cr</span>
                <span>Bill Ref</span>
                <span class="w-6" />
            </div>

            <div v-for="(le, i) in form.ledger_entries" :key="i"
                 class="border-b border-gray-50 last:border-0 px-4 py-2 space-y-2">
                <div class="grid grid-cols-[2fr_1fr_0.7fr_1fr_auto] gap-2 items-center">
                    <div>
                        <select v-model="le.ledger_name" @change="onLedgerChange(le)"
                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option value="">{{ le._suggestLabel ? `— ${le._suggestLabel} Ledger —` : '— Select Ledger —' }}</option>
                            <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                        </select>
                        <input v-if="le.ledger_group" v-model="le.ledger_group" type="text" readonly
                               class="mt-0.5 w-full rounded border-0 px-2 py-0.5 text-xs text-gray-400 bg-transparent" />
                    </div>
                    <input v-model="le.ledger_amount" type="number" step="0.01" placeholder="0.00"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <select v-model="le.is_deemed_positive"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <option :value="true">Dr</option>
                        <option :value="false">Cr</option>
                    </select>
                    <div>
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-gray-400">{{ le.bills_allocation.length }} ref(s)</span>
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
            <span v-if="mode === 'item' && autoTaxGroups.length">Click "Suggest Tax Lines" to auto-add GST entries.</span>
        </p>
    </div>

    <!-- ── INVOICE SUMMARY ────────────────────────────────────────────────── -->
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

    <!-- ── NARRATION ──────────────────────────────────────────────────────── -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="Notes / description…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
    </div>

    <!-- ── COLLAPSIBLE SECTIONS ───────────────────────────────────────────── -->
    <details class="border border-gray-200 rounded-xl overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50 flex items-center justify-between">
            <span>Vendor Details</span><span class="text-xs text-gray-400 font-normal">Name, GSTIN, Address</span>
        </summary>
        <div class="px-4 pb-4 pt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Vendor Name</label>
                    <input v-model="form.buyer_name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Vendor GSTIN</label>
                    <input v-model="form.buyer_gstin" type="text" placeholder="15-char GSTIN" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" /></div>
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
                <div><label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                    <select v-model="form.buyer_state" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select —</option>
                        <option v-for="s in INDIAN_STATES" :key="s" :value="s">{{ s }}</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Pin Code</label>
                    <input v-model="form.buyer_pin_code" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">GST Reg Type</label>
                    <select v-model="form.buyer_gst_registration_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select —</option>
                        <option v-for="t in GST_REG_TYPES" :key="t" :value="t">{{ t }}</option>
                    </select></div>
            </div>
        </div>
    </details>

    <details class="border border-gray-200 rounded-xl overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50 flex items-center justify-between">
            <span>Order Details</span><span class="text-xs text-gray-400 font-normal">Original bill reference</span>
        </summary>
        <div class="px-4 pb-4 pt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Order No</label>
                    <input v-model="form.order_no" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Order Date</label>
                    <input v-model="form.order_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" /></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Other References</label>
                <input v-model="form.other_references" type="text"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" /></div>
        </div>
    </details>

    <datalist id="dn-item-ledger-list">
        <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name" />
    </datalist>
</template>
