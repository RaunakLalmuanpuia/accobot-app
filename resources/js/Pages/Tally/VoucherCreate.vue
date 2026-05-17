<script setup>
import { ref, watch } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SalesVoucherForm    from './Vouchers/SalesVoucherForm.vue'
import PurchaseVoucherForm from './Vouchers/PurchaseVoucherForm.vue'
import CreditNoteForm      from './Vouchers/CreditNoteForm.vue'
import DebitNoteForm       from './Vouchers/DebitNoteForm.vue'
import ReceiptVoucherForm  from './Vouchers/ReceiptVoucherForm.vue'
import PaymentVoucherForm  from './Vouchers/PaymentVoucherForm.vue'
import ContraVoucherForm   from './Vouchers/ContraVoucherForm.vue'
import JournalVoucherForm  from './Vouchers/JournalVoucherForm.vue'

const props = defineProps({
    tenant:                   Object,
    ledgers:                  Array,
    stockItems:               Array,
    godowns:                  Array,
    nextSalesVoucherNumber:   Number,
})

const VOUCHER_TYPES = [
    { key: 'Sales',      label: 'Sales',       desc: 'Invoice / Tax Invoice' },
    { key: 'Purchase',   label: 'Purchase',    desc: 'Supplier invoice' },
    { key: 'Receipt',    label: 'Receipt',     desc: 'Money received' },
    { key: 'Payment',    label: 'Payment',     desc: 'Money paid out' },
    { key: 'Contra',     label: 'Contra',      desc: 'Cash ↔ Bank transfer' },
    { key: 'Journal',    label: 'Journal',     desc: 'Manual Dr/Cr entry' },
    { key: 'CreditNote', label: 'Credit Note', desc: 'Return / adjustment to customer' },
    { key: 'DebitNote',  label: 'Debit Note',  desc: 'Return / adjustment from supplier' },
]

const TYPE_COLORS = {
    Sales:      'bg-emerald-100 text-emerald-700 border-emerald-200 hover:bg-emerald-50',
    Purchase:   'bg-blue-100 text-blue-700 border-blue-200 hover:bg-blue-50',
    Receipt:    'bg-violet-100 text-violet-700 border-violet-200 hover:bg-violet-50',
    Payment:    'bg-orange-100 text-orange-700 border-orange-200 hover:bg-orange-50',
    Contra:     'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-50',
    Journal:    'bg-pink-100 text-pink-700 border-pink-200 hover:bg-pink-50',
    CreditNote: 'bg-yellow-100 text-yellow-700 border-yellow-200 hover:bg-yellow-50',
    DebitNote:  'bg-red-100 text-red-700 border-red-200 hover:bg-red-50',
}

const selectedType = ref(new URLSearchParams(window.location.search).get('type') || '')

const form = useForm({
    voucher_type:      '',
    voucher_base_type: '',
    voucher_number:    '',
    voucher_date:      new Date().toISOString().split('T')[0],
    party_name:        '',
    voucher_total:     '',
    narration:         '',
    is_invoice:        false,
    reference:         '',
    reference_date:    '',
    place_of_supply:   '',
    cost_centre:       '',
    buyer_name:        '',
    buyer_alias:       '',
    buyer_gstin:       '',
    buyer_pin_code:    '',
    buyer_state:       '',
    buyer_country:     '',
    buyer_gst_registration_type: '',
    buyer_email:       '',
    buyer_mobile:      '',
    buyer_address:     '',
    consignee_name:    '',
    consignee_gstin:   '',
    consignee_tally_group: '',
    consignee_pin_code:    '',
    consignee_state:       '',
    consignee_country:     '',
    consignee_gst_registration_type: '',
    consignee_address: '',
    delivery_note_no:   '',
    delivery_note_date: '',
    dispatch_doc_no:    '',
    dispatch_through:   '',
    destination:        '',
    carrier_name:       '',
    lr_no:              '',
    lr_date:            '',
    motor_vehicle_no:   '',
    order_no:           '',
    order_date:         '',
    terms_of_payment:   '',
    terms_of_delivery:  '',
    other_references:   '',
    irn:                '',
    acknowledgement_no:   '',
    acknowledgement_date: '',
    qr_code:            '',
    ledger_entries:    [],
    inventory_entries: [],
})

