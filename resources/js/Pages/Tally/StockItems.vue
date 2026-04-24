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
    name:             '',
    stock_group_name: '',
    category_name:    '',
    unit_name:        '',
    hsn_code:         '',
    igst_rate:        '',
    sgst_rate:        '',
    cgst_rate:        '',
    cess_rate:        '',
    opening_balance:  '',
})

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(item) {
    form.name             = item.name
    form.stock_group_name = item.stock_group_name ?? ''
    form.category_name    = item.category_name ?? ''
    form.unit_name        = item.unit_name ?? ''
    form.hsn_code         = item.hsn_code ?? ''
    form.igst_rate        = item.igst_rate ?? ''
    form.sgst_rate        = item.sgst_rate ?? ''
    form.cgst_rate        = item.cgst_rate ?? ''
    form.cess_rate        = item.cess_rate ?? ''
    form.opening_balance  = item.opening_balance ?? ''
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="e.g. Laptop 15 inch"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stock Group</label>
                        <input v-model="form.stock_group_name" type="text"
                               list="si-group-options"
                               placeholder="e.g. Electronics"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <datalist id="si-group-options">
                            <option v-for="n in stockGroupNames" :key="n" :value="n" />
                        </datalist>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <input v-model="form.category_name" type="text"
                               list="si-cat-options"
                               placeholder="e.g. Accessories"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <datalist id="si-cat-options">
                            <option v-for="n in stockCategoryNames" :key="n" :value="n" />
                        </datalist>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                            <input v-model="form.unit_name" type="text" placeholder="e.g. Nos"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">HSN Code</label>
                            <input v-model="form.hsn_code" type="text" placeholder="e.g. 8471"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

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

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Opening Balance (Qty)</label>
                        <input v-model="form.opening_balance" type="number" step="0.0001" min="0" placeholder="0"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
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
