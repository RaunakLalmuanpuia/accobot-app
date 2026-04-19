<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant: Object,
    groups: Array,
})

const search = ref('')

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return props.groups
    return props.groups.filter(g =>
        g.name.toLowerCase().includes(q) ||
        (g.nature_of_group ?? '').toLowerCase().includes(q)
    )
})

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
                    <h1 class="text-xl font-semibold text-gray-900">Ledger Groups</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ groups.length }} groups synced from Tally</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('tally.ledgers.index', { tenant: tenant.id })"
                          class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                        View Ledgers →
                    </Link>
                    <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-4">

                <div class="flex items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search groups…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-4">Name</div>
                        <div class="col-span-2">Under</div>
                        <div class="col-span-2">Nature</div>
                        <div class="col-span-1 text-center">Revenue</div>
                        <div class="col-span-1 text-center">Addable</div>
                        <div class="col-span-1 text-center">Status</div>
                        <div class="col-span-1">Last Synced</div>
                    </div>

                    <div v-for="group in filtered" :key="group.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-4">
                            <p class="text-sm font-medium text-gray-900">{{ group.name }}</p>
                        </div>
                        <div class="col-span-2 text-sm text-gray-500 truncate">{{ group.under_name ?? '—' }}</div>
                        <div class="col-span-2 text-sm text-gray-500">{{ group.nature_of_group ?? '—' }}</div>
                        <div class="col-span-1 text-center">
                            <span v-if="group.is_revenue" class="text-xs text-green-600 font-medium">Yes</span>
                            <span v-else class="text-xs text-gray-400">No</span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span v-if="group.is_addable" class="text-xs text-green-600 font-medium">Yes</span>
                            <span v-else class="text-xs text-gray-400">No</span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="group.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ group.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-span-1 text-xs text-gray-400">{{ formatDate(group.last_synced_at) }}</div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No ledger groups found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