watch(selectedType, (val) => {
    form.voucher_base_type = val
    form.voucher_type      = val
    form.ledger_entries    = []
    form.inventory_entries = []
    if (val === 'Sales' && !form.voucher_number) {
        form.voucher_number = String(props.nextSalesVoucherNumber)
    }
    if (['Receipt', 'Payment', 'Contra'].includes(val)) {
        const isDr = ['Receipt', 'Contra'].includes(val)
        form.ledger_entries = [{
            ledger_name: '', ledger_group: '', ledger_amount: '',
            is_deemed_positive: isDr, is_party_ledger: false,
            igst_rate: '', hsn_code: '', cess_rate: '',
            bills_allocation: [], bank_allocation_details: [],
        }]
    }
})

function submit() {
    form.post(route('tally.vouchers.store', { tenant: props.tenant.id }), {
        onSuccess: () => router.visit(route('tally.vouchers.index', { tenant: props.tenant.id })),
    })
}

const hasInventoryTypes = ['Sales', 'Purchase', 'CreditNote', 'DebitNote']
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('tally.vouchers.index', { tenant: tenant.id })"
                      class="text-sm text-gray-500 hover:text-gray-700">← Vouchers</Link>
                <span class="text-gray-300">/</span>
                <h1 class="text-xl font-semibold text-gray-900">
                    New {{ selectedType || 'Voucher' }}
                </h1>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- Flash -->
                <div v-if="$page.props.flash?.error"
                     class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Type selector -->
                <div v-if="!selectedType" class="bg-white border border-gray-200 rounded-2xl p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4">Select Voucher Type</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <button v-for="t in VOUCHER_TYPES" :key="t.key"
                                type="button"
                                @click="selectedType = t.key"
                                :class="['border rounded-xl px-4 py-3 text-left transition cursor-pointer', TYPE_COLORS[t.key]]">
                            <div class="text-sm font-semibold">{{ t.label }}</div>
                            <div class="text-xs opacity-70 mt-0.5">{{ t.desc }}</div>
                        </button>
                    </div>
                </div>

                <!-- Change type button when type is selected -->
                <div v-else class="flex items-center gap-3">
                    <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full border', TYPE_COLORS[selectedType]]">
                        {{ selectedType }}
                    </span>
                    <button type="button" @click="selectedType = ''"
                            class="text-xs text-gray-400 hover:text-gray-600">Change type</button>
                </div>

                <!-- Form -->
                <form v-if="selectedType" @submit.prevent="submit" class="space-y-5">
                    <SalesVoucherForm v-if="selectedType === 'Sales'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="false"
                        default-mode="item" />
                    <PurchaseVoucherForm v-else-if="selectedType === 'Purchase'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="false"
                        default-mode="item" />
                    <CreditNoteForm v-else-if="selectedType === 'CreditNote'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="false"
                        default-mode="item" />
                    <DebitNoteForm v-else-if="selectedType === 'DebitNote'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="false"
                        default-mode="item" />
                    <ReceiptVoucherForm v-else-if="selectedType === 'Receipt'"
                        :form="form" :ledgers="ledgers" :is-editing="false" />
                    <PaymentVoucherForm v-else-if="selectedType === 'Payment'"
                        :form="form" :ledgers="ledgers" :is-editing="false" />
                    <ContraVoucherForm v-else-if="selectedType === 'Contra'"
                        :form="form" :ledgers="ledgers" :is-editing="false" />
                    <JournalVoucherForm v-else-if="selectedType === 'Journal'"
                        :form="form" :ledgers="ledgers" :is-editing="false" />

                    <div class="flex gap-3 pt-2 pb-8 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing"
                                class="rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ form.processing ? 'Saving…' : 'Save Voucher' }}
                        </button>
                        <Link :href="route('tally.vouchers.index', { tenant: tenant.id })"
                              class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </Link>
                    </div>
                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
