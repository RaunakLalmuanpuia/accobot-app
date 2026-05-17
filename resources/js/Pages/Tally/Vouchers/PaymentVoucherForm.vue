<script setup>
import { ref } from 'vue'
import { useAccountParticularsForm } from './useAccountParticularsForm.js'
import VoucherGuide from './VoucherGuide.vue'

const props = defineProps({
    form:      Object,
    ledgers:   Array,
    isEditing: Boolean,
    tenant:    Object,
})

const {
    accountEntry, particulars, particularsTotal,
    ensureAccountEntry, onAccountLedgerChange, syncAccountAmount,
    addParticular, removeParticular, onParticularLedgerChange,
    addBillRef, removeBillRef, addBankAlloc, removeBankAlloc,
    loadingBills, outstandingBillsFor, isBillSelected, toggleBill,
    BANK_TRANSACTION_TYPES, TRANSFER_MODES, fmt,
} = useAccountParticularsForm(props, {
    accountDeemedPositive:    false,  // Payment: bank pays out → Cr
    particularDeemedPositive: true,   // Expense/party receives → Dr
    tenantId: props.tenant?.id,
})

const showGuide = ref(false)
</script>

<template>
    <VoucherGuide :show="showGuide" voucher-type="Payment" @close="showGuide = false" />

    <!-- ── HEADER BAR ─────────────────────────────────────────────────────── -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
        <div class="flex items-start justify-between gap-3">
        <div class="grid grid-cols-2 gap-3 flex-1">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Payment No</label>
                <input v-model="form.voucher_number" type="text" placeholder="e.g. PMT-001"
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

    <!-- ── ACCOUNT (Bank / Cash that PAYS) ───────────────────────────────── -->
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2.5">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Account (Bank / Cash Paying)</span>
        </div>
        <div v-if="accountEntry" class="px-4 py-4 space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Ledger <span class="text-red-500">*</span></label>
                    <select v-model="accountEntry.ledger_name" @change="onAccountLedgerChange"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Select Bank / Cash —</option>
                        <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                    </select>
                    <p v-if="accountEntry.ledger_group" class="text-xs text-gray-400 mt-0.5 ml-1">{{ accountEntry.ledger_group }}</p>
                </div>
                <div class="w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input v-model="accountEntry.ledger_amount" type="number" step="0.01" placeholder="0.00"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-right font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <button type="button" @click="syncAccountAmount"
                        title="Sync from particulars total"
                        class="mb-0.5 text-xs text-violet-600 hover:text-violet-800 border border-violet-200 rounded px-2 py-1.5">
                    ↺ Sync
                </button>
            </div>

            <details class="border border-gray-200 rounded-lg overflow-hidden">
                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 bg-white flex items-center justify-between select-none">
                    Bank Transaction Details
                    <button type="button" @click.stop="addBankAlloc(accountEntry)"
                            class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                </summary>
                <div class="px-3 pb-3 pt-2 space-y-3">
                    <div v-for="(ba, j) in accountEntry.bank_allocation_details" :key="j"
                         class="border border-gray-100 rounded-lg p-3 space-y-2">
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-400 mb-0.5">Transfer Mode</label>
                                <select v-model="ba.TRANSFERMODE"
                                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option value="">— Select —</option>
                                    <option v-for="m in TRANSFER_MODES" :key="m" :value="m">{{ m }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-0.5">UTR / Instrument No</label>
                                <input v-model="ba.INSTRUMENTNUMBER" type="text"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-0.5">Date</label>
                                <input v-model="ba.Date" type="date"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-400 mb-0.5">Bank Name</label>
                                <input v-model="ba.BANKNAME" type="text"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-0.5">IFSC</label>
                                <input v-model="ba.IFSCODE" type="text"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-0.5">Favouring</label>
                                <input v-model="ba.PAYMENTFAVOURING" type="text"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="removeBankAlloc(accountEntry, j)"
                                    class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                        </div>
                    </div>
                    <p v-if="!accountEntry.bank_allocation_details.length" class="text-xs text-gray-400 italic">No bank transaction details.</p>
                </div>
            </details>
        </div>
        <div v-else class="px-4 py-4 text-center">
            <button type="button" @click="ensureAccountEntry"
                    class="text-sm text-violet-600 hover:text-violet-800 font-medium">+ Set Account</button>
        </div>
    </div>

    <!-- ── PARTICULARS (Who is being paid) ───────────────────────────────── -->
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2.5 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Particulars (Paid To)</span>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500">Total: <span class="font-mono font-semibold text-gray-800">{{ fmt(particularsTotal) }}</span></span>
                <button type="button" @click="addParticular"
                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Line</button>
            </div>
        </div>

        <div v-if="particulars.length">
            <div class="grid grid-cols-[2fr_1fr_2fr_auto] gap-2 px-4 py-1.5 bg-gray-50 border-b border-gray-100 text-xs font-medium text-gray-400">
                <span>Ledger (Paid To)</span>
                <span>Amount</span>
                <span>Bill References</span>
                <span class="w-6" />
            </div>

            <div v-for="(le, i) in particulars" :key="i"
                 class="border-b border-gray-50 last:border-0 px-4 py-3 space-y-2">
                <div class="grid grid-cols-[2fr_1fr_2fr_auto] gap-2 items-start">
                    <div>
                        <select v-model="le.ledger_name" @change="onParticularLedgerChange(le)"
                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option value="">— Select Ledger —</option>
                            <option v-for="l in ledgers" :key="l.id" :value="l.ledger_name">{{ l.ledger_name }}</option>
                        </select>
                        <p v-if="le.ledger_group" class="text-xs text-gray-400 mt-0.5 ml-1">{{ le.ledger_group }}</p>
                    </div>
                    <input v-model="le.ledger_amount" type="number" step="0.01" placeholder="0.00"
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <div class="space-y-2">
                        <!-- Outstanding bills picker -->
                        <div v-if="le.ledger_name && (outstandingBillsFor(le.ledger_name).length || loadingBills)"
                             class="border border-violet-200 rounded-lg overflow-hidden">
                            <div class="bg-violet-50 px-3 py-1.5 flex items-center justify-between">
                                <span class="text-xs font-semibold text-violet-700">Outstanding Bills</span>
                                <span v-if="loadingBills" class="text-xs text-gray-400">Loading…</span>
                            </div>
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-violet-100 text-gray-500">
                                        <th class="px-3 py-1.5 text-left font-medium">Reference</th>
                                        <th class="px-3 py-1.5 text-left font-medium">Date</th>
                                        <th class="px-3 py-1.5 text-right font-medium">Invoiced</th>
                                        <th class="px-3 py-1.5 text-right font-medium">Settled</th>
                                        <th class="px-3 py-1.5 text-right font-medium text-violet-700">Outstanding</th>
                                        <th class="px-3 py-1.5 w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="bill in outstandingBillsFor(le.ledger_name)" :key="bill.reference"
                                        class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer"
                                        @click="toggleBill(bill, le)">
                                        <td class="px-3 py-1.5 font-mono">{{ bill.reference }}</td>
                                        <td class="px-3 py-1.5 text-gray-500">{{ bill.invoice_date }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono">{{ fmt(bill.invoiced) }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono text-gray-400">{{ fmt(bill.settled) }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono font-semibold text-violet-700">{{ fmt(bill.outstanding) }}</td>
                                        <td class="px-3 py-1.5 text-center">
                                            <input type="checkbox" :checked="isBillSelected(bill, le)"
                                                   @click.stop="toggleBill(bill, le)"
                                                   class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Manual bill refs -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-400">{{ le.bills_allocation.length }} ref(s)</span>
                                <button type="button" @click="addBillRef(le)"
                                        class="text-xs text-violet-600 hover:text-violet-800">+ Add</button>
                            </div>
                            <div v-for="(br, j) in le.bills_allocation" :key="j"
                                 class="grid grid-cols-[0.7fr_1fr_0.7fr_0.7fr_auto] gap-1 mb-1 items-center">
                                <select v-model="br.AgstType" class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                    <option>New Ref</option><option>Agst Ref</option><option>On Account</option><option>Advance</option>
                                </select>
                                <input v-model="br.Reference" type="text" placeholder="Ref No"
                                       class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                <input v-model="br.CreditPeriod" type="text" placeholder="Period"
                                       class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                <input v-model="br.Amount" type="number" step="0.01"
                                       class="w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                <button type="button" @click="removeBillRef(le, j)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                            </div>
                            <p v-if="!le.bills_allocation.length" class="text-xs text-gray-400 italic">No bill references.</p>
                        </div>
                    </div>
                    <button type="button" @click="removeParticular(le)"
                            class="text-red-400 hover:text-red-600 text-sm leading-none mt-1">✕</button>
                </div>
            </div>
        </div>
        <p v-else class="px-4 py-4 text-xs text-gray-400 text-center">No particulars. Click + Add Line.</p>
    </div>

    <!-- ── NARRATION ──────────────────────────────────────────────────────── -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
        <textarea v-model="form.narration" rows="2" placeholder="Notes…"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
    </div>
</template>
