<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3'
import { hasPermission, hasFeature } from '@/utils/permissions';
import { usePushNotifications } from '@/composables/usePushNotifications';

const page = usePage()
const sidebarOpen = ref(false)
const showTenantSwitcher = ref(false)
const showInvitations = ref(false)

const tallyOpen = ref(route().current('tally.*'))
const masterOpen = ref(
    route().current('clients.*') ||
    route().current('vendors.*') ||
    route().current('products.*') ||
    route().current('narration-heads.*') ||
    route().current('invoices.*')
)

const currentTenantId = () => page.props.auth.current_tenant_id
const isAdmin = () => page.props.auth.is_admin
const isImpersonating = () => page.props.auth.impersonating

const homeUrl = () => isAdmin()
    ? route('admin.dashboard')
    : currentTenantId()
        ? route('dashboard', { tenant: currentTenantId() })
        : route('profile.edit')

const switchTenant = (id) => {
    showTenantSwitcher.value = false
    router.visit(route('dashboard', { tenant: id }))
}

const currentTenant = () =>
    page.props.auth.tenants.find(t => t.id === page.props.auth.current_tenant_id)

const isPersonalTenant = () => currentTenant()?.is_personal ?? false

const pendingInvitations = () => page.props.auth.pending_invitations ?? []

function acceptInvitation(id) {
    router.post(route('invitation.accept-by-id', id), {}, {
        onSuccess: () => { showInvitations.value = false },
    })
}

function declineInvitation(id) {
    router.delete(route('invitation.decline-by-id', id), {}, {
        onSuccess: () => {
            if (!pendingInvitations().length) showInvitations.value = false
        },
    })
}

function stopImpersonation() {
    router.post(route('impersonate.stop'))
}

function closeDropdowns(e) {
    if (!e.target.closest('[data-dropdown]')) {
        showTenantSwitcher.value = false
        showInvitations.value = false
    }
}

const { subscribe: subscribePush } = usePushNotifications()

