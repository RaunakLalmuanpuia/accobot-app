<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'

defineProps({
    stats:         Object,
    tenants:       Array,
    roleBreakdown: Array,
    recentUsers:   Array,
})

const roleColor = (name) => {
    const map = {
        admin:              'bg-red-100 text-red-700',
        owner:              'bg-amber-100 text-amber-700',
        OwnerPartner:       'bg-amber-100 text-amber-700',
        TenantAdmin:        'bg-red-100 text-red-700',
        Manager:            'bg-blue-100 text-blue-700',
        CAManager:          'bg-blue-100 text-blue-700',
        Staff:              'bg-gray-100 text-gray-600',
        CAStaff:            'bg-gray-100 text-gray-600',
        Auditor:            'bg-purple-100 text-purple-700',
        Viewer:             'bg-slate-100 text-slate-500',
        ExternalAccountant: 'bg-teal-100 text-teal-700',
        IntegrationUser:    'bg-orange-100 text-orange-700',
    }
    return map[name] ?? 'bg-gray-100 text-gray-600'
}

const typeLabel = (type) => type === 'ca_firm' ? 'CA Firm' : 'Business'
const typeColor  = (type) => type === 'ca_firm' ? 'bg-violet-50 text-violet-600' : 'bg-amber-50 text-amber-600'

function impersonate(userId) {
    router.post(route('impersonate.start', userId))
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
                <div class="grid grid-cols-5 gap-4 mb-8">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-500 mb-1">Tenants</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.tenants }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-500 mb-1">Businesses</p>
                        <p class="text-3xl font-bold text-amber-600">{{ stats.businesses }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-500 mb-1">CA Firms</p>
                        <p class="text-3xl font-bold text-violet-600">{{ stats.ca_firms }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-500 mb-1">Users</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.users }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-500 mb-1">Roles</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.roles }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-6">

                    <!-- Tenants list -->
                    <div class="col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="font-semibold text-gray-800">All Tenants</h2>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            <li v-for="t in tenants" :key="t.id" class="flex items-center justify-between px-6 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span :class="['text-xs font-medium px-2 py-0.5 rounded-full shrink-0', typeColor(t.type)]">{{ typeLabel(t.type) }}</span>
                                    <span class="text-sm font-medium text-gray-800 truncate">{{ t.name }}</span>
                                </div>
                                <div class="flex items-center gap-3 shrink-0 ml-3">
                                    <span class="text-xs text-gray-400">{{ t.users_count }} member{{ t.users_count !== 1 ? 's' : '' }}</span>
                                    <Link :href="route('dashboard', { tenant: t.id })" class="text-xs text-violet-600 hover:text-violet-800 font-medium">Open →</Link>
                                </div>
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
                                <span :class="['text-xs font-medium px-2.5 py-1 rounded-full capitalize', roleColor(r.name)]">
                                    {{ r.name }}
                                </span>
                                <span class="text-sm font-semibold text-gray-700">{{ r.count }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Recent users with impersonation -->
                <div class="mt-6 bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="font-semibold text-gray-800">Recent Users</h2>
                    </div>
                    <ul class="divide-y divide-gray-50">
                        <li v-for="u in recentUsers" :key="u.id" class="flex items-center justify-between px-6 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800">{{ u.name }}</p>
                                <p class="text-xs text-gray-400">{{ u.email }}</p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 ml-3">
                                <span :class="['text-xs px-2 py-0.5 rounded-full capitalize', u.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500']">
                                    {{ u.status }}
                                </span>
                                <button
                                    @click="impersonate(u.id)"
                                    class="text-xs text-gray-500 hover:text-violet-600 font-medium border border-gray-200 rounded-lg px-2.5 py-1 hover:border-violet-300 transition"
                                >
                                    Impersonate
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
