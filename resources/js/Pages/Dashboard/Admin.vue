<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
    stats:          Object,
    businesses:     Array,
    roleBreakdown:  Array,
})

const roleColors = {
    admin:  'bg-red-100 text-red-700',
    owner:  'bg-amber-100 text-amber-700',
    ca:     'bg-indigo-100 text-indigo-700',
    user:   'bg-gray-100 text-gray-600',
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-0.5">Platform Admin</p>
                <h1 class="text-xl font-semibold text-gray-900">Admin Dashboard</h1>
            </div>
        </template>

        <div class="py-8 space-y-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Stat cards -->
                <div class="grid grid-cols-3 gap-5 mb-8">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Businesses</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.businesses }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Total Users</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.users }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Roles</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.roles }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-6">

                    <!-- Businesses list -->
                    <div class="col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="font-semibold text-gray-800">All Businesses</h2>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            <li v-for="b in businesses" :key="b.id" class="flex items-center justify-between px-6 py-3">
                                <span class="text-sm font-medium text-gray-800">{{ b.name }}</span>
                                <span class="text-xs text-gray-400">{{ b.users_count }} member{{ b.users_count !== 1 ? 's' : '' }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Role breakdown -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="font-semibold text-gray-800">Users by Role</h2>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            <li v-for="r in roleBreakdown" :key="r.name" class="flex items-center justify-between px-6 py-3">
                                <span :class="['text-xs font-medium px-2.5 py-1 rounded-full capitalize', roleColors[r.name] ?? 'bg-gray-100 text-gray-600']">
                                    {{ r.name }}
                                </span>
                                <span class="text-sm font-semibold text-gray-700">{{ r.count }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Quick links per business -->
                <div class="mt-6">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Jump to Business</p>
                    <div class="grid grid-cols-3 gap-4">
                        <Link
                            v-for="b in businesses" :key="b.id"
                            :href="route('dashboard', { business: b.id })"
                            class="flex items-center justify-between bg-white rounded-xl border border-gray-200 px-4 py-3 hover:border-indigo-300 hover:shadow-sm transition"
                        >
                            <span class="text-sm font-medium text-gray-800">{{ b.name }}</span>
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