onMounted(() => {
    document.addEventListener('click', closeDropdowns)
    if (currentTenantId()) subscribePush()
})
onUnmounted(() => document.removeEventListener('click', closeDropdowns))
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-100">

        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-20 bg-black/40 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- ─── Sidebar ─────────────────────────────────────────────────────── -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-white border-r border-gray-200 transition-transform duration-200 ease-in-out lg:static lg:z-auto lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-100 px-4">
                <Link :href="homeUrl()" class="flex items-center gap-2.5">
                    <ApplicationLogo class="h-8 w-auto" />
                    <span class="text-base font-semibold text-gray-800 tracking-tight">accobot</span>
                </Link>
                <button
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 lg:hidden"
                    @click="sidebarOpen = false"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Tenant switcher -->
            <div
                v-if="page.props.auth.tenants.length && !isAdmin()"
                class="relative shrink-0 border-b border-gray-100 px-3 py-3"
                data-dropdown
            >
                <button
                    @click="showTenantSwitcher = !showTenantSwitcher"
                    class="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100"
                >
                    <span :class="['h-2 w-2 shrink-0 rounded-full', isPersonalTenant() ? 'bg-green-400' : 'bg-orange-400']" />
                    <span class="flex-1 truncate text-left">{{ currentTenant()?.name ?? 'Select Tenant' }}</span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                    </svg>
                </button>

                <div
                    v-if="showTenantSwitcher"
                    class="absolute inset-x-3 top-full z-50 mt-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg"
                >
                    <div class="border-b border-gray-100 px-3 py-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Your Companies</p>
                    </div>
                    <div class="max-h-56 overflow-y-auto py-1">
                        <template v-for="(t, index) in page.props.auth.tenants" :key="t.id">
                            <div
                                v-if="index > 0 && !t.is_personal && page.props.auth.tenants[index - 1].is_personal"
                                class="mx-3 my-1 border-t border-gray-100"
                            >
                                <p class="pt-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Clients</p>
                            </div>
                            <button
                                @click="switchTenant(t.id)"
                                class="flex w-full items-center gap-3 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50"
                            >
                                <span :class="['h-2 w-2 shrink-0 rounded-full', t.is_personal ? 'bg-green-400' : 'bg-orange-400']" />
                                <span class="flex-1 truncate text-left">{{ t.name }}</span>
                                <span v-if="t.is_personal" class="shrink-0 rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-violet-600">Mine</span>
                                <svg v-if="t.id === page.props.auth.current_tenant_id" class="ml-auto h-4 w-4 shrink-0 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Navigation links -->
            <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">

                <!-- ── Admin nav ── -->
                <template v-if="isAdmin() && !currentTenantId()">
                    <SidebarNavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </SidebarNavLink>
                    <SidebarNavLink :href="route('admin.ai-usage')" :active="route().current('admin.ai-usage')">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
                        AI Usage
                    </SidebarNavLink>
                </template>

                <!-- ── Tenant nav ── -->
                <template v-if="currentTenantId()">

                    <!-- Dashboard -->
                    <SidebarNavLink :href="route('dashboard', { tenant: currentTenantId() })" :active="route().current('dashboard')">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </SidebarNavLink>

                    <!-- CA: Businesses -->
                    <SidebarNavLink
                        v-if="currentTenant()?.type === 'ca_firm'"
                        :href="route('ca.businesses.index', { tenant: currentTenantId() })"
                        :active="route().current('ca.businesses.index')"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Businesses
                    </SidebarNavLink>

                    <!-- People: Team + Roles -->
                    <template v-if="hasPermission('members.view') || hasPermission('members.assign_role')">
                        <p class="px-3 pb-1 pt-5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">People</p>
                        <SidebarNavLink v-if="hasPermission('members.view')" :href="route('team.index', { tenant: currentTenantId() })" :active="route().current('team.index')">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Team
                        </SidebarNavLink>
                        <SidebarNavLink v-if="hasPermission('members.assign_role')" :href="route('roles.index', { tenant: currentTenantId() })" :active="route().current('roles.index')">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Roles
                        </SidebarNavLink>
                    </template>

                    <!-- Master Data (collapsible) -->
                    <template v-if="hasPermission('clients.view') || hasPermission('vendors.view') || hasPermission('products.view') || hasPermission('narration_heads.view') || hasPermission('invoices.view')">
                        <p class="px-3 pb-1 pt-5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Master</p>
                        <button
                            @click="masterOpen = !masterOpen"
                            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-600 transition-colors duration-150 hover:bg-gray-50 hover:text-gray-900"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
                            <span class="flex-1 text-left">Master Data</span>
                            <svg :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform duration-150', masterOpen ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div v-show="masterOpen" class="ml-2 space-y-0.5 border-l-2 border-gray-100 pl-3">
                            <SidebarNavLink v-if="hasPermission('clients.view')" :href="route('clients.index', { tenant: currentTenantId() })" :active="route().current('clients.*')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Clients
                            </SidebarNavLink>
                            <SidebarNavLink v-if="hasPermission('vendors.view')" :href="route('vendors.index', { tenant: currentTenantId() })" :active="route().current('vendors.*')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                Vendors
                            </SidebarNavLink>
                            <SidebarNavLink v-if="hasPermission('products.view')" :href="route('products.index', { tenant: currentTenantId() })" :active="route().current('products.*')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                Inventory
                            </SidebarNavLink>
                            <SidebarNavLink v-if="hasPermission('narration_heads.view')" :href="route('narration-heads.index', { tenant: currentTenantId() })" :active="route().current('narration-heads.*')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                Narration Heads
                            </SidebarNavLink>
                            <SidebarNavLink v-if="hasPermission('invoices.view')" :href="route('invoices.index', { tenant: currentTenantId() })" :active="route().current('invoices.*')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Invoices
                            </SidebarNavLink>
                        </div>
                    </template>

                    <!-- Communication -->
                    <template v-if="(hasPermission('chat.view') && hasFeature('ai_assistant')) || (hasPermission('chat.room.view') && hasFeature('group_chat')) || hasPermission('transactions.view')">
                        <p class="px-3 pb-1 pt-5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Communication</p>
                        <SidebarNavLink v-if="hasPermission('chat.view') && hasFeature('ai_assistant')" :href="route('chat.index', { tenant: currentTenantId() })" :active="route().current('chat.index')">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Assistant
                        </SidebarNavLink>
                        <SidebarNavLink v-if="hasPermission('chat.room.view') && hasFeature('group_chat')" :href="route('chat.groups.index', { tenant: currentTenantId() })" :active="route().current('chat.groups.*')">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
                            Groups
                        </SidebarNavLink>
                        <SidebarNavLink v-if="hasPermission('transactions.view')" :href="route('banking.index', { tenant: currentTenantId() })" :active="route().current('banking.index')">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            Narration
                        </SidebarNavLink>
                    </template>

                    <!-- Tally ERP (collapsible) -->
                    <template v-if="hasPermission('integrations.view') && hasFeature('tally_sync')">
                        <p class="px-3 pb-1 pt-5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Tally ERP</p>
                        <button
                            @click="tallyOpen = !tallyOpen"
                            :class="[
                                'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors duration-150',
                                route().current('tally.*') ? 'bg-violet-50 text-violet-700 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                            ]"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span class="flex-1 text-left">Tally</span>
                            <svg :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform duration-150', tallyOpen ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div v-show="tallyOpen" class="ml-2 space-y-0.5 border-l-2 border-gray-100 pl-3">
                            <SidebarNavLink :href="route('tally.sync.index', { tenant: currentTenantId() })" :active="route().current('tally.sync.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Sync
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.connection.show', { tenant: currentTenantId() })" :active="route().current('tally.connection.show')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                Connection
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.ledger-groups.index', { tenant: currentTenantId() })" :active="route().current('tally.ledger-groups.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                Ledger Groups
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.ledgers.index', { tenant: currentTenantId() })" :active="route().current('tally.ledgers.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                Ledgers
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.vouchers.index', { tenant: currentTenantId() })" :active="route().current('tally.vouchers.*')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                Vouchers
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.stock-masters.index', { tenant: currentTenantId() })" :active="route().current('tally.stock-masters.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                Stock Masters
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.stock-items.index', { tenant: currentTenantId() })" :active="route().current('tally.stock-items.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                Stock Items
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.units.index', { tenant: currentTenantId() })" :active="route().current('tally.units.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                Units
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.statutory-masters.index', { tenant: currentTenantId() })" :active="route().current('tally.statutory-masters.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                Statutory
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.companies.index', { tenant: currentTenantId() })" :active="route().current('tally.companies.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                Companies
                            </SidebarNavLink>
                            <SidebarNavLink :href="route('tally.payroll.index', { tenant: currentTenantId() })" :active="route().current('tally.payroll.index')">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                Payroll
                            </SidebarNavLink>
                        </div>
                    </template>

                    <!-- Audit Log -->
                    <template v-if="hasPermission('audit.view')">
                        <p class="px-3 pb-1 pt-5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Admin</p>
                        <SidebarNavLink :href="route('settings.audit', { tenant: currentTenantId() })" :active="route().current('settings.audit')">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Audit Log
                        </SidebarNavLink>
                    </template>

                </template>
            </nav>

            <!-- ─── Sidebar footer ─────────────────────────────────────────── -->
            <div class="shrink-0 border-t border-gray-100">

                <!-- Invitations -->
                <div v-if="!isAdmin()" class="relative border-b border-gray-100 px-2 py-2" data-dropdown>
                    <button
                        @click="showInvitations = !showInvitations"
                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span class="flex-1 text-left">Invitations</span>
                        <span
                            v-if="pendingInvitations().length"
                            class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"
                        >{{ pendingInvitations().length }}</span>
                    </button>

                    <!-- Invitations panel (floats above) -->
                    <div
                        v-if="showInvitations"
                        class="absolute bottom-full inset-x-2 mb-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg z-50"
                    >
                        <div class="border-b border-gray-100 px-4 py-3">
                            <p class="text-sm font-semibold text-gray-700">Pending Invitations</p>
                        </div>
                        <div v-if="pendingInvitations().length" class="max-h-60 divide-y divide-gray-50 overflow-y-auto">
                            <div v-for="inv in pendingInvitations()" :key="inv.token" class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900">{{ inv.tenant_name }}</p>
                                <p class="mt-0.5 text-xs capitalize text-gray-500">Role: {{ inv.role_name }}</p>
                                <div class="mt-2 flex gap-2">
                                    <button @click="acceptInvitation(inv.id)" class="rounded-lg bg-violet-600 px-3 py-1 text-xs font-medium text-white transition hover:bg-violet-700">Accept</button>
                                    <button @click="declineInvitation(inv.id)" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50">Decline</button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="px-4 py-4 text-center text-sm text-gray-400">No pending invitations</p>
                    </div>
                </div>

                <!-- Account links -->
                <div class="space-y-0.5 px-2 py-2">
                    <Link :href="route('profile.edit')" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-gray-900">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Profile
                    </Link>
                    <Link
                        v-if="currentTenantId() && hasPermission('tenant.view_settings')"
                        :href="route('settings.profile', { tenant: currentTenantId() })"
                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Tenant Settings
                    </Link>
                    <Link
                        v-if="currentTenantId()"
                        :href="route('billing.index', { tenant: currentTenantId() })"
                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Billing
                    </Link>
                </div>

                <!-- User info + logout -->
                <div class="flex items-center gap-2.5 border-t border-gray-100 px-4 py-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-semibold text-violet-700">
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $page.props.auth.user.name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ $page.props.auth.user.email }}</p>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-500"
                        title="Log out"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- ─── Main content ───────────────────────────────────────────────── -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

            <!-- Mobile top bar -->
            <div class="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4 lg:hidden">
                <button
                    @click="sidebarOpen = true"
                    class="rounded-lg p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <Link :href="homeUrl()">
                    <ApplicationLogo class="h-7 w-auto" />
                </Link>
            </div>

            <!-- Impersonation banner -->
            <div
                v-if="isImpersonating()"
                class="flex shrink-0 items-center justify-center gap-4 bg-yellow-400 px-4 py-2 text-center text-sm text-yellow-900"
            >
                <span>You are impersonating <strong>{{ $page.props.auth.user.name }}</strong>. Destructive actions are disabled.</span>
                <button @click="stopImpersonation()" class="font-semibold underline hover:text-yellow-800">Stop impersonating</button>
            </div>

            <!-- Persistent page header (stays fixed while content scrolls) -->
            <header v-if="$slots.header" class="shrink-0 bg-white shadow-sm border-b border-gray-200">
                <div class="px-4 py-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Scrollable page area -->
            <div class="flex-1 overflow-y-auto">
                <main>
                    <slot />
                </main>
            </div>
        </div>

    </div>
</template>
