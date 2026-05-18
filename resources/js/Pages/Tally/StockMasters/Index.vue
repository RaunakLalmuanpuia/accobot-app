<script setup>
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant: Object,
    counts: Object,
})

const sections = [
    {
        key:   'stockGroups',
        label: 'Stock Groups',
        desc:  'Hierarchical groupings for stock items with costing & valuation methods.',
        route: 'tally.stock-groups.index',
    },
    {
        key:   'stockCategories',
        label: 'Stock Categories',
        desc:  'Optional categorisation layer that cross-cuts stock groups.',
        route: 'tally.stock-categories.index',
    },
    {
        key:   'godowns',
        label: 'Godowns',
        desc:  'Warehouse / storage locations where physical stock is held.',
        route: 'tally.godowns.index',
    },
    {
        key:   'units',
        label: 'Units of Measure',
        desc:  'Simple and compound units used across stock items.',
        route: 'tally.units.index',
    },
]
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Stock Masters</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ counts.stockGroups }} groups · {{ counts.stockCategories }} categories · {{ counts.godowns }} godowns · {{ counts.units }} units
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <Link :href="route('tally.stock-items.index', { tenant: tenant.id })"
                          class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                        View Stock Items →
                    </Link>
                    <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Link v-for="s in sections" :key="s.key"
                          :href="route(s.route, { tenant: tenant.id })"
                          class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-violet-300 hover:shadow-md transition">
                        <p class="text-3xl font-bold text-violet-600 group-hover:text-violet-700">
                            {{ counts[s.key] }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ s.label }}</p>
                        <p class="mt-1 text-xs text-gray-400 leading-snug">{{ s.desc }}</p>
                        <p class="mt-3 text-xs font-medium text-violet-600 group-hover:text-violet-800">
                            Manage →
                        </p>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
