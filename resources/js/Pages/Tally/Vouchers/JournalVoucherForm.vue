<script setup>
import { computed, ref, watch } from 'vue'
import { fmt, emptyBillRef } from './voucherHelpers.js'
import VoucherGuide from './VoucherGuide.vue'

const props = defineProps({
    form:      Object,
    ledgers:   Array,
    isEditing: Boolean,
})

const ledgerMap = computed(() => {
    const m = {}
    props.ledgers.forEach(l => { m[l.ledger_name] = l })
    return m
})

function onLedgerChange(le) {
    const m = ledgerMap.value[le.ledger_name]
    if (m?.group_name) le.ledger_group = m.group_name
}

function addEntry() {
    props.form.ledger_entries.push({
        ledger_name: '', ledger_group: '', ledger_amount: '',
        is_deemed_positive: true, is_party_ledger: false,
        igst_rate: '', hsn_code: '', cess_rate: '',
        bills_allocation: [], bank_allocation_details: [],
    })
}

function removeEntry(i) { props.form.ledger_entries.splice(i, 1) }

function addBillRef(i)       { props.form.ledger_entries[i].bills_allocation.push(emptyBillRef()) }
function removeBillRef(i, j) { props.form.ledger_entries[i].bills_allocation.splice(j, 1) }

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
const diff       = computed(() => Math.abs(drTotal.value - crTotal.value))

watch(drTotal, (v) => { props.form.voucher_total = parseFloat(v.toFixed(2)) || '' })
watch(() => props.form.ledger_entries[0]?.ledger_name, (name) => {
    props.form.party_name = name || ''
}, { immediate: true })

const showGuide = ref(false)
</script>

<template>
    <VoucherGuide :show="showGuide" voucher-type="Journal" @close="showGuide = false" />

    <!-- ── HEADER BAR ─────────────────────────────────────────────────────── -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
        <div class="flex items-start justify-between gap-3">
        <div class="grid grid-cols-4 gap-3 flex-1">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Journal No</label>
                <input v-model="form.voucher_number" type="text" placeholder="e.g. JNL-001"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Date <span class="text-red-500">*</span></label>
                <input v-model="form.voucher_date" type="date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                <p v-if="form.errors.voucher_date" class="mt-0.5 text-xs text-red-500">{{ form.errors.voucher_date }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Reference</label>
                <input v-model="form.reference" type="text" placeholder="e.g. TDS-Q1"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Ref Date</label>
                <input v-model="form.reference_date" type="date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
        </div>
        <button type="button" @click="showGuide = true"
                class="flex items-center gap-1.5 text-xs text-violet-600 hover:text-violet-800 border border-violet-200 hover:border-violet-300 rounded-lg px-3 py-1.5 bg-white transition whitespace-nowrap mt-0.5">
            <span>?</span> Guide
        </button>
        </div>
    </div>

    <!-- ── PARTICULARS TABLE ──────────────────────────────────────────────── -->
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2.5 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Particulars</span>
            <button type="button" @click="addEntry"
                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Line</button>
        </div>

        <!-- Column headers -->
        <div class="grid grid-cols-[2.5fr_1fr_1fr_1.5fr_auto] gap-2 px-4 py-1.5 bg-gray-50 border-b border-gray-100 text-xs font-medium text-gray-400">
            <span>Ledger Name</span>
            <span>Dr Amount</span>
            <span>Cr Amount</span>
            <span>Bill Ref</span>
            <span class="w-6" />
        </div>

        <div v-for="(le, i) in form.ledger_entries" :key="i"
             class="border-b border-gray-50 last:border-0 px-4 py-2.5 space-y-1.5">
            <div class="grid grid-cols-[2.5fr_1fr_1fr_1.5fr_auto] gap-2 items-start">
                <div>
                    <select v-model="le.ledger_name" @change="onLedgerChange(le)"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <option value="">— Select Ledger —</option>
                        <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                    </select>
                    <p v-if="le.ledger_group" class="text-xs text-gray-400 mt-0.5 ml-1">{{ le.ledger_group }}</p>
                </div>

                <!-- Dr Amount -->
                <div>
                    <input v-if="le.is_deemed_positive"
                           v-model="le.ledger_amount" type="number" step="0.01" placeholder="0.00"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <button v-else type="button" @click="le.is_deemed_positive = true"
                            class="w-full rounded border border-dashed border-gray-200 px-2 py-1.5 text-xs text-gray-300 hover:text-violet-600 hover:border-violet-300 transition">
                        + Dr
                    </button>
                </div>

                <!-- Cr Amount -->
                <div>
                    <input v-if="!le.is_deemed_positive"
                           v-model="le.ledger_amount" type="number" step="0.01" placeholder="0.00"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <button v-else type="button" @click="le.is_deemed_positive = false"
                            class="w-full rounded border border-dashed border-gray-200 px-2 py-1.5 text-xs text-gray-300 hover:text-violet-600 hover:border-violet-300 transition">
                        + Cr
                    </button>
                </div>

                <!-- Bill Refs -->
                <div>
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="text-xs text-gray-400">{{ le.bills_allocation.length }} ref(s)</span>
                        <button type="button" @click="addBillRef(i)"
                                class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                    </div>
                    <div v-for="(br, j) in le.bills_allocation" :key="j"
                         class="grid grid-cols-[0.7fr_1fr_0.6fr_auto] gap-1 mb-1 items-center">
                        <select v-model="br.AgstType" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option>New Ref</option><option>Agst Ref</option><option>On Account</option>
                        </select>
                        <input v-model="br.Reference" type="text" placeholder="Ref No"
                               class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        <input v-model="br.Amount" type="number" step="0.01"
                               class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        <button type="button" @click="removeBillRef(i, j)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                    </div>
                </div>

                <button type="button" @click="removeEntry(i)"
                        class="text-red-400 hover:text-red-600 text-sm leading-none mt-1">✕</button>
            </div>
        </div>

        <p v-if="!form.ledger_entries.length"
           class="px-4 py-4 text-xs text-gray-400 text-center">No entries. Click + Add Line.</p>
    </div>

    <!-- ── BALANCE CHECK ──────────────────────────────────────────────────── -->
    <div :class="['rounded-xl p-4 border', isBalanced ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200']">
        <div class="flex items-center justify-between mb-3">
            <span :class="['text-sm font-semibold', isBalanced ? 'text-green-800' : 'text-amber-800']">
                {{ isBalanced ? '✓ Balanced' : `⚠ Difference: ${fmt(diff)}` }}
            </span>
            <div class="text-xs text-gray-500">
                Grand Total:
                <input v-model="form.voucher_total" type="number" step="0.01" placeholder="0.00"
                       class="ml-1 w-32 text-right rounded border border-gray-300 px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-violet-500 bg-white" />
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Total Dr</span>
                <span class="font-mono font-semibold text-gray-900">{{ fmt(drTotal) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Total Cr</span>
                <span class="font-mono font-semibold text-gray-900">{{ fmt(crTotal) }}</span>
            </div>
        </div>
    </div>

    <!-- ── NARRATION ──────────────────────────────────────────────────────── -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="Journal description…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
    </div>
</template>
