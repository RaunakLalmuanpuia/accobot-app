<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
    business:       Object,
    stats:          Object,
    members:        Array,
    roleBreakdown:  Array,
})

const avatarColors = ['bg-violet-500','bg-indigo-500','bg-sky-500','bg-teal-500','bg-emerald-500','bg-amber-500','bg-rose-500']
const initials = (name) => name.split(' ').slice(0,2).map(n=>n[0]).join('').toUpperCase()
const avatarColor = (id) => avatarColors[id % avatarColors.length]

const roleColors = {
    owner: 'bg-amber-100 text-amber-700',
    ca:    'bg-indigo-100 text-indigo-700',
    user:  'bg-gray-100 text-gray-600',
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-500 mb-0.5">Owner</p>
                <h1 class="text-xl font-semibold text-gray-900">{{ business.name }}</h1>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-5">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Team Members</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.members }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Role Breakdown</p>
                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                            <span
                                v-for="r in roleBreakdown"
                                :key="r.role"
                                :class="['text-xs font-medium px-2.5 py-1 rounded-full capitalize', roleColors[r.role] ?? 'bg-gray-100 text-gray-600']"
                            >{{ r.count }} {{ r.role }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-6">

                    <!-- Recent members -->
                    <div class="col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="font-semibold text-gray-800">Team Members</h2>
                            <Link :href="route('team.index', { business: business.id })" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all →</Link>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            <li v-for="m in members" :key="m.id" class="flex items-center gap-3 px-6 py-3">
                                <div :class="['h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0', avatarColor(m.id)]">
                                    {{ initials(m.name) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ m.name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ m.email }}</p>
                                </div>
                                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full capitalize', roleColors[m.role] ?? 'bg-gray-100 text-gray-600']">{{ m.role }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Quick links -->
                    <div class="space-y-3">
                        <h2 class="font-semibold text-gray-800 px-1">Quick Actions</h2>
                        <Link :href="route('team.index', { business: business.id })" class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-indigo-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-amber-50 flex items-center justify-center group-hover:bg-amber-100 transition shrink-0">
                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Manage Team</p>
                                <p class="text-xs text-gray-400">Add, remove, assign roles</p>
                            </div>
                        </Link>
                        <Link :href="route('roles.index', { business: business.id })" class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-indigo-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-100 transition shrink-0">
                                <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Roles & Permissions</p>
                                <p class="text-xs text-gray-400">Manage access control</p>
                            </div>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
