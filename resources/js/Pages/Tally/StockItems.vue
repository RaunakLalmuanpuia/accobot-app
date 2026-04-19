<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant: Object,
    items:  Array,
})

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
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Stock Items</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ items.length }} items synced from Tally</p>
                </div>
                <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                      class="text-sm text-gray-500 hover:text-gray-700">
                    ← Back to Sync
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

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
                        <div class="col-span-1">HSN</div>
                        <div class="col-span-1 text-center">Unit</div>
                        <div class="col-span-1 text-center">IGST %</div>
                        <div class="col-span-1 text-right">MRP</div>
                        <div class="col-span-1 text-right">Closing Qty</div>
                        <div class="col-span-1 text-center">Mapped</div>
                        <div class="col-span-1 text-center">Status</div>
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
                        <div class="col-span-1 text-xs text-gray-500 font-mono">{{ item.hsn_code ?? '—' }}</div>
                        <div class="col-span-1 text-center text-sm text-gray-500">{{ item.unit_name ?? '—' }}</div>
                        <div class="col-span-1 text-center text-sm text-gray-600">{{ item.igst_rate ?? '—' }}</div>
                        <div class="col-span-1 text-right text-sm text-gray-700">{{ formatAmount(item.mrp_rate) }}</div>
                        <div class="col-span-1 text-right text-sm text-gray-700">{{ item.closing_balance ?? '—' }}</div>
                        <div class="col-span-1 text-center">
                            <span v-if="item.mapped_product_id"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium bg-violet-100 text-violet-700">
                                Product
                            </span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ item.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No stock items found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
