<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'
import SalesVoucherForm      from './Vouchers/SalesVoucherForm.vue'
import PurchaseVoucherForm   from './Vouchers/PurchaseVoucherForm.vue'
import CreditNoteForm        from './Vouchers/CreditNoteForm.vue'
import DebitNoteForm         from './Vouchers/DebitNoteForm.vue'
import ReceiptVoucherForm    from './Vouchers/ReceiptVoucherForm.vue'
import PaymentVoucherForm    from './Vouchers/PaymentVoucherForm.vue'
import ContraVoucherForm     from './Vouchers/ContraVoucherForm.vue'
import JournalVoucherForm    from './Vouchers/JournalVoucherForm.vue'

const props = defineProps({
    tenant:                   Object,
    vouchers:                 Array,
    ledgers:                  Array,
    stockItems:               Array,
    godowns:                  Array,
    nextSalesVoucherNumber:   Number,
})

const canManage = hasPermission('integrations.manage')

// ── Filters ────────────────────────────────────────────────────────────────────
const search     = ref('')
const typeFilter = ref('all')

const voucherTypes = computed(() => {
    const set = new Set(props.vouchers.map(v => v.voucher_base_type).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.vouchers
    if (typeFilter.value !== 'all') {
        list = list.filter(v => v.voucher_base_type === typeFilter.value)
    }
    const q = search.value.toLowerCase()
    if (q) {
        list = list.filter(v =>
            (v.voucher_number ?? '').toLowerCase().includes(q) ||
            (v.party_name ?? '').toLowerCase().includes(q) ||
            (v.narration ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

// ── Badges ─────────────────────────────────────────────────────────────────────
const typeBadge = {
    'Sales':       'bg-emerald-100 text-emerald-700',
    'Purchase':    'bg-blue-100 text-blue-700',
    'Receipt':     'bg-violet-100 text-violet-700',
    'Payment':     'bg-orange-100 text-orange-700',
    'CreditNote':  'bg-yellow-100 text-yellow-700',
    'DebitNote':   'bg-red-100 text-red-700',
    'Contra':      'bg-gray-100 text-gray-600',
    'Journal':     'bg-pink-100 text-pink-700',
}

function badgeCls(type) {
    return typeBadge[type] ?? 'bg-gray-100 text-gray-600'
}

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending', cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',   cls: 'bg-gray-100 text-gray-400'   }
}

function formatAmount(v) {
    if (v === null || v === undefined) return '—'
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(v)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-IN')
}

const VOUCHER_TYPES = [
    'Sales', 'Purchase', 'Receipt', 'Payment',
    'Contra', 'Journal', 'CreditNote', 'DebitNote',
]

// ── CRUD ───────────────────────────────────────────────────────────────────────

const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    // Core
    voucher_type:      '',
    voucher_base_type: '',
    voucher_number:    '',
    voucher_date:    '',
    party_name:      '',
    voucher_total:   '',
    narration:       '',
    is_invoice:      false,
    reference:       '',
    reference_date:  '',
    place_of_supply: '',
    cost_centre:     '',

    // Buyer
    buyer_name:                  '',
    buyer_alias:                 '',
    buyer_gstin:                 '',
    buyer_pin_code:              '',
    buyer_state:                 '',
    buyer_country:               '',
    buyer_gst_registration_type: '',
    buyer_email:                 '',
    buyer_mobile:                '',
    buyer_address:               '',

    // Consignee
    consignee_name:                  '',
    consignee_gstin:                 '',
    consignee_tally_group:           '',
    consignee_pin_code:              '',
    consignee_state:                 '',
    consignee_country:               '',
    consignee_gst_registration_type: '',

    // Dispatch
    delivery_note_no:  '',
    delivery_note_date: '',
    dispatch_doc_no:   '',
    dispatch_through:  '',
    destination:       '',
    carrier_name:      '',
    lr_no:             '',
    lr_date:           '',
    motor_vehicle_no:  '',

    // Order
    order_no:          '',
    order_date:        '',
    terms_of_payment:  '',
    terms_of_delivery: '',
    other_references:  '',

    // e-Invoice
    irn:                  '',
    acknowledgement_no:   '',
    acknowledgement_date: '',
    qr_code:              '',

    // Child entries
    ledger_entries:    [],
    inventory_entries: [],
})

watch(() => form.voucher_base_type, (val) => {
    if (val) form.voucher_type = val
    if (val === 'Sales' && !isEditing.value && !form.voucher_number) {
        form.voucher_number = String(props.nextSalesVoucherNumber)
    }
})

// ── Open / close ───────────────────────────────────────────────────────────────
function str(v)            { return v ?? '' }
function num(v)            { return v ?? '' }
function bool(v, def=false){ return v ?? def }

function openCreate() {
    form.reset()
    form.clearErrors()
    form.ledger_entries    = []
    form.inventory_entries = []
    modal.value = 'create'
}

function openEdit(v) {
    // Core
    form.voucher_type      = str(v.voucher_type)
    form.voucher_base_type = str(v.voucher_base_type)
    form.voucher_number    = str(v.voucher_number)
    form.voucher_date    = v.voucher_date ? v.voucher_date.substring(0, 10) : ''
    form.party_name      = str(v.party_name)
    form.voucher_total   = num(v.voucher_total)
    form.narration       = str(v.narration)
    form.is_invoice      = bool(v.is_invoice)
    form.reference       = str(v.reference)
    form.reference_date  = str(v.reference_date)
    form.place_of_supply = str(v.place_of_supply)
    form.cost_centre     = str(v.cost_centre)
    // Buyer
    form.buyer_name                  = str(v.buyer_name)
    form.buyer_alias                 = str(v.buyer_alias)
    form.buyer_gstin                 = str(v.buyer_gstin)
    form.buyer_pin_code              = str(v.buyer_pin_code)
    form.buyer_state                 = str(v.buyer_state)
    form.buyer_country               = str(v.buyer_country)
    form.buyer_gst_registration_type = str(v.buyer_gst_registration_type)
    form.buyer_email                 = str(v.buyer_email)
    form.buyer_mobile                = str(v.buyer_mobile)
    form.buyer_address               = Array.isArray(v.buyer_address)
        ? (v.buyer_address[0]?.BuyerAddress ?? '')
        : str(v.buyer_address)
    // Consignee
    form.consignee_name                  = str(v.consignee_name)
    form.consignee_gstin                 = str(v.consignee_gstin)
    form.consignee_tally_group           = str(v.consignee_tally_group)
    form.consignee_pin_code              = str(v.consignee_pin_code)
    form.consignee_state                 = str(v.consignee_state)
    form.consignee_country               = str(v.consignee_country)
    form.consignee_gst_registration_type = str(v.consignee_gst_registration_type)
    // Dispatch
    form.delivery_note_no   = str(v.delivery_note_no)
    form.delivery_note_date = str(v.delivery_note_date)
    form.dispatch_doc_no    = str(v.dispatch_doc_no)
    form.dispatch_through   = str(v.dispatch_through)
    form.destination        = str(v.destination)
    form.carrier_name       = str(v.carrier_name)
    form.lr_no              = str(v.lr_no)
    form.lr_date            = str(v.lr_date)
    form.motor_vehicle_no   = str(v.motor_vehicle_no)
    // Order
    form.order_no          = str(v.order_no)
    form.order_date        = str(v.order_date)
    form.terms_of_payment  = str(v.terms_of_payment)
    form.terms_of_delivery = str(v.terms_of_delivery)
    form.other_references  = str(v.other_references)
    // e-Invoice
    form.irn                  = str(v.irn)
    form.acknowledgement_no   = str(v.acknowledgement_no)
    form.acknowledgement_date = str(v.acknowledgement_date)
    form.qr_code              = str(v.qr_code)
    // Child entries
    form.ledger_entries = (v.ledger_entries ?? []).map(le => ({
        ledger_name:        str(le.ledger_name),
        ledger_group:       str(le.ledger_group),
        ledger_amount:      num(le.ledger_amount),
        is_deemed_positive: bool(le.is_deemed_positive, true),
        is_party_ledger:    bool(le.is_party_ledger),
        igst_rate:          str(le.igst_rate),
        hsn_code:           str(le.hsn_code),
        cess_rate:          str(le.cess_rate),
        bills_allocation:        le.bills_allocation        ? JSON.parse(JSON.stringify(le.bills_allocation))        : [],
        bank_allocation_details: le.bank_allocation_details ? JSON.parse(JSON.stringify(le.bank_allocation_details)) : [],
    }))
    form.inventory_entries = (v.inventory_entries ?? []).map(ie => ({
        stock_item_name:   str(ie.stock_item_name),
        item_code:         str(ie.item_code),
        group_name:        str(ie.group_name),
        hsn_code:          str(ie.hsn_code),
        unit:              str(ie.unit),
        billed_qty:        num(ie.billed_qty),
        actual_qty:        num(ie.actual_qty),
        rate:              num(ie.rate),
        igst_rate:         num(ie.igst_rate),
        cess_rate:         num(ie.cess_rate),
        discount_percent:  num(ie.discount_percent),
        amount:            num(ie.amount),
        tax_amount:        num(ie.tax_amount),
        mrp:               num(ie.mrp),
        sales_ledger:      str(ie.sales_ledger),
        godown_name:       str(ie.godown_name),
        batch_name:        str(ie.batch_name),
        is_deemed_positive:     bool(ie.is_deemed_positive),
        batch_allocations:      ie.batch_allocations      ? JSON.parse(JSON.stringify(ie.batch_allocations))      : [],
        accounting_allocations: ie.accounting_allocations ? JSON.parse(JSON.stringify(ie.accounting_allocations)) : [],
    }))
    form.clearErrors()
    modal.value = v
}

function closeModal() {
    modal.value = null
    form.reset()
    form.ledger_entries    = []
    form.inventory_entries = []
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.vouchers.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.vouchers.update', { tenant: props.tenant.id, voucher: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(v) {
    const label = v.voucher_number ?? `Voucher #${v.id}`
    const msg = v.tally_id
        ? `Mark "${label}" inactive and queue deletion in Tally?`
        : `Delete "${label}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.vouchers.destroy', { tenant: props.tenant.id, voucher: v.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Vouchers</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ vouchers.length }} vouchers synced from Tally</p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Voucher
                    </button>
                    <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success"
                     class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.info"
                     class="rounded-lg bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-800">
                    {{ $page.props.flash.info }}
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search vouchers…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />

                    <div class="flex flex-wrap gap-2">
                        <button v-for="type in voucherTypes" :key="type"
                                @click="typeFilter = type"
                                :class="[
                                    'px-3 py-1.5 text-xs font-medium rounded-full border transition',
                                    typeFilter === type
                                        ? 'bg-violet-600 text-white border-violet-600'
                                        : 'bg-white text-gray-600 border-gray-300 hover:border-violet-400'
                                ]">
                            {{ type === 'all' ? 'All Types' : type }}
                        </button>
                    </div>

                    <span class="text-sm text-gray-400 ml-auto">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-2">Type</div>
                        <div class="col-span-2">Number</div>
                        <div class="col-span-1">Date</div>
                        <div class="col-span-3">Party</div>
                        <div class="col-span-2 text-right">Amount</div>
                        <div class="col-span-1 text-center">Tally</div>
                        <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="voucher in filtered" :key="voucher.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-2">
                            <span :class="badgeCls(voucher.voucher_type)"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ voucher.voucher_type }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <Link :href="route('tally.vouchers.show', { tenant: tenant.id, voucher: voucher.id })"
                                  class="text-sm font-mono text-violet-600 hover:text-violet-800">
                                {{ voucher.voucher_number ?? '—' }}
                            </Link>
                        </div>
                        <div class="col-span-1 text-sm text-gray-500">{{ formatDate(voucher.voucher_date) }}</div>
                        <div class="col-span-3">
                            <p class="text-sm text-gray-800 truncate">{{ voucher.party_name ?? '—' }}</p>
                            <p v-if="voucher.narration" class="text-xs text-gray-400 truncate mt-0.5">{{ voucher.narration }}</p>
                        </div>
                        <div class="col-span-2 text-right text-sm font-medium text-gray-900">
                            {{ formatAmount(voucher.voucher_total) }}
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="syncBadge(voucher.sync_status).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ syncBadge(voucher.sync_status).label }}
                            </span>
                        </div>
                        <div class="col-span-1 text-right" v-if="canManage">
                            <button @click="openEdit(voucher)"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                            <button @click="destroy(voucher)"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No vouchers found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Slide-over -->
    <Teleport to="body">
        <div v-if="modal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeModal" />
            <div :class="['relative z-50 w-full bg-white shadow-xl flex flex-col',
                          ['Sales', 'Purchase', 'CreditNote', 'DebitNote'].includes(form.voucher_base_type) ? 'max-w-4xl' : 'max-w-2xl']">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Voucher' : 'New Voucher' }}
                        <span v-if="form.voucher_base_type"
                              class="ml-2 text-xs font-normal px-2 py-0.5 rounded-full bg-violet-100 text-violet-700">
                            {{ form.voucher_base_type }}
                        </span>
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                    <!-- Voucher type selector -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Voucher Base Type <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.voucher_base_type" :disabled="isEditing"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500">
                                <option value="">— Select —</option>
                                <option v-for="t in VOUCHER_TYPES" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="form.errors.voucher_base_type" class="mt-1 text-xs text-red-500">{{ form.errors.voucher_base_type }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Voucher Type
                                <span class="text-xs text-gray-400 font-normal">(auto-filled)</span>
                            </label>
                            <input v-model="form.voucher_type" type="text"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50" />
                            <p v-if="form.errors.voucher_type" class="mt-1 text-xs text-red-500">{{ form.errors.voucher_type }}</p>
                        </div>
                    </div>

                    <!-- No type selected prompt -->
                    <div v-if="!form.voucher_base_type"
                         class="flex items-center justify-center py-12 text-sm text-gray-400 border border-dashed border-gray-200 rounded-lg">
                        Select a voucher type above to continue
                    </div>

                    <!-- Per-type form components -->
                    <SalesVoucherForm v-else-if="form.voucher_base_type === 'Sales'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="isEditing" />
                    <PurchaseVoucherForm v-else-if="form.voucher_base_type === 'Purchase'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="isEditing" />
                    <CreditNoteForm v-else-if="form.voucher_base_type === 'CreditNote'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="isEditing" />
                    <DebitNoteForm v-else-if="form.voucher_base_type === 'DebitNote'"
                        :form="form" :ledgers="ledgers" :stock-items="stockItems" :godowns="godowns" :is-editing="isEditing" />
                    <ReceiptVoucherForm v-else-if="form.voucher_base_type === 'Receipt'"
                        :form="form" :ledgers="ledgers" :is-editing="isEditing" />
                    <PaymentVoucherForm v-else-if="form.voucher_base_type === 'Payment'"
                        :form="form" :ledgers="ledgers" :is-editing="isEditing" />
                    <ContraVoucherForm v-else-if="form.voucher_base_type === 'Contra'"
                        :form="form" :ledgers="ledgers" :is-editing="isEditing" />
                    <JournalVoucherForm v-else-if="form.voucher_base_type === 'Journal'"
                        :form="form" :ledgers="ledgers" :is-editing="isEditing" />

                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditing ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
