<script setup>
import { computed, ref } from 'vue'
import { emptyBankAlloc, TRANSFER_MODES } from './voucherHelpers.js'
import VoucherGuide from './VoucherGuide.vue'

const props = defineProps({
    form:      Object,
    ledgers:   Array,
    isEditing: Boolean,
})

// First non-party entry = "To" account (destination — where money goes)
const toAccount = computed(() => props.form.ledger_entries.find(le => !le.is_party_ledger) ?? null)
// Party entries = "From" accounts (sources)
const fromAccounts = computed(() => props.form.ledger_entries.filter(le => le.is_party_ledger))

function ensureToAccount() {
    if (!toAccount.value) {
        props.form.ledger_entries.unshift({
            ledger_name: '', ledger_group: '', ledger_amount: '',
            is_deemed_positive: true, is_party_ledger: false,
            igst_rate: '', hsn_code: '', cess_rate: '',
            bills_allocation: [], bank_allocation_details: [],
        })
    }
}

function onToLedgerChange() {
    const m = props.ledgers.find(l => l.ledger_name === toAccount.value?.ledger_name)
    if (m?.group_name && toAccount.value) toAccount.value.ledger_group = m.group_name
}

function addFrom() {
    props.form.ledger_entries.push({
        ledger_name: '', ledger_group: '', ledger_amount: '',
        is_deemed_positive: false, is_party_ledger: true,
        igst_rate: '', hsn_code: '', cess_rate: '',
        bills_allocation: [], bank_allocation_details: [],
    })
}

function removeFrom(le) {
    const idx = props.form.ledger_entries.indexOf(le)
    if (idx !== -1) props.form.ledger_entries.splice(idx, 1)
}

function onFromLedgerChange(le) {
    const m = props.ledgers.find(l => l.ledger_name === le.ledger_name)
    if (m?.group_name) le.ledger_group = m.group_name
}

function addBankAlloc(le)       { le.bank_allocation_details.push(emptyBankAlloc()) }
function removeBankAlloc(le, j) { le.bank_allocation_details.splice(j, 1) }

function syncTotal() {
    const total = fromAccounts.value.reduce((s, le) => s + (parseFloat(le.ledger_amount) || 0), 0)
    if (toAccount.value) toAccount.value.ledger_amount = total || ''
    props.form.voucher_total = total || ''
}

if (!toAccount.value) ensureToAccount()

const showGuide = ref(false)
</script>

