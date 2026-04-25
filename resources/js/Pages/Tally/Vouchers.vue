<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:         Object,
    vouchers:       Array,
    ledgerNames:    Array,
    stockItemNames: Array,
})

const canManage = hasPermission('integrations.manage')

// ── Filters ────────────────────────────────────────────────────────────────────
const search     = ref('')
const typeFilter = ref('all')

const voucherTypes = computed(() => {
    const set = new Set(props.vouchers.map(v => v.voucher_type))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.vouchers
    if (typeFilter.value !== 'all') {
        list = list.filter(v => v.voucher_type === typeFilter.value)
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
    'Payroll':     'bg-teal-100 text-teal-700',
    'Attendance':  'bg-sky-100 text-sky-700',
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
    'Payroll', 'Attendance',
]

const showInventory = computed(() =>
    ['Sales', 'Purchase', 'DebitNote', 'CreditNote'].includes(form.voucher_type)
)

const isPayroll = computed(() =>
    ['Payroll', 'Attendance'].includes(form.voucher_type)
)

// ── CRUD ───────────────────────────────────────────────────────────────────────
const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    // Core
    voucher_type:    '',
    voucher_number:  '',
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

    // Child entries
    ledger_entries:    [],
    inventory_entries: [],
})

// ── Child entry helpers ────────────────────────────────────────────────────────
function emptyLedger() {
    return {
        ledger_name: '', ledger_group: '', ledger_amount: '',
        is_deemed_positive: true, is_party_ledger: false,
        igst_rate: '', hsn_code: '', cess_rate: '',
    }
}
function addLedger()     { form.ledger_entries.push(emptyLedger()) }
function removeLedger(i) { form.ledger_entries.splice(i, 1) }

