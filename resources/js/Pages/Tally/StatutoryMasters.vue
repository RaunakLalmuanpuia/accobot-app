<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant: Object,
    items:  Array,
})

const search     = ref('')
const typeFilter = ref('all')

const types = computed(() => {
    const set = new Set(props.items.map(i => i.statutory_type).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.items
    if (typeFilter.value !== 'all') {
        list = list.filter(i => i.statutory_type === typeFilter.value)
    }
    const q = search.value.toLowerCase()
    if (q) {
        list = list.filter(i =>
            i.name.toLowerCase().includes(q) ||
            (i.registration_number ?? '').toLowerCase().includes(q) ||
            (i.registration_type ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

const typeColors = {
    GST: 'bg-blue-100 text-blue-700',
    TDS: 'bg-amber-100 text-amber-700',
    TCS: 'bg-orange-100 text-orange-700',
    PF:  'bg-green-100 text-green-700',
    ESI: 'bg-teal-100 text-teal-700',
    PT:  'bg-purple-100 text-purple-700',
}

function typeColor(type) {
    return typeColors[type] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Statutory Masters</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ items.length }} statutory records synced from Tally</p>
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
                    <input v-model="search" type="text" placeholder="Search statutory masters…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />

                    <select v-model="typeFilter"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="all">All Types</option>
                        <option v-for="t in types.slice(1)" :key="t" :value="t">{{ t }}</option>
                    </select>

                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-1 text-center">Type</div>
                        <div class="col-span-3">Name</div>
                        <div class="col-span-3">Registration Number</div>
                        <div class="col-span-2">Registration Type</div>
                        <div class="col-span-1 text-center">State</div>
                        <div class="col-span-1 text-center">Applicable From</div>
                        <div class="col-span-1 text-center">Status</div>
                    </div>

                    <div v-for="item in filtered" :key="item.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-1 text-center">
                            <span :class="typeColor(item.statutory_type)"
                                  class="text-xs px-2 py-0.5 rounded-full font-semibold">
                                {{ item.statutory_type ?? '—' }}
                            </span>
                        </div>
                        <div class="col-span-3">
                            <p class="text-sm font-medium text-gray-900">{{ item.name }}</p>
                            <p v-if="item.pan || item.tan" class="text-xs text-gray-400 mt-0.5 font-mono">
                                {{ item.pan ?? item.tan }}
                            </p>
                        </div>
                        <div class="col-span-3 text-sm text-gray-600 font-mono">{{ item.registration_number ?? '—' }}</div>
                        <div class="col-span-2 text-sm text-gray-500">{{ item.registration_type ?? '—' }}</div>
                        <div class="col-span-1 text-center text-sm text-gray-500">{{ item.state_code ?? '—' }}</div>
                        <div class="col-span-1 text-center text-xs text-gray-500">{{ formatDate(item.applicable_from) }}</div>
                        <div class="col-span-1 text-center">
                            <span :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ item.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No statutory masters found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
