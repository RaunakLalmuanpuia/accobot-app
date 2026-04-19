<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant:  Object,
    ledgers: Array,
})

const search      = ref('')
const groupFilter = ref('all')

const groups = computed(() => {
    const set = new Set(props.ledgers.map(l => l.group_name).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.ledgers
    if (groupFilter.value !== 'all') {
        list = list.filter(l => l.group_name === groupFilter.value)
    }
    const q = search.value.toLowerCase()
    if (q) {
        list = list.filter(l =>
            l.ledger_name.toLowerCase().includes(q) ||
            (l.gstin_number ?? '').toLowerCase().includes(q) ||
            (l.state_name ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

function mappingBadge(ledger) {
    if (ledger.mapped_client_id) return { label: 'Client', cls: 'bg-blue-100 text-blue-700' }
    if (ledger.mapped_vendor_id) return { label: 'Vendor', cls: 'bg-orange-100 text-orange-700' }
    return null
}

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
                    <h1 class="text-xl font-semibold text-gray-900">Ledgers</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ ledgers.length }} ledgers synced from Tally</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('tally.ledger-groups.index', { tenant: tenant.id })"
                          class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                        Ledger Groups
                    </Link>
                    <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search ledgers…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />

                    <select v-model="groupFilter"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="all">All Groups</option>
                        <option v-for="g in groups.slice(1)" :key="g" :value="g">{{ g }}</option>
                    </select>

                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-3">Ledger Name</div>
                        <div class="col-span-2">Group</div>
                        <div class="col-span-2">GSTIN</div>
                        <div class="col-span-1">State</div>
                        <div class="col-span-2 text-right">Opening Bal.</div>
                        <div class="col-span-1 text-center">Mapped</div>
                        <div class="col-span-1 text-center">Status</div>
                    </div>

                    <div v-for="ledger in filtered" :key="ledger.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-3">
                            <p class="text-sm font-medium text-gray-900">{{ ledger.ledger_name }}</p>
                            <p v-if="ledger.mapped_client?.name || ledger.mapped_vendor?.name"
                               class="text-xs text-gray-400 truncate mt-0.5">
                                {{ ledger.mapped_client?.name ?? ledger.mapped_vendor?.name }}
                            </p>
                        </div>
                        <div class="col-span-2 text-sm text-gray-500 truncate">{{ ledger.group_name ?? '—' }}</div>
                        <div class="col-span-2 text-xs text-gray-500 font-mono">{{ ledger.gstin_number ?? '—' }}</div>
                        <div class="col-span-1 text-sm text-gray-500 truncate">{{ ledger.state_name ?? '—' }}</div>
                        <div class="col-span-2 text-right">
                            <span v-if="ledger.opening_balance" class="text-sm text-gray-700 font-medium">
                                {{ formatAmount(ledger.opening_balance) }}
                                <span class="text-xs text-gray-400 ml-0.5">{{ ledger.opening_balance_type }}</span>
                            </span>
                            <span v-else class="text-sm text-gray-400">—</span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span v-if="mappingBadge(ledger)"
                                  :class="mappingBadge(ledger).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ mappingBadge(ledger).label }}
                            </span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="ledger.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ ledger.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No ledgers found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