function emptyInventory() {
    return {
        stock_item_name: '', item_code: '', group_name: '', hsn_code: '', unit: '',
        billed_qty: '', actual_qty: '', rate: '', igst_rate: '', cess_rate: '',
        discount_percent: '', amount: '', tax_amount: '', mrp: '',
        sales_ledger: '', godown_name: '', batch_name: '',
        is_deemed_positive: false,
    }
}
function addInventory()     { form.inventory_entries.push(emptyInventory()) }
function removeInventory(i) { form.inventory_entries.splice(i, 1) }

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
    form.voucher_type    = str(v.voucher_type)
    form.voucher_number  = str(v.voucher_number)
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
        is_deemed_positive: bool(ie.is_deemed_positive),
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
            <div class="relative z-50 w-full max-w-2xl bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Voucher' : 'New Voucher' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                    <!-- ── Core fields ─────────────────────────────────────── -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Voucher Type <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.voucher_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option v-for="t in VOUCHER_TYPES" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="form.errors.voucher_type" class="mt-1 text-xs text-red-500">{{ form.errors.voucher_type }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.voucher_date" type="date"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.voucher_date" class="mt-1 text-xs text-red-500">{{ form.errors.voucher_date }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Voucher Number</label>
                            <input v-model="form.voucher_number" type="text" placeholder="e.g. INV-001"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Party Name</label>
                            <input v-model="form.party_name" type="text" placeholder="e.g. ABC Traders"
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
                            <input v-model="form.reference" type="text" placeholder="e.g. PO-2024-001"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference Date</label>
                            <input v-model="form.reference_date" type="text" placeholder="e.g. 20250401"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Place of Supply</label>
                            <input v-model="form.place_of_supply" type="text" placeholder="e.g. Maharashtra"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cost Centre</label>
                            <input v-model="form.cost_centre" type="text" placeholder="e.g. Head Office"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_invoice" type="checkbox"
                                       class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                <span class="text-sm text-gray-700">Is Invoice</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Narration</label>
                        <textarea v-model="form.narration" rows="2" placeholder="Notes / description…"
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
                    </div>

                    <!-- ── Buyer Details (collapsible) ─────────────────────── -->
                    <details class="border border-gray-200 rounded-lg overflow-hidden">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50">
                            Buyer Details
                        </summary>
                        <div class="px-4 pb-4 pt-3 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Buyer Name</label>
                                    <input v-model="form.buyer_name" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Buyer Alias</label>
                                    <input v-model="form.buyer_alias" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Buyer GSTIN</label>
                                    <input v-model="form.buyer_gstin" type="text" placeholder="15-char GSTIN"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Pin Code</label>
                                    <input v-model="form.buyer_pin_code" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                    <input v-model="form.buyer_state" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                    <input v-model="form.buyer_country" type="text" placeholder="e.g. India"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">GST Reg Type</label>
                                    <input v-model="form.buyer_gst_registration_type" type="text" placeholder="e.g. Regular"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input v-model="form.buyer_email" type="email"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                                <input v-model="form.buyer_mobile" type="text"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <textarea v-model="form.buyer_address" rows="2"
                                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
                            </div>
                        </div>
                    </details>

                    <!-- ── Consignee Details (collapsible) ────────────────── -->
                    <details class="border border-gray-200 rounded-lg overflow-hidden">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50">
                            Consignee Details
                        </summary>
                        <div class="px-4 pb-4 pt-3 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Consignee Name</label>
                                    <input v-model="form.consignee_name" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Consignee GSTIN</label>
                                    <input v-model="form.consignee_gstin" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tally Group</label>
                                    <input v-model="form.consignee_tally_group" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Pin Code</label>
                                    <input v-model="form.consignee_pin_code" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                    <input v-model="form.consignee_state" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                    <input v-model="form.consignee_country" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">GST Reg Type</label>
                                <input v-model="form.consignee_gst_registration_type" type="text"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>
                    </details>

                    <!-- ── Dispatch & Shipping (collapsible) ──────────────── -->
                    <details class="border border-gray-200 rounded-lg overflow-hidden">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50">
                            Dispatch &amp; Shipping
                        </summary>
                        <div class="px-4 pb-4 pt-3 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Note No</label>
                                    <input v-model="form.delivery_note_no" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Note Date</label>
                                    <input v-model="form.delivery_note_date" type="text" placeholder="e.g. 20250401"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Dispatch Doc No</label>
                                    <input v-model="form.dispatch_doc_no" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Dispatch Through</label>
                                    <input v-model="form.dispatch_through" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Destination</label>
                                    <input v-model="form.destination" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Carrier Name</label>
                                    <input v-model="form.carrier_name" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">LR No</label>
                                    <input v-model="form.lr_no" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">LR Date</label>
                                    <input v-model="form.lr_date" type="text" placeholder="e.g. 20250401"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Motor Vehicle No</label>
                                    <input v-model="form.motor_vehicle_no" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                        </div>
                    </details>

                    <!-- ── Order Details (collapsible) ────────────────────── -->
                    <details class="border border-gray-200 rounded-lg overflow-hidden">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 select-none bg-gray-50">
                            Order Details
                        </summary>
                        <div class="px-4 pb-4 pt-3 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Order No</label>
                                    <input v-model="form.order_no" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Order Date</label>
                                    <input v-model="form.order_date" type="text" placeholder="e.g. 20250401"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Terms of Payment</label>
                                    <input v-model="form.terms_of_payment" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Terms of Delivery</label>
                                    <input v-model="form.terms_of_delivery" type="text"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Other References</label>
                                <input v-model="form.other_references" type="text"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>
                    </details>

                    <!-- ── Payroll notice ──────────────────────────────────── -->
                    <div v-if="isPayroll"
                         class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        Payroll and Attendance vouchers support basic fields only. Employee allocations must be managed in Tally directly.
                    </div>

                    <!-- ── Ledger Entries ──────────────────────────────────── -->
                    <div v-if="!isPayroll">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-700">Ledger Entries</h3>
                            <button type="button" @click="addLedger"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Entry</button>
                        </div>
                        <div v-for="(le, i) in form.ledger_entries" :key="i"
                             class="border border-gray-200 rounded-lg p-3 space-y-2 mb-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-500">Entry {{ i + 1 }}</span>
                                <button type="button" @click="removeLedger(i)"
                                        class="text-xs text-red-400 hover:text-red-600">Remove</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Ledger Name *</label>
                                    <input v-model="le.ledger_name" type="text"
                                           list="ledger-name-options"
                                           placeholder="e.g. Sales Account"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Ledger Group</label>
                                    <input v-model="le.ledger_group" type="text" placeholder="e.g. Sales Accounts"
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
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">IGST Rate</label>
                                    <input v-model="le.igst_rate" type="text" placeholder="e.g. 18"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">HSN Code</label>
                                    <input v-model="le.hsn_code" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">CESS Rate</label>
                                    <input v-model="le.cess_rate" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                        </div>
                        <p v-if="!form.ledger_entries.length" class="text-xs text-gray-400">No ledger entries. Click + Add Entry.</p>
                    </div>

                    <!-- ── Inventory Entries ───────────────────────────────── -->
                    <div v-if="showInventory">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-700">Inventory Entries</h3>
                            <button type="button" @click="addInventory"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Item</button>
                        </div>
                        <div v-for="(ie, i) in form.inventory_entries" :key="i"
                             class="border border-gray-200 rounded-lg p-3 space-y-2 mb-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-500">Item {{ i + 1 }}</span>
                                <button type="button" @click="removeInventory(i)"
                                        class="text-xs text-red-400 hover:text-red-600">Remove</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Stock Item *</label>
                                    <input v-model="ie.stock_item_name" type="text"
                                           list="stock-item-options"
                                           placeholder="e.g. Product A"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Item Code</label>
                                    <input v-model="ie.item_code" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Group Name</label>
                                    <input v-model="ie.group_name" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">HSN Code</label>
                                    <input v-model="ie.hsn_code" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Unit</label>
                                    <input v-model="ie.unit" type="text" placeholder="e.g. Nos"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Billed Qty</label>
                                    <input v-model="ie.billed_qty" type="number" step="0.0001" placeholder="0"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Actual Qty</label>
                                    <input v-model="ie.actual_qty" type="number" step="0.0001" placeholder="0"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Rate</label>
                                    <input v-model="ie.rate" type="number" step="0.01" placeholder="0.00"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Discount %</label>
                                    <input v-model="ie.discount_percent" type="number" step="0.01" placeholder="0"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">IGST %</label>
                                    <input v-model="ie.igst_rate" type="number" step="0.01" placeholder="0"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">CESS %</label>
                                    <input v-model="ie.cess_rate" type="number" step="0.01" placeholder="0"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Amount</label>
                                    <input v-model="ie.amount" type="number" step="0.01" placeholder="0.00"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Tax Amount</label>
                                    <input v-model="ie.tax_amount" type="number" step="0.01" placeholder="0.00"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">MRP</label>
                                    <input v-model="ie.mrp" type="number" step="0.01" placeholder="0.00"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Sales Ledger</label>
                                    <input v-model="ie.sales_ledger" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Godown</label>
                                    <input v-model="ie.godown_name" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Batch</label>
                                    <input v-model="ie.batch_name" type="text"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-0.5">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input v-model="ie.is_deemed_positive" type="checkbox"
                                           class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                    <span class="text-xs text-gray-600">Deemed Positive</span>
                                </label>
                            </div>
                        </div>
                        <p v-if="!form.inventory_entries.length" class="text-xs text-gray-400">No inventory items. Click + Add Item.</p>
                    </div>

                    <!-- ── Datalists ───────────────────────────────────────── -->
                    <datalist id="ledger-name-options">
                        <option v-for="n in ledgerNames" :key="n" :value="n" />
                    </datalist>
                    <datalist id="stock-item-options">
                        <option v-for="n in stockItemNames" :key="n" :value="n" />
                    </datalist>

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
