<script setup>
import { computed } from 'vue'

const props = defineProps({
    form:     Object,
    ledgers:  Array,
    isEditing: Boolean,
})

const ledgerMap = computed(() => {
    const m = {}
    props.ledgers.forEach(l => { m[l.ledger_name] = l })
    return m
})

const drTotal = computed(() =>
    props.form.ledger_entries
        .filter(le => le.is_deemed_positive)
        .reduce((s, le) => s + (parseFloat(le.ledger_amount) || 0), 0)
)

const crTotal = computed(() =>
    props.form.ledger_entries
        .filter(le => !le.is_deemed_positive)
        .reduce((s, le) => s + (parseFloat(le.ledger_amount) || 0), 0)
)

const isBalanced = computed(() => Math.abs(drTotal.value - crTotal.value) < 0.01)

function onLedgerChange(le) {
    const m = ledgerMap.value[le.ledger_name]
    if (m?.group_name) le.ledger_group = m.group_name
}

function emptyLedger() {
    return {
        ledger_name: '', ledger_group: '', ledger_amount: '',
        is_deemed_positive: true, is_party_ledger: false,
        igst_rate: '', hsn_code: '', cess_rate: '',
        bills_allocation: [],
        bank_allocation_details: [],
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

function fmt(v) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(v || 0)
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
            <input v-model="form.voucher_date" type="date"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            <p v-if="form.errors.voucher_date" class="mt-1 text-xs text-red-500">{{ form.errors.voucher_date }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Journal No</label>
            <input v-model="form.voucher_number" type="text" placeholder="e.g. JNL-001"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
            <input v-model="form.reference" type="text" placeholder="e.g. TDS-Q1-2025"
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Place of Supply</label>
            <select v-model="form.place_of_supply"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">— Select State —</option>
                <option v-for="s in INDIAN_STATES" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cost Centre</label>
            <input v-model="form.cost_centre" type="text" placeholder="e.g. Head Office"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="Journal description…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
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
                    <input v-model="le.ledger_amount" type="number" step="0.01" placeholder="0.00"
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

    <div :class="['rounded-lg p-4 border', isBalanced ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200']">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="font-semibold" :class="isBalanced ? 'text-green-800' : 'text-amber-800'">
                {{ isBalanced ? 'Balanced' : 'Not Balanced' }}
            </span>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Total Dr</span>
                <span class="font-mono font-medium text-gray-900">{{ fmt(drTotal) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Total Cr</span>
                <span class="font-mono font-medium text-gray-900">{{ fmt(crTotal) }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Grand Total</label>
            <input v-model="form.voucher_total" type="number" step="0.01" placeholder="0.00"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>
</template>
