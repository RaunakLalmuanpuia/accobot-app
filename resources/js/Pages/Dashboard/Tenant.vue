<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    tenant:        Object,
    roleName:      String,
    memberType:    String,
    permissions:   Array,
    stats:         Object,
    recentMembers: Array,
    roleBreakdown: Array,
})

const page = usePage()
const can = (p) => props.permissions.includes(p)

const avatarColors = ['bg-violet-500','bg-violet-500','bg-sky-500','bg-teal-500','bg-emerald-500','bg-amber-500','bg-rose-500']
const initials     = (name) => name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
const avatarColor  = (id)   => avatarColors[id % avatarColors.length]

const roleColorMap = {
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
const roleColor = (name) => roleColorMap[name] ?? 'bg-gray-100 text-gray-600'

// Permission groups come from the backend (config/permission_groups.php)
const activeGroups = computed(() =>
    (page.props.auth.permission_groups ?? [])
        .map(g => ({ ...g, active: g.perms.filter(p => props.permissions.includes(p)) }))
        .filter(g => g.active.length > 0)
)

// Strip prefix for display: 'clients.create' → 'create'
const permLabel = (p) => p.split('.').slice(1).join('.')
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full capitalize', roleColor(roleName)]">
                            {{ roleName }}
                        </span>
                        <span v-if="memberType === 'external'" class="text-xs font-medium px-2 py-0.5 rounded-full bg-teal-50 text-teal-600 border border-teal-200">
                            External
                        </span>
                    </div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ tenant.name }}</h1>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stat cards -->
                <div v-if="stats && Object.keys(stats).length" class="grid gap-5 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-if="stats.members !== undefined" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Team Members</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.members }}</p>
                    </div>
                    <div v-if="stats.clients !== undefined" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Clients</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.clients }}</p>
                    </div>
                    <div v-if="stats.vendors !== undefined" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Vendors</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.vendors }}</p>
                    </div>
                    <div v-if="stats.products !== undefined" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Inventory</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.products }}</p>
                    </div>
                    <div v-if="stats.invoices !== undefined" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Invoices</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.invoices }}</p>
                    </div>
                    <div v-if="stats.pending_transactions !== undefined" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Pending Transactions</p>
                        <p class="text-3xl font-bold" :class="stats.pending_transactions > 0 ? 'text-amber-500' : 'text-gray-900'">{{ stats.pending_transactions }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-6">

                    <!-- Left col: recent members (if members.view) -->
                    <div v-if="can('members.view')" class="col-span-2 space-y-6">

                        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h2 class="font-semibold text-gray-800">Recent Members</h2>
                                <Link :href="route('team.index', { tenant: tenant.id })" class="text-xs text-violet-600 hover:text-violet-800 font-medium">View all →</Link>
                            </div>

                            <div v-if="roleBreakdown && roleBreakdown.length" class="px-6 py-3 border-b border-gray-50 flex flex-wrap gap-2">
                                <span
                                    v-for="r in roleBreakdown"
                                    :key="r.role"
                                    :class="['text-xs font-medium px-2.5 py-1 rounded-full capitalize', roleColor(r.role)]"
                                >{{ r.count }} {{ r.role }}</span>
                            </div>

                            <ul class="divide-y divide-gray-50">
                                <li v-for="m in recentMembers" :key="m.id" class="flex items-center gap-3 px-6 py-3">
                                    <div :class="['h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0', avatarColor(m.id)]">
                                        {{ initials(m.name) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ m.name }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ m.email }}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span v-if="m.member_type === 'external'" class="text-xs px-1.5 py-0.5 rounded bg-teal-50 text-teal-600 border border-teal-100">ext</span>
                                        <span :class="['text-xs font-medium px-2 py-0.5 rounded-full capitalize', roleColor(m.role)]">{{ m.role }}</span>
                                    </div>
                                </li>
                                <li v-if="!recentMembers || !recentMembers.length" class="px-6 py-4 text-sm text-gray-400 text-center">No members yet</li>
                            </ul>
                        </div>

                        <!-- Your Access card -->
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                                <div>
                                    <h2 class="font-semibold text-gray-800">Your Access</h2>
                                    <p class="text-xs text-gray-400 mt-0.5">Role: <span :class="['font-medium px-1.5 py-0.5 rounded-full text-xs', roleColor(roleName)]">{{ roleName }}</span></p>
                                </div>
                            </div>
                            <div class="px-6 py-4 grid grid-cols-2 gap-4">
                                <div v-for="group in activeGroups" :key="group.label">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">{{ group.label }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="p in group.active"
                                            :key="p"
                                            class="text-xs px-2 py-0.5 rounded-full bg-violet-50 text-violet-700 font-medium capitalize"
                                        >{{ permLabel(p) }}</span>
                                    </div>
                                </div>
                                <p v-if="!activeGroups.length" class="text-sm text-gray-400 col-span-2">No permissions assigned.</p>
                            </div>
                        </div>
                    </div>

                    <!-- If no members.view: Your Access takes full left col -->
                    <div v-if="!can('members.view')" class="col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="font-semibold text-gray-800">Your Access</h2>
                            <p class="text-xs text-gray-400 mt-0.5">Role: <span :class="['font-medium px-1.5 py-0.5 rounded-full text-xs', roleColor(roleName)]">{{ roleName }}</span></p>
                        </div>
                        <div class="px-6 py-4 grid grid-cols-2 gap-4">
                            <div v-for="group in activeGroups" :key="group.label">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">{{ group.label }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="p in group.active"
                                        :key="p"
                                        class="text-xs px-2 py-0.5 rounded-full bg-violet-50 text-violet-700 font-medium capitalize"
                                    >{{ permLabel(p) }}</span>
                                </div>
                            </div>
                            <p v-if="!activeGroups.length" class="text-sm text-gray-400 col-span-2">No permissions assigned.</p>
                        </div>
                    </div>

                    <!-- Right col: Quick actions -->
                    <div class="space-y-3">
                        <h2 class="font-semibold text-gray-800 px-1">Quick Actions</h2>

                        <Link v-if="can('members.view')" :href="route('team.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-amber-50 flex items-center justify-center group-hover:bg-amber-100 transition shrink-0">
                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Team</p>
                                <p class="text-xs text-gray-400">View team members</p>
                            </div>
                        </Link>

                        <Link v-if="can('clients.view')" :href="route('clients.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-sky-50 flex items-center justify-center group-hover:bg-sky-100 transition shrink-0">
                                <svg class="h-4 w-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Clients</p>
                                <p class="text-xs text-gray-400">Manage client records</p>
                            </div>
                        </Link>

                        <Link v-if="can('vendors.view')" :href="route('vendors.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition shrink-0">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Vendors</p>
                                <p class="text-xs text-gray-400">Manage vendor records</p>
                            </div>
                        </Link>

                        <Link v-if="can('products.view')" :href="route('products.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-teal-50 flex items-center justify-center group-hover:bg-teal-100 transition shrink-0">
                                <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Inventory</p>
                                <p class="text-xs text-gray-400">Manage products</p>
                            </div>
                        </Link>

                        <Link v-if="can('invoices.view')" :href="route('invoices.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-sky-50 flex items-center justify-center group-hover:bg-sky-100 transition shrink-0">
                                <svg class="h-4 w-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Invoices</p>
                                <p class="text-xs text-gray-400">View & create invoices</p>
                            </div>
                        </Link>

                        <Link v-if="can('transactions.view')" :href="route('banking.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-violet-50 flex items-center justify-center group-hover:bg-violet-100 transition shrink-0">
                                <svg class="h-4 w-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Transactions</p>
                                <p class="text-xs text-gray-400">Review bank transactions</p>
                            </div>
                        </Link>

                        <Link v-if="can('chat.view')" :href="route('chat.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-rose-50 flex items-center justify-center group-hover:bg-rose-100 transition shrink-0">
                                <svg class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Assistant</p>
                                <p class="text-xs text-gray-400">Ask your accounting AI</p>
                            </div>
                        </Link>

                        <Link v-if="can('members.assign_role')" :href="route('roles.index', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-violet-50 flex items-center justify-center group-hover:bg-violet-100 transition shrink-0">
                                <svg class="h-4 w-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Roles & Permissions</p>
                                <p class="text-xs text-gray-400">Manage access control</p>
                            </div>
                        </Link>

                        <Link v-if="can('audit.view')" :href="route('dashboard', { tenant: tenant.id })"
                            class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:border-violet-300 hover:shadow-sm transition group">
                            <div class="h-8 w-8 rounded-lg bg-slate-50 flex items-center justify-center group-hover:bg-slate-100 transition shrink-0">
                                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Audit Log</p>
                                <p class="text-xs text-gray-400">View activity history</p>
                            </div>
                        </Link>

                        <p v-if="!can('members.view') && !can('clients.view') && !can('vendors.view') && !can('products.view') && !can('invoices.view') && !can('transactions.view') && !can('chat.view') && !can('members.assign_role') && !can('audit.view')"
                            class="text-sm text-gray-400 px-1">No actions available for your role.</p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
