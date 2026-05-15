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

const BANK_TRANSACTION_TYPES = [
    'Same Bank Transfer', 'Inter Bank Transfer', 'Cash', 'Cheque', 'DD',
    'Electronic Cheque', 'Electronic DD/PO', 'E-Payments',
]
const TRANSFER_MODES = ['NEFT', 'RTGS', 'IMPS', 'UPI', 'Cheque', 'DD', 'Cash']

function emptyBankAlloc() {
    return {
        TRANSACTIONTYPE: '', BANKNAME: '', AMOUNT: '',
        Date: '', InstrumentDate: '', IFSCODE: '',
        ACCOUNTNUMBER: '', PAYMENTFAVOURING: '', TRANSFERMODE: '',
        INSTRUMENTNUMBER: '', BankersDate: '',
    }
}
function addBankAlloc(i)       { props.form.ledger_entries[i].bank_allocation_details.push(emptyBankAlloc()) }
function removeBankAlloc(i, j) { props.form.ledger_entries[i].bank_allocation_details.splice(j, 1) }
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Contra No</label>
            <input v-model="form.voucher_number" type="text" placeholder="e.g. CTR-001"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
            <input v-model="form.voucher_total" type="number" step="0.01" placeholder="0.00"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cost Centre</label>
            <input v-model="form.cost_centre" type="text" placeholder="e.g. Head Office"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="e.g. Cash deposited to bank…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700">Ledger Entries</h3>
            <button type="button" @click="addLedger"
                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Entry</button>
        </div>
        <p class="text-xs text-gray-400 mb-2">Add Dr and Cr entries for the bank/cash accounts involved.</p>

        <div v-for="(le, i) in form.ledger_entries" :key="i"
             class="border border-gray-200 rounded-lg p-3 space-y-2 mb-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">Entry {{ i + 1 }}</span>
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
            <div class="grid grid-cols-2 gap-2">
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
            </div>

            <div class="pt-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-gray-500">Bank Allocations</span>
                    <button type="button" @click="addBankAlloc(i)"
                            class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                </div>
                <div v-for="(ba, j) in le.bank_allocation_details" :key="j"
                     class="border border-gray-200 rounded p-2 mb-1.5 space-y-1.5">
                    <div class="grid grid-cols-2 gap-1.5">
                        <div>
                            <label class="block text-xs text-gray-400 mb-0.5">Transaction Type</label>
                            <select v-model="ba.TRANSACTIONTYPE"
                                    class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option v-for="t in BANK_TRANSACTION_TYPES" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-0.5">Bank Name</label>
                            <input v-model="ba.BANKNAME" type="text"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        </div>
                    </div>
                    <div class="grid grid-cols-4 gap-1.5">
                        <div>
                            <label class="block text-xs text-gray-400 mb-0.5">Amount</label>
                            <input v-model="ba.AMOUNT" type="text"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-0.5">Date</label>
                            <input v-model="ba.Date" type="date"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-0.5">Instrument No.</label>
                            <input v-model="ba.INSTRUMENTNUMBER" type="text"
                                   class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-0.5">Transfer Mode</label>
                            <select v-model="ba.TRANSFERMODE"
                                    class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option v-for="m in TRANSFER_MODES" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" @click="removeBankAlloc(i, j)"
                                class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                    </div>
                </div>
                <p v-if="!le.bank_allocation_details.length" class="text-xs text-gray-400 italic">No bank allocations.</p>
            </div>
        </div>
        <p v-if="!form.ledger_entries.length" class="text-xs text-gray-400">No ledger entries. Click + Add Entry.</p>
    </div>
</template>
