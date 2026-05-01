<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:             Object,
    items:              Array,
    stockGroupNames:    Array,
    stockCategoryNames: Array,
    unitNames:          Array,
    godownNames:        Array,
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
    igst_rate:         '',
    sgst_rate:         '',
    cgst_rate:         '',
    cess_rate:         '',
    mrp_rate:          '',
    opening_balance:   '',
    opening_rate:      '',
    opening_value:     '',
    closing_balance:   '',
    closing_rate:      '',
    closing_value:     '',
    batch_allocations: [],
})

function addAlias()         { form.aliases.push({ Alias: '' }) }
function removeAlias(i)     { form.aliases.splice(i, 1) }
function addPartNo()        { form.part_nos.push({ PartNo: '' }) }
function removePartNo(i)    { form.part_nos.splice(i, 1) }
function addBatchAlloc()    { form.batch_allocations.push({ GodownName: '', GodownID: '', BatchName: '', OpeningBalnace: '', Rate: '', OpeningValue: '' }) }
function removeBatchAlloc(i){ form.batch_allocations.splice(i, 1) }

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
    form.is_gst_applicable = item.is_gst_applicable !== null ? (item.is_gst_applicable ? '1' : '0') : ''
    form.taxability        = item.taxability ?? ''
    form.calculation_type  = item.calculation_type ?? ''
    form.hsn_code          = item.hsn_code ?? ''
    form.igst_rate         = item.igst_rate ?? ''
    form.sgst_rate         = item.sgst_rate ?? ''
    form.cgst_rate         = item.cgst_rate ?? ''
    form.cess_rate         = item.cess_rate ?? ''
    form.mrp_rate          = item.mrp_rate ?? ''
    form.opening_balance   = item.opening_balance ?? ''
    form.opening_rate      = item.opening_rate ?? ''
    form.opening_value     = item.opening_value ?? ''
    form.closing_balance   = item.closing_balance ?? ''
    form.closing_rate      = item.closing_rate ?? ''
    form.closing_value     = item.closing_value ?? ''
    form.batch_allocations = item.batch_allocations ? JSON.parse(JSON.stringify(item.batch_allocations)) : []
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
            <div class="relative z-50 w-full max-w-md bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Stock Item' : 'New Stock Item' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="e.g. Laptop 15 inch"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <!-- Description & Remarks -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="2" placeholder="e.g. High-speed lease line"
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea v-model="form.remarks" rows="2" placeholder="Internal notes"
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <!-- Aliases -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Aliases</label>
                            <button type="button" @click="addAlias"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                        <div v-for="(al, i) in form.aliases" :key="i" class="flex gap-2 mb-2">
                            <input v-model="al.Alias" type="text" placeholder="Alias name"
                                   class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <button type="button" @click="removeAlias(i)"
                                    class="text-xs text-red-400 hover:text-red-600 px-2">✕</button>
                        </div>
                        <p v-if="!form.aliases.length" class="text-xs text-gray-400">No aliases added.</p>
                    </div>

                    <!-- Part Nos -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Part Numbers</label>
                            <button type="button" @click="addPartNo"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                        <div v-for="(pn, i) in form.part_nos" :key="i" class="flex gap-2 mb-2">
                            <input v-model="pn.PartNo" type="text" placeholder="Part number"
                                   class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <button type="button" @click="removePartNo(i)"
                                    class="text-xs text-red-400 hover:text-red-600 px-2">✕</button>
                        </div>
                        <p v-if="!form.part_nos.length" class="text-xs text-gray-400">No part numbers added.</p>
                    </div>

                    <!-- Group & Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stock Group</label>
                        <select v-model="form.stock_group_name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="">— None —</option>
                            <option v-for="n in stockGroupNames" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select v-model="form.category_name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="">— None —</option>
                            <option v-for="n in stockCategoryNames" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </div>

                    <!-- Unit -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                            <select v-model="form.unit_name"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— None —</option>
                                <option v-for="n in unitNames" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Unit</label>
                            <select v-model="form.alternate_unit"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— None —</option>
                                <option v-for="n in unitNames" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Conversion</label>
                            <input v-model="form.conversion" type="number" step="0.0001" min="0" placeholder="0"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Denominator</label>
                            <input v-model="form.denominator" type="number" min="1" placeholder="1"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <!-- GST -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">GST Applicable</label>
                            <select v-model="form.is_gst_applicable"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option value="1">Applicable</option>
                                <option value="0">Not Applicable</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Taxability</label>
                            <select v-model="form.taxability"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option>Taxable</option>
                                <option>Non-Taxable</option>
                                <option>Exempt</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Calculation Type</label>
                            <select v-model="form.calculation_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option>On Value</option>
                                <option>On MRP Rate</option>
                                <option>Based on Qty</option>
                                <option>Fixed Amount</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">HSN Code</label>
                            <input v-model="form.hsn_code" type="text" placeholder="e.g. 8471"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <!-- GST Rates -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">GST Rates (%)</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">IGST</p>
                                <input v-model="form.igst_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">SGST</p>
                                <input v-model="form.sgst_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">CGST</p>
                                <input v-model="form.cgst_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">CESS</p>
                                <input v-model="form.cess_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>
                    </div>

                    <!-- MRP -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">MRP Rate</label>
                        <input v-model="form.mrp_rate" type="number" step="0.01" min="0" placeholder="0"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <!-- Opening -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Opening</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">Balance (Qty)</p>
                                <input v-model="form.opening_balance" type="number" step="0.0001" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">Rate</p>
                                <input v-model="form.opening_rate" type="number" step="0.01" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">Value</p>
                                <input v-model="form.opening_value" type="number" step="0.01" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Closing -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Closing</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">Balance (Qty)</p>
                                <input v-model="form.closing_balance" type="number" step="0.0001" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">Rate</p>
                                <input v-model="form.closing_rate" type="number" step="0.01" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">Value</p>
                                <input v-model="form.closing_value" type="number" step="0.01" placeholder="0"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Batch Allocations -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Batch Allocations (Godowns)</label>
                            <button type="button" @click="addBatchAlloc"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                        <div v-for="(ba, i) in form.batch_allocations" :key="i"
                             class="border border-gray-200 rounded-lg p-3 mb-2 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-500">Allocation {{ i + 1 }}</span>
                                <button type="button" @click="removeBatchAlloc(i)"
                                        class="text-xs text-red-400 hover:text-red-600">✕ Remove</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-400 mb-1">Godown</p>
                                    <select @change="selectGodown(ba, godownNames.find(g => g.name === $event.target.value))"
                                            :value="ba.GodownName"
                                            class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        <option value="">— Select Godown —</option>
                                        <option v-for="g in godownNames" :key="g.name" :value="g.name">{{ g.name }}</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-400 mb-1">Batch Name</p>
                                    <input v-model="ba.BatchName" type="text" placeholder="e.g. Batch-001"
                                           class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Opening Balance</p>
                                    <input v-model="ba.OpeningBalnace" type="number" step="0.0001" placeholder="0"
                                           class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Rate</p>
                                    <input v-model="ba.Rate" type="number" step="0.01" placeholder="0"
                                           class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-400 mb-1">Opening Value</p>
                                    <input v-model="ba.OpeningValue" type="number" step="0.01" placeholder="0"
                                           class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                </div>
                            </div>
                        </div>
                        <p v-if="!form.batch_allocations.length" class="text-xs text-gray-400">No batch allocations.</p>
                    </div>

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
