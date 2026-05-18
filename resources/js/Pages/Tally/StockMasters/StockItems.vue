<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:              Object,
    items:               Array,
    stockGroupNames:     Array,
    stockCategoryNames:  Array,
    unitNames:           Array,
    godownNames:         Array,
    salesLedgerNames:    Array,
    purchaseLedgerNames: Array,
})

const canManage = hasPermission('integrations.manage')

// ── List ───────────────────────────────────────────────────────────────────────
const search      = ref('')
const groupFilter = ref('all')

const stockGroups = computed(() => {
    const set = new Set(props.items.map(i => i.stock_group_name).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.items
    if (groupFilter.value !== 'all') {
        list = list.filter(i => i.stock_group_name === groupFilter.value)
    }
    const q = search.value.toLowerCase()
    if (q) {
        list = list.filter(i =>
            i.name.toLowerCase().includes(q) ||
            (i.hsn_code ?? '').toLowerCase().includes(q) ||
            (i.category_name ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

function formatAmount(v) {
    if (v === null || v === undefined) return '—'
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(v)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString()
}

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending', cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',   cls: 'bg-gray-100 text-gray-400'   }
}

// ── CRUD ───────────────────────────────────────────────────────────────────────
const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    name:              '',
    description:       '',
    remarks:           '',
    aliases:           [],
    part_nos:          [],
    stock_group_name:  '',
    category_name:     '',
    unit_name:         '',
    alternate_unit:    '',
    conversion:        '',
    denominator:       '',
    is_gst_applicable: '',
    taxability:        '',
    calculation_type:  '',
    hsn_code:          '',
    type_of_supply:    '',
    igst_rate:         '',
    sgst_rate:         '',
    cgst_rate:         '',
    cess_rate:         '',
    mrp_rate:          '',
    inclusive_tax:     false,
    modify_mrp_rate:   false,
    calc_on_mrp:       false,
    mrp_incl_of_tax:   false,
    costing_method:    '',
    valuation_method:  '',
    sales_ledger:      '',
    purchase_ledger:   '',
    opening_balance:   '',
    opening_rate:      '',
    opening_value:     '',
    closing_balance:   '',
    closing_rate:      '',
    closing_value:     '',
    is_batch_wise:     false,
    is_perishable:     false,
    has_mfg_date:      false,
    allow_expired_items: false,
    ignore_batches:    false,
    ignore_godowns:    false,
    ignore_neg_stock:  false,
    is_cost_centres_on:  false,
    is_cost_tracking_on: false,
    batch_allocations: [],
})

function addAlias()         { form.aliases.push({ Alias: '' }) }
function removeAlias(i)     { form.aliases.splice(i, 1) }
function addPartNo()        { form.part_nos.push({ PartNo: '' }) }
function removePartNo(i)    { form.part_nos.splice(i, 1) }
function addBatchAlloc()    { form.batch_allocations.push({ GodownName: '', GodownID: '', BatchName: '', MFDON: '', ExpiryPeriod: '', OpeningBalance: '', Rate: '', OpeningValue: 0 }) }
function removeBatchAlloc(i){ form.batch_allocations.splice(i, 1) }

function calcOpeningValue(ba) {
    const qty  = parseFloat(ba.OpeningBalance) || 0
    const rate = parseFloat(ba.Rate) || 0
    ba.OpeningValue = parseFloat((qty * rate).toFixed(2))
}

function recalcOpeningValue() {
    const qty  = parseFloat(form.opening_balance) || 0
    const rate = parseFloat(form.opening_rate)    || 0
    if (qty && rate) form.opening_value = parseFloat((qty * rate).toFixed(2))
}

function recalcClosingValue() {
    const qty  = parseFloat(form.closing_balance) || 0
    const rate = parseFloat(form.closing_rate)    || 0
    if (qty && rate) form.closing_value = parseFloat((qty * rate).toFixed(2))
}

// SGST/CGST auto-fill from IGST ÷ 2
watch(() => form.igst_rate, (val) => {
    const half = parseFloat((parseFloat(val) / 2).toFixed(2)) || ''
    form.sgst_rate = half
    form.cgst_rate = half
})

// Conditional visibility
const showGST         = computed(() => form.is_gst_applicable === '1' || form.is_gst_applicable === 'Applicable')
const showAltUnit     = computed(() => !!form.alternate_unit)
const showBatchFields = computed(() => form.is_batch_wise)
const showMfgDate     = computed(() => showBatchFields.value && form.has_mfg_date)
const showExpiry      = computed(() => showBatchFields.value && form.is_perishable)

const totalBatchOpening = computed(() =>
    form.batch_allocations.reduce((sum, ba) => sum + (parseFloat(ba.OpeningBalance) || 0), 0)
)

const remainingOpening = computed(() =>
    (parseFloat(form.opening_balance) || 0) - totalBatchOpening.value
)

const batchOpeningError = computed(() => {
    if (totalBatchOpening.value > (parseFloat(form.opening_balance) || 0)) {
        return `Total batch opening balance (${totalBatchOpening.value}) exceeds opening balance (${form.opening_balance}).`
    }
    return null
})

function maxForBatch(i) {
    const others = form.batch_allocations
        .reduce((sum, ba, j) => j === i ? sum : sum + (parseFloat(ba.OpeningBalance) || 0), 0)
    return (parseFloat(form.opening_balance) || 0) - others
}

function clampBatchOpening(ba, i) {
    calcOpeningValue(ba)
    const limit = maxForBatch(i)
    if ((parseFloat(ba.OpeningBalance) || 0) > limit) {
        ba.OpeningBalance = limit
        calcOpeningValue(ba)
    }
}

function selectGodown(ba, godown) {
    if (godown) {
        ba.GodownName = godown.name
        ba.GodownID   = godown.tally_id ?? 0
    } else {
        ba.GodownName = ''
        ba.GodownID   = ''
    }
}

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(item) {
    form.name              = item.name
    form.description       = item.description ?? ''
    form.remarks           = item.remarks ?? ''
    form.aliases           = item.aliases ? JSON.parse(JSON.stringify(item.aliases)) : []
    form.part_nos          = item.part_nos ? JSON.parse(JSON.stringify(item.part_nos)) : []
    form.stock_group_name  = item.stock_group_name ?? ''
    form.category_name     = item.category_name ?? ''
    form.unit_name         = item.unit_name ?? ''
    form.alternate_unit    = item.alternate_unit ?? ''
    form.conversion        = item.conversion ?? ''
    form.denominator       = item.denominator ?? ''
    form.is_gst_applicable   = item.is_gst_applicable !== null ? (item.is_gst_applicable ? '1' : '0') : ''
    form.taxability          = item.taxability ?? ''
    form.calculation_type    = item.calculation_type ?? ''
    form.hsn_code            = item.hsn_code ?? ''
    form.type_of_supply      = item.type_of_supply ?? ''
    form.igst_rate           = item.igst_rate ?? ''
    form.sgst_rate           = item.sgst_rate ?? ''
    form.cgst_rate           = item.cgst_rate ?? ''
    form.cess_rate           = item.cess_rate ?? ''
    form.mrp_rate            = item.mrp_rate ?? ''
    form.inclusive_tax       = !!item.inclusive_tax
    form.modify_mrp_rate     = !!item.modify_mrp_rate
    form.calc_on_mrp         = !!item.calc_on_mrp
    form.mrp_incl_of_tax     = !!item.mrp_incl_of_tax
    form.costing_method      = item.costing_method ?? ''
    form.valuation_method    = item.valuation_method ?? ''
    form.sales_ledger        = item.sales_ledger ?? ''
    form.purchase_ledger     = item.purchase_ledger ?? ''
    form.opening_balance     = item.opening_balance ?? ''
    form.opening_rate        = item.opening_rate ?? ''
    form.opening_value       = item.opening_value ?? ''
    form.closing_balance     = item.closing_balance ?? ''
    form.closing_rate        = item.closing_rate ?? ''
    form.closing_value       = item.closing_value ?? ''
    form.is_batch_wise       = !!item.is_batch_wise
    form.is_perishable       = !!item.is_perishable
    form.has_mfg_date        = !!item.has_mfg_date
    form.allow_expired_items = !!item.allow_expired_items
    form.ignore_batches      = !!item.ignore_batches
    form.ignore_godowns      = !!item.ignore_godowns
    form.ignore_neg_stock    = !!item.ignore_neg_stock
    form.is_cost_centres_on  = !!item.is_cost_centres_on
    form.is_cost_tracking_on = !!item.is_cost_tracking_on
    form.batch_allocations   = item.batch_allocations ? JSON.parse(JSON.stringify(item.batch_allocations)) : []
    form.clearErrors()
    modal.value = item
}

function closeModal() {
    modal.value = null
    form.reset()
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.stock-items.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.stock-items.update', { tenant: props.tenant.id, stockItem: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(item) {
    const msg = item.tally_id
        ? `Mark "${item.name}" inactive and queue deletion in Tally?`
        : `Delete "${item.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.stock-items.destroy', { tenant: props.tenant.id, stockItem: item.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Stock Items</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ items.length }} items</p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Item
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

                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search items…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />

                    <select v-model="groupFilter"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="all">All Groups</option>
                        <option v-for="g in stockGroups.slice(1)" :key="g" :value="g">{{ g }}</option>
                    </select>

                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-3">Item Name</div>
                        <div class="col-span-2">Group / Category</div>
                        <div class="col-span-1 text-center">Unit</div>
                        <div class="col-span-1 text-center">IGST %</div>
                        <div class="col-span-1 text-right">MRP</div>
                        <div class="col-span-1 text-right">Closing Qty</div>
                        <div class="col-span-1 text-center">Status</div>
                        <div class="col-span-1 text-center">Tally</div>
                        <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="item in filtered" :key="item.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-3">
                            <p class="text-sm font-medium text-gray-900">{{ item.name }}</p>
                            <p v-if="item.mapped_product?.name" class="text-xs text-gray-400 mt-0.5 truncate">
                                → {{ item.mapped_product.name }}
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-gray-600 truncate">{{ item.stock_group_name ?? '—' }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ item.category_name ?? '' }}</p>
                        </div>
                        <div class="col-span-1 text-center text-sm text-gray-500">{{ item.unit_name ?? '—' }}</div>
                        <div class="col-span-1 text-center text-sm text-gray-600">{{ item.igst_rate ?? '—' }}</div>
                        <div class="col-span-1 text-right text-sm text-gray-700">{{ formatAmount(item.mrp_rate) }}</div>
                        <div class="col-span-1 text-right text-sm text-gray-700">{{ item.closing_balance ?? '—' }}</div>
                        <div class="col-span-1 text-center">
                            <span :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ item.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="syncBadge(item.sync_status).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ syncBadge(item.sync_status).label }}
                            </span>
                        </div>
                        <div class="col-span-1 text-right" v-if="canManage">
                            <button @click="openEdit(item)"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                            <button @click="destroy(item)"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No stock items found.</p>
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
                        {{ isEditing ? 'Edit Stock Item' : 'New Stock Item' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto divide-y divide-gray-100">

                    <!-- ── Basic Info ──────────────────────────────────────── -->
                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="form.name" type="text" placeholder="e.g. Laptop 15 inch" class="tally-field" />
                            <p v-if="form.errors.name" class="mt-0.5 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Under <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <select v-model="form.stock_group_name" class="tally-field">
                                <option value="">— Select Group —</option>
                                <option v-for="n in stockGroupNames" :key="n" :value="n">{{ n }}</option>
                            </select>
                            <p v-if="form.errors.stock_group_name" class="mt-0.5 text-xs text-red-500">{{ form.errors.stock_group_name }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Category</span>
                        <select v-model="form.category_name" class="tally-input tally-field">
                            <option value="">Not Applicable</option>
                            <option v-for="n in stockCategoryNames" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Units <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <select v-model="form.unit_name" class="tally-field">
                                <option value="">— Select Unit —</option>
                                <option v-for="n in unitNames" :key="n" :value="n">{{ n }}</option>
                            </select>
                            <p v-if="form.errors.unit_name" class="mt-0.5 text-xs text-red-500">{{ form.errors.unit_name }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Alternate Units</span>
                        <select v-model="form.alternate_unit" class="tally-input tally-field">
                            <option value="">Not Applicable</option>
                            <option v-for="n in unitNames" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </div>
                    <template v-if="showAltUnit">
                        <div class="tally-row">
                            <span class="tally-label">Conversion</span>
                            <div class="tally-input flex items-center gap-2 text-sm">
                                <input v-model="form.denominator" type="number" min="1" placeholder="1"
                                       class="tally-field w-16 text-center" />
                                <span class="text-gray-500 font-medium">{{ form.unit_name || 'unit' }}</span>
                                <span class="text-gray-400">=</span>
                                <input v-model="form.conversion" type="number" step="0.0001" min="0" placeholder="1"
                                       class="tally-field w-16 text-center" />
                                <span class="text-gray-500 font-medium">{{ form.alternate_unit || 'alt unit' }}</span>
                            </div>
                        </div>
                    </template>
                    <div class="tally-row">
                        <span class="tally-label">MRP Rate</span>
                        <input v-model="form.mrp_rate" type="number" step="0.01" min="0" placeholder="0"
                               class="tally-input tally-field w-40" />
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">MRP Incl. of Tax</span>
                        <label class="tally-input flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.mrp_incl_of_tax" class="rounded border-gray-300 text-violet-600" />
                            <span class="text-sm text-gray-600">MRP is inclusive of tax</span>
                        </label>
                    </div>

                    <!-- ── GST Details ─────────────────────────────────────── -->
                    <div class="tally-section-header">GST Details</div>
                    <div class="tally-row">
                        <span class="tally-label">GST Applicable</span>
                        <select v-model="form.is_gst_applicable" class="tally-input tally-field w-48">
                            <option value="">— Select —</option>
                            <option value="1">Applicable</option>
                            <option value="0">Not Applicable</option>
                        </select>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Type of Supply</span>
                        <select v-model="form.type_of_supply" class="tally-input tally-field w-48">
                            <option value="">— Select —</option>
                            <option>Goods</option>
                            <option>Services</option>
                            <option>Capital Goods</option>
                        </select>
                    </div>
                    <template v-if="showGST">
                    <div class="tally-row">
                        <span class="tally-label">HSN / SAC</span>
                        <input v-model="form.hsn_code" type="text" placeholder="e.g. 8471"
                               class="tally-input tally-field w-48" />
                    </div>
                        <div class="tally-row">
                            <span class="tally-label">Taxability</span>
                            <select v-model="form.taxability" class="tally-input tally-field w-48">
                                <option value="">— Select —</option>
                                <option>Taxable</option>
                                <option>Nil Rated</option>
                                <option>Exempt</option>
                                <option>Non-GST Supply</option>
                            </select>
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Calculation Type</span>
                            <select v-model="form.calculation_type" class="tally-input tally-field w-48">
                                <option value="">— Select —</option>
                                <option>On Value</option>
                                <option>On MRP Rate</option>
                                <option>Based on Qty</option>
                                <option>Fixed Amount</option>
                            </select>
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">IGST Rate %</span>
                            <input v-model="form.igst_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                   class="tally-input tally-field w-28" />
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">SGST Rate % <span class="text-gray-300 text-xs">(auto)</span></span>
                            <input v-model="form.sgst_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                   class="tally-input tally-field w-28" />
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">CGST Rate % <span class="text-gray-300 text-xs">(auto)</span></span>
                            <input v-model="form.cgst_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                   class="tally-input tally-field w-28" />
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">CESS Rate %</span>
                            <input v-model="form.cess_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                   class="tally-input tally-field w-28" />
                        </div>
                    </template>

                    <!-- ── Inventory Behaviour ─────────────────────────────── -->
                    <div class="tally-section-header">Inventory Behaviour</div>
                    <div class="tally-row">
                        <span class="tally-label">Costing Method</span>
                        <select v-model="form.costing_method" class="tally-input tally-field w-48">
                            <option value="">— Default —</option>
                            <option>Avg. Cost</option>
                            <option>FIFO</option>
                            <option>LIFO Annual</option>
                            <option>LIFO Perpetual</option>
                            <option>Standard Cost</option>
                        </select>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Valuation Method</span>
                        <select v-model="form.valuation_method" class="tally-input tally-field w-48">
                            <option value="">— Default —</option>
                            <option>Avg. Price</option>
                            <option>FIFO</option>
                            <option>LIFO Annual</option>
                            <option>LIFO Perpetual</option>
                            <option>Standard Price</option>
                            <option>At Zero Price</option>
                        </select>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Batch-wise</span>
                        <label class="tally-input flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_batch_wise" class="rounded border-gray-300 text-violet-600" />
                            <span class="text-sm text-gray-600">Track batches / lots</span>
                        </label>
                    </div>
                    <template v-if="showBatchFields">
                        <div class="tally-row">
                            <span class="tally-label">Perishable</span>
                            <label class="tally-input flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.is_perishable" class="rounded border-gray-300 text-violet-600" />
                                <span class="text-sm text-gray-600">Has expiry date</span>
                            </label>
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Mfg Date</span>
                            <label class="tally-input flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.has_mfg_date" class="rounded border-gray-300 text-violet-600" />
                                <span class="text-sm text-gray-600">Track manufacturing date</span>
                            </label>
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Allow Expired</span>
                            <label class="tally-input flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.allow_expired_items" class="rounded border-gray-300 text-violet-600" />
                                <span class="text-sm text-gray-600">Allow use of expired batches</span>
                            </label>
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Ignore Batches</span>
                            <label class="tally-input flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.ignore_batches" class="rounded border-gray-300 text-violet-600" />
                                <span class="text-sm text-gray-600">Skip batch entry in vouchers</span>
                            </label>
                        </div>
                    </template>
                    <div class="tally-row">
                        <span class="tally-label">Ignore Godowns</span>
                        <label class="tally-input flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.ignore_godowns" class="rounded border-gray-300 text-violet-600" />
                            <span class="text-sm text-gray-600">Skip godown entry in vouchers</span>
                        </label>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Allow Negative Stock</span>
                        <label class="tally-input flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.ignore_neg_stock" class="rounded border-gray-300 text-violet-600" />
                            <span class="text-sm text-gray-600">Allow negative stock balance</span>
                        </label>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Cost Tracking</span>
                        <label class="tally-input flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_cost_tracking_on" class="rounded border-gray-300 text-violet-600" />
                            <span class="text-sm text-gray-600">Enable cost tracking</span>
                        </label>
                    </div>

                    <!-- ── Default Ledgers ─────────────────────────────────── -->
                    <div class="tally-section-header">Default Ledgers</div>
                    <div class="tally-row">
                        <span class="tally-label">Sales Ledger</span>
                        <div class="tally-input">
                            <input v-model="form.sales_ledger" type="text"
                                   list="sales-ledger-list"
                                   placeholder="— Select or type —"
                                   class="tally-field" />
                            <datalist id="sales-ledger-list">
                                <option v-for="n in salesLedgerNames" :key="n" :value="n" />
                            </datalist>
                            <p class="mt-0.5 text-xs text-gray-400">Sales Accounts group from Tally</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Purchase Ledger</span>
                        <div class="tally-input">
                            <input v-model="form.purchase_ledger" type="text"
                                   list="purchase-ledger-list"
                                   placeholder="— Select or type —"
                                   class="tally-field" />
                            <datalist id="purchase-ledger-list">
                                <option v-for="n in purchaseLedgerNames" :key="n" :value="n" />
                            </datalist>
                            <p class="mt-0.5 text-xs text-gray-400">Purchase Accounts group from Tally</p>
                        </div>
                    </div>

                    <!-- ── Opening Balance ─────────────────────────────────── -->
                    <div class="tally-section-header">Opening Balance</div>
                    <div class="tally-row">
                        <span class="tally-label">Quantity</span>
                        <input v-model="form.opening_balance" type="number" step="0.0001" placeholder="0"
                               @input="recalcOpeningValue"
                               class="tally-input tally-field w-36" />
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Rate</span>
                        <input v-model="form.opening_rate" type="number" step="0.01" placeholder="0.00"
                               @input="recalcOpeningValue"
                               class="tally-input tally-field w-36" />
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Value <span class="text-gray-300 text-xs">(auto)</span></span>
                        <input v-model="form.opening_value" type="number" step="0.01" placeholder="0.00"
                               class="tally-input tally-field w-36" />
                    </div>

                    <!-- ── Closing Balance ─────────────────────────────────── -->
                    <div class="tally-section-header">Closing Balance</div>
                    <div class="tally-row">
                        <span class="tally-label">Quantity</span>
                        <input v-model="form.closing_balance" type="number" step="0.0001" placeholder="0"
                               @input="recalcClosingValue"
                               class="tally-input tally-field w-36" />
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Rate</span>
                        <input v-model="form.closing_rate" type="number" step="0.01" placeholder="0.00"
                               @input="recalcClosingValue"
                               class="tally-input tally-field w-36" />
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Value <span class="text-gray-300 text-xs">(auto)</span></span>
                        <input v-model="form.closing_value" type="number" step="0.01" placeholder="0.00"
                               class="tally-input tally-field w-36" />
                    </div>

                    <!-- ── Godown / Batch Allocations ──────────────────── -->
                    <div class="tally-section-header flex items-center justify-between">
                        <span>Godown / Batch Allocations</span>
                        <div class="flex items-center gap-3 normal-case tracking-normal font-normal">
                            <span v-if="form.opening_balance" class="text-xs"
                                  :class="remainingOpening < 0 ? 'text-red-500 font-semibold' : 'text-violet-600'">
                                Remaining: {{ remainingOpening }}
                            </span>
                            <button type="button" @click="addBatchAlloc"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                    </div>

                    <div v-for="(ba, i) in form.batch_allocations" :key="i" class="divide-y divide-gray-100">
                        <div class="tally-row bg-violet-50/40">
                            <span class="tally-label text-violet-700 font-semibold">Allocation {{ i + 1 }}</span>
                            <button type="button" @click="removeBatchAlloc(i)"
                                    class="tally-input text-xs text-red-400 hover:text-red-600">✕ Remove</button>
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Godown</span>
                            <select @change="selectGodown(ba, godownNames.find(g => g.name === $event.target.value))"
                                    :value="ba.GodownName" class="tally-input tally-field">
                                <option value="">— Select Godown —</option>
                                <option v-for="g in godownNames" :key="g.name" :value="g.name">{{ g.name }}</option>
                            </select>
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Batch Name</span>
                            <input v-model="ba.BatchName" type="text" placeholder="e.g. Batch-001"
                                   class="tally-input tally-field" />
                        </div>
                        <div v-if="showMfgDate" class="tally-row">
                            <span class="tally-label">Mfg Date</span>
                            <input v-model="ba.MFDON" type="text" placeholder="YYYYMMDD"
                                   class="tally-input tally-field w-40" />
                        </div>
                        <div v-if="showExpiry" class="tally-row">
                            <span class="tally-label">Expiry</span>
                            <input v-model="ba.ExpiryPeriod" type="text" placeholder="e.g. 31-Mar-27"
                                   class="tally-input tally-field w-40" />
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">
                                Opening Qty
                                <span class="text-gray-300 text-xs">(max {{ maxForBatch(i) }})</span>
                            </span>
                            <input v-model="ba.OpeningBalance" @input="clampBatchOpening(ba, i)"
                                   type="number" step="0.0001" min="0" :max="maxForBatch(i)" placeholder="0"
                                   class="tally-input tally-field w-36" />
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Rate</span>
                            <input v-model="ba.Rate" @input="calcOpeningValue(ba)"
                                   type="number" step="0.01" placeholder="0"
                                   class="tally-input tally-field w-36" />
                        </div>
                        <div class="tally-row">
                            <span class="tally-label">Value <span class="text-gray-300 text-xs">(auto)</span></span>
                            <input :value="ba.OpeningValue" readonly
                                   class="tally-input tally-field w-36 bg-gray-50 text-gray-400 cursor-not-allowed" />
                        </div>
                    </div>

                    <div v-if="batchOpeningError" class="px-4 py-2 bg-red-50 text-xs text-red-600">
                        {{ batchOpeningError }}
                    </div>
                    <div v-if="!form.batch_allocations.length" class="tally-row text-xs text-gray-400 italic">
                        <span class="tally-label"></span>
                        <span class="tally-input">No godown allocations.</span>
                    </div>

                    <!-- ── Additional Info ─────────────────────────────────── -->
                    <div class="tally-section-header">Additional Info</div>
                    <div class="tally-row">
                        <span class="tally-label">Description</span>
                        <textarea v-model="form.description" rows="2" placeholder="Optional description"
                                  class="tally-input tally-field resize-none" />
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Remarks</span>
                        <textarea v-model="form.remarks" rows="2" placeholder="Internal notes"
                                  class="tally-input tally-field resize-none" />
                    </div>

                    <!-- Aliases -->
                    <div class="tally-row items-start">
                        <span class="tally-label pt-1">Aliases</span>
                        <div class="tally-input space-y-1.5">
                            <div v-for="(al, i) in form.aliases" :key="i" class="flex gap-2">
                                <input v-model="al.Alias" type="text" placeholder="Alias name"
                                       class="flex-1 tally-field" />
                                <button type="button" @click="removeAlias(i)"
                                        class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
                            </div>
                            <button type="button" @click="addAlias"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Alias</button>
                        </div>
                    </div>

                    <!-- Part Numbers -->
                    <div class="tally-row items-start">
                        <span class="tally-label pt-1">Part Numbers</span>
                        <div class="tally-input space-y-1.5">
                            <div v-for="(pn, i) in form.part_nos" :key="i" class="flex gap-2">
                                <input v-model="pn.PartNo" type="text" placeholder="Part number"
                                       class="flex-1 tally-field" />
                                <button type="button" @click="removePartNo(i)"
                                        class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
                            </div>
                            <button type="button" @click="addPartNo"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Part No</button>
                        </div>
                    </div>

                    <!-- ── Actions ─────────────────────────────────────────── -->
                    <div class="px-4 py-4 flex gap-3">
                        <button type="submit" :disabled="form.processing || !!batchOpeningError"
                                class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditing ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeModal"
                                class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.tally-row {
    @apply flex items-center px-4 py-2.5 border-b border-gray-100 last:border-0;
}
.tally-label {
    @apply w-44 flex-shrink-0 text-sm text-gray-600;
}
.tally-input {
    @apply flex-1;
}
.tally-field {
    @apply w-full rounded border border-gray-300 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent;
}
.tally-section-header {
    @apply flex items-center px-4 py-2 bg-violet-50 border-y border-violet-100 text-xs font-semibold text-violet-700 uppercase tracking-wider;
}
</style>