<template>
    <VoucherGuide :show="showGuide" voucher-type="Contra" @close="showGuide = false" />

    <!-- ── HEADER BAR ─────────────────────────────────────────────────────── -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
        <div class="flex items-start justify-between gap-3">
        <div class="grid grid-cols-2 gap-3 flex-1">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Contra No</label>
                <input v-model="form.voucher_number" type="text" placeholder="e.g. CTR-001"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Date <span class="text-red-500">*</span></label>
                <input v-model="form.voucher_date" type="date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                <p v-if="form.errors.voucher_date" class="mt-0.5 text-xs text-red-500">{{ form.errors.voucher_date }}</p>
            </div>
        </div>
        <button type="button" @click="showGuide = true"
                class="flex items-center gap-1.5 text-xs text-violet-600 hover:text-violet-800 border border-violet-200 hover:border-violet-300 rounded-lg px-3 py-1.5 bg-white transition whitespace-nowrap mt-0.5">
            <span>?</span> Guide
        </button>
        </div>
    </div>

    <!-- ── TO ACCOUNT ─────────────────────────────────────────────────────── -->
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2.5">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Account (To — Deposit Into)</span>
        </div>
        <div v-if="toAccount" class="px-4 py-4 space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank / Cash Account</label>
                    <select v-model="toAccount.ledger_name" @change="onToLedgerChange"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select Account —</option>
                        <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                    </select>
                    <p v-if="toAccount.ledger_group" class="text-xs text-gray-400 mt-0.5 ml-1">{{ toAccount.ledger_group }}</p>
                </div>
                <div class="w-44">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <div class="flex gap-1">
                        <input v-model="toAccount.ledger_amount" type="number" step="0.01" placeholder="0.00"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-right font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <button type="button" @click="syncTotal" title="Sync from below"
                                class="text-xs text-violet-600 hover:text-violet-800 border border-violet-200 rounded px-2">↺</button>
                    </div>
                </div>
            </div>

            <details class="border border-gray-200 rounded-lg overflow-hidden">
                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 bg-white flex items-center justify-between select-none">
                    Bank Transaction Details
                    <button type="button" @click.stop="addBankAlloc(toAccount)"
                            class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                </summary>
                <div class="px-3 pb-3 pt-2 space-y-2">
                    <div v-for="(ba, j) in toAccount.bank_allocation_details" :key="j"
                         class="border border-gray-100 rounded-lg p-2 space-y-2">
                        <div class="grid grid-cols-3 gap-2">
                            <div><label class="block text-xs text-gray-400 mb-0.5">Mode</label>
                                <select v-model="ba.TRANSFERMODE" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="">— Select —</option>
                                    <option v-for="m in TRANSFER_MODES" :key="m" :value="m">{{ m }}</option>
                                </select></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">UTR / Ref</label>
                                <input v-model="ba.INSTRUMENTNUMBER" type="text" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Date</label>
                                <input v-model="ba.Date" type="date" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="removeBankAlloc(toAccount, j)" class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                        </div>
                    </div>
                    <p v-if="!toAccount.bank_allocation_details.length" class="text-xs text-gray-400 italic">No bank details.</p>
                </div>
            </details>
        </div>
    </div>

    <!-- ── FROM ACCOUNTS ──────────────────────────────────────────────────── -->
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2.5 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Particulars (From — Withdraw From)</span>
            <button type="button" @click="addFrom"
                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
        </div>

        <div v-for="(le, i) in fromAccounts" :key="i"
             class="border-b border-gray-50 last:border-0 px-4 py-3 space-y-2">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank / Cash Account</label>
                    <select v-model="le.ledger_name" @change="onFromLedgerChange(le)"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select Account —</option>
                        <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                    </select>
                    <p v-if="le.ledger_group" class="text-xs text-gray-400 mt-0.5 ml-1">{{ le.ledger_group }}</p>
                </div>
                <div class="w-36">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input v-model="le.ledger_amount" type="number" step="0.01" placeholder="0.00"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-right font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <button type="button" @click="removeFrom(le)"
                        class="mb-0.5 text-red-400 hover:text-red-600 text-sm">✕</button>
            </div>

            <details class="border border-gray-200 rounded-lg overflow-hidden">
                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 bg-white flex items-center justify-between select-none">
                    Bank Transaction Details
                    <button type="button" @click.stop="addBankAlloc(le)"
                            class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                </summary>
                <div class="px-3 pb-3 pt-2 space-y-2">
                    <div v-for="(ba, j) in le.bank_allocation_details" :key="j"
                         class="border border-gray-100 rounded-lg p-2 space-y-2">
                        <div class="grid grid-cols-3 gap-2">
                            <div><label class="block text-xs text-gray-400 mb-0.5">Mode</label>
                                <select v-model="ba.TRANSFERMODE" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="">— Select —</option>
                                    <option v-for="m in TRANSFER_MODES" :key="m" :value="m">{{ m }}</option>
                                </select></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">UTR / Ref</label>
                                <input v-model="ba.INSTRUMENTNUMBER" type="text" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                            <div><label class="block text-xs text-gray-400 mb-0.5">Date</label>
                                <input v-model="ba.Date" type="date" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" /></div>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="removeBankAlloc(le, j)" class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                        </div>
                    </div>
                    <p v-if="!le.bank_allocation_details.length" class="text-xs text-gray-400 italic">No bank details.</p>
                </div>
            </details>
        </div>
        <p v-if="!fromAccounts.length" class="px-4 py-4 text-xs text-gray-400 text-center">No source accounts. Click + Add.</p>
    </div>

    <!-- ── NARRATION ──────────────────────────────────────────────────────── -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="e.g. Cash deposited to HDFC Bank…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
    </div>
</template>
