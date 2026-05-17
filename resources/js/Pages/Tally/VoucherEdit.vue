<script setup>
import { ref, computed } from 'vue'
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
    tenant:     Object,
    voucher:    Object,
    ledgers:    Array,
    stockItems: Array,
    godowns:    Array,
})

function s(v) { return v ?? '' }
function n(v) { return v ?? '' }
function b(v, def = false) { return v ?? def }

const v = props.voucher

const form = useForm({
    voucher_type:      s(v.voucher_type),
    voucher_base_type: s(v.voucher_base_type),
    voucher_number:    s(v.voucher_number),
    voucher_date:      v.voucher_date ? v.voucher_date.substring(0, 10) : '',
    party_name:        s(v.party_name),
    voucher_total:     n(v.voucher_total),
    narration:         s(v.narration),
    is_invoice:        b(v.is_invoice),
    reference:         s(v.reference),
    reference_date:    s(v.reference_date),
    place_of_supply:   s(v.place_of_supply),
    cost_centre:       s(v.cost_centre),
    buyer_name:        s(v.buyer_name),
    buyer_alias:       s(v.buyer_alias),
    buyer_gstin:       s(v.buyer_gstin),
    buyer_pin_code:    s(v.buyer_pin_code),
    buyer_state:       s(v.buyer_state),
    buyer_country:     s(v.buyer_country),
    buyer_gst_registration_type: s(v.buyer_gst_registration_type),
    buyer_email:       s(v.buyer_email),
    buyer_mobile:      s(v.buyer_mobile),
    buyer_address: Array.isArray(v.buyer_address)
        ? v.buyer_address.map(a => a.BuyerAddress || '').filter(Boolean).join('\n')
        : s(v.buyer_address),
    consignee_name:    s(v.consignee_name),
    consignee_gstin:   s(v.consignee_gstin),
    consignee_tally_group: s(v.consignee_tally_group),
    consignee_pin_code:    s(v.consignee_pin_code),
    consignee_state:       s(v.consignee_state),
    consignee_country:     s(v.consignee_country),
    consignee_gst_registration_type: s(v.consignee_gst_registration_type),
    consignee_address: Array.isArray(v.consignee_address)
        ? v.consignee_address.map(a => a.ConsigneeAddress || '').filter(Boolean).join('\n')
        : s(v.consignee_address),
    delivery_note_no:   s(v.delivery_note_no),
    delivery_note_date: s(v.delivery_note_date),
    dispatch_doc_no:    s(v.dispatch_doc_no),
    dispatch_through:   s(v.dispatch_through),
    destination:        s(v.destination),
    carrier_name:       s(v.carrier_name),
    lr_no:              s(v.lr_no),
    lr_date:            s(v.lr_date),
    motor_vehicle_no:   s(v.motor_vehicle_no),
    order_no:           s(v.order_no),
    order_date:         s(v.order_date),
    terms_of_payment:   s(v.terms_of_payment),
    terms_of_delivery:  s(v.terms_of_delivery),
    other_references:   s(v.other_references),
    irn:                s(v.irn),
    acknowledgement_no:   s(v.acknowledgement_no),
    acknowledgement_date: s(v.acknowledgement_date),
    qr_code:            s(v.qr_code),
    ledger_entries: (v.ledger_entries ?? []).map(le => ({
        ledger_name:        s(le.ledger_name),
        ledger_group:       s(le.ledger_group),
        ledger_amount:      n(le.ledger_amount),
        is_deemed_positive: b(le.is_deemed_positive, true),
        is_party_ledger:    b(le.is_party_ledger),
        igst_rate:          s(le.igst_rate),
        hsn_code:           s(le.hsn_code),
        cess_rate:          s(le.cess_rate),
        bills_allocation:        le.bills_allocation        ? JSON.parse(JSON.stringify(le.bills_allocation))        : [],
        bank_allocation_details: le.bank_allocation_details ? JSON.parse(JSON.stringify(le.bank_allocation_details)) : [],
    })),
    inventory_entries: (v.inventory_entries ?? []).map(ie => ({
        stock_item_name:   s(ie.stock_item_name),
        item_code:         s(ie.item_code),
        group_name:        s(ie.group_name),
        hsn_code:          s(ie.hsn_code),
        unit:              s(ie.unit),
        billed_qty:        n(ie.billed_qty),
        actual_qty:        n(ie.actual_qty),
        rate:              n(ie.rate),
        igst_rate:         n(ie.igst_rate),
        cess_rate:         n(ie.cess_rate),
        discount_percent:  n(ie.discount_percent),
        amount:            n(ie.amount),
        tax_amount:        n(ie.tax_amount),
        mrp:               n(ie.mrp),
        sales_ledger:      s(ie.sales_ledger),
        godown_name:       s(ie.godown_name),
        batch_name:        s(ie.batch_name),
        is_deemed_positive:     b(ie.is_deemed_positive),
        batch_allocations:      ie.batch_allocations      ? JSON.parse(JSON.stringify(ie.batch_allocations))      : [],
        accounting_allocations: ie.accounting_allocations ? JSON.parse(JSON.stringify(ie.accounting_allocations)) : [],
    })),
})

const salesInitialMode = computed(() => {
    if (!v.is_invoice) return 'voucher'
    return (v.inventory_entries?.length ?? 0) > 0 ? 'item' : 'accounting'
})

const purchaseInitialMode = computed(() => {
    if (!v.is_invoice) return 'voucher'
    return (v.inventory_entries?.length ?? 0) > 0 ? 'item' : 'accounting'
})

const TYPE_COLORS = {
    Sales:      'bg-emerald-100 text-emerald-700',
    Purchase:   'bg-blue-100 text-blue-700',
    Receipt:    'bg-violet-100 text-violet-700',
    Payment:    'bg-orange-100 text-orange-700',
    Contra:     'bg-gray-100 text-gray-700',
    Journal:    'bg-pink-100 text-pink-700',
    CreditNote: 'bg-yellow-100 text-yellow-700',
    DebitNote:  'bg-red-100 text-red-700',
}

function submit() {
    form.put(route('tally.vouchers.update', { tenant: props.tenant.id, voucher: v.id }), {
        onSuccess: () => router.visit(route('tally.vouchers.index', { tenant: props.tenant.id })),
    })
}

function destroy() {
    const label = v.voucher_number ?? `Voucher #${v.id}`
    const msg = v.tally_id
        ? `Mark "${label}" inactive and queue deletion in Tally?`
        : `Delete "${label}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.vouchers.destroy', { tenant: props.tenant.id, voucher: v.id }), {
        onSuccess: () => router.visit(route('tally.vouchers.index', { tenant: props.tenant.id })),
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('tally.vouchers.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">← Vouchers</Link>
                    <span class="text-gray-300">/</span>
                    <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', TYPE_COLORS[voucher.voucher_base_type] ?? 'bg-gray-100 text-gray-700']">
                        {{ voucher.voucher_base_type }}
                    </span>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Edit {{ voucher.voucher_number ?? `#${voucher.id}` }}
                    </h1>
                    <span v-if="voucher.tally_id"
                          class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Synced to Tally</span>
                </div>
                <button type="button" @click="destroy"
                        class="text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-300 rounded-lg px-3 py-1.5 transition">
                    Delete Voucher
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- Flash -->
                <div v-if="$page.props.flash?.error"
                     class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    {{ $page.props.flash.error }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <SalesVoucherForm v-if="voucher.voucher_base_type === 'Sales'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="true"
                        :default-mode="salesInitialMode" />
                    <PurchaseVoucherForm v-else-if="voucher.voucher_base_type === 'Purchase'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="true"
                        :default-mode="purchaseInitialMode" />
                    <CreditNoteForm v-else-if="voucher.voucher_base_type === 'CreditNote'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="true"
                        :default-mode="salesInitialMode" />
                    <DebitNoteForm v-else-if="voucher.voucher_base_type === 'DebitNote'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="true"
                        :default-mode="purchaseInitialMode" />
                    <ReceiptVoucherForm v-else-if="voucher.voucher_base_type === 'Receipt'"
                        :form="form" :ledgers="ledgers" :is-editing="true" />
                    <PaymentVoucherForm v-else-if="voucher.voucher_base_type === 'Payment'"
                        :form="form" :ledgers="ledgers" :is-editing="true" />
                    <ContraVoucherForm v-else-if="voucher.voucher_base_type === 'Contra'"
                        :form="form" :ledgers="ledgers" :is-editing="true" />
                    <JournalVoucherForm v-else-if="voucher.voucher_base_type === 'Journal'"
                        :form="form" :ledgers="ledgers" :is-editing="true" />
                    <div v-else class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Unsupported voucher type: {{ voucher.voucher_base_type }}
                    </div>

                    <div class="flex gap-3 pt-2 pb-8 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing"
                                class="rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ form.processing ? 'Saving…' : 'Update Voucher' }}
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
