<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3'
import { hasPermission } from '@/utils/permissions';

const showingNavigationDropdown = ref(false)
const showInvitations = ref(false)
const showTenantSwitcher = ref(false)
const showMasterDropdown = ref(false)
const page = usePage()

const currentTenantId = () => page.props.auth.current_tenant_id
const isAdmin = () => page.props.auth.is_admin
const isImpersonating = () => page.props.auth.impersonating

// Resolve the correct home URL based on role
const homeUrl = () => isAdmin()
    ? route('admin.dashboard')
    : currentTenantId()
        ? route('dashboard', { tenant: currentTenantId() })
        : route('profile.edit')

// Switch tenant = navigate to that tenant's dashboard URL
const switchTenant = (id) => {
    showTenantSwitcher.value = false
    router.visit(route('dashboard', { tenant: id }))
}

const currentTenant = () =>
    page.props.auth.tenants.find(t => t.id === page.props.auth.current_tenant_id)

function closeDropdowns(e) {
    if (!e.target.closest('[data-dropdown]')) {
        showTenantSwitcher.value = false
        showInvitations.value = false
        showMasterDropdown.value = false
    }
}
onMounted(() => document.addEventListener('click', closeDropdowns))
onUnmounted(() => document.removeEventListener('click', closeDropdowns))

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
</script>

<template>
    <div>
        <!-- Impersonation banner -->
        <div v-if="isImpersonating()" class="bg-yellow-400 text-yellow-900 text-sm text-center py-2 px-4 flex items-center justify-center gap-4">
            <span>You are impersonating <strong>{{ $page.props.auth.user.name }}</strong>. Destructive actions are disabled.</span>
            <button @click="stopImpersonation()" class="underline font-semibold hover:text-yellow-800">Stop impersonating</button>
        </div>

        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="homeUrl()">
                                    <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">

                                <!-- Tenant switcher -->
                                <div
                                    v-if="page.props.auth.tenants.length"
                                    class="relative flex items-center"
                                    data-dropdown
                                >
                                    <button
                                        @click="showTenantSwitcher = !showTenantSwitcher"
                                        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 transition max-w-[200px]"
                                    >
                                        <span class="h-2 w-2 rounded-full bg-emerald-400 shrink-0"></span>
                                        <span class="truncate">{{ currentTenant()?.name ?? 'Select Tenant' }}</span>
                                        <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                                        </svg>
                                    </button>

                                    <div
                                        v-if="showTenantSwitcher"
                                        class="absolute left-0 top-full mt-2 w-56 rounded-xl border border-gray-200 bg-white shadow-lg z-50 overflow-hidden"
                                    >
                                        <div class="px-3 py-2 border-b border-gray-100">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Your Tenants</p>
                                        </div>
                                        <div class="py-1">
                                            <button
                                                v-for="t in page.props.auth.tenants"
                                                :key="t.id"
                                                @click="switchTenant(t.id)"
                                                class="flex w-full items-center gap-3 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                                            >
                                                <span
                                                    :class="['h-2 w-2 rounded-full shrink-0', t.id === page.props.auth.current_tenant_id ? 'bg-emerald-400' : 'bg-transparent border border-gray-300']"
                                                ></span>
                                                <span class="truncate">{{ t.name }}</span>
                                                <svg v-if="t.id === page.props.auth.current_tenant_id" class="ml-auto h-4 w-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Admin nav -->
                                <template v-if="isAdmin() && !currentTenantId()">
                                    <NavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">Dashboard</NavLink>
                                </template>

                                <!-- Tenant nav (shown for both tenant users and admin visiting a tenant) -->
                                <template v-if="currentTenantId()">
                                    <NavLink :href="route('dashboard', { tenant: currentTenantId() })" :active="route().current('dashboard')">Dashboard</NavLink>

                                    <template v-if="hasPermission('members.view') || hasPermission('members.assign_role')">
                                        <span class="self-center h-5 w-px bg-gray-200"></span>

                                        <NavLink
                                            v-if="hasPermission('members.view')"
                                            :href="route('team.index', { tenant: currentTenantId() })"
                                            :active="route().current('team.index')"
                                        >Team</NavLink>

                                        <NavLink
                                            v-if="hasPermission('members.assign_role')"
                                            :href="route('roles.index', { tenant: currentTenantId() })"
                                            :active="route().current('roles.index')"
                                        >Roles</NavLink>
                                    </template>

                                    <template v-if="hasPermission('clients.view') || hasPermission('vendors.view') || hasPermission('products.view') || hasPermission('narration_heads.view') || hasPermission('invoices.view')">
                                        <span class="self-center h-5 w-px bg-gray-200"></span>

                                        <div class="relative flex items-center" data-dropdown>
                                            <button
                                                @click="showMasterDropdown = !showMasterDropdown"
                                                class="inline-flex items-center gap-1 px-1 pt-1 text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 transition duration-150 ease-in-out focus:outline-none"
                                            >
                                                Master
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <div
                                                v-if="showMasterDropdown"
                                                class="absolute left-0 top-full mt-2 w-40 rounded-xl border border-gray-200 bg-white shadow-lg z-50 overflow-hidden"
                                            >
                                                <Link
                                                    v-if="hasPermission('clients.view')"
                                                    :href="route('clients.index', { tenant: currentTenantId() })"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                                                    @click="showMasterDropdown = false"
                                                >Clients</Link>
                                                <Link
                                                    v-if="hasPermission('vendors.view')"
                                                    :href="route('vendors.index', { tenant: currentTenantId() })"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                                                    @click="showMasterDropdown = false"
                                                >Vendors</Link>
                                                <Link
                                                    v-if="hasPermission('products.view')"
                                                    :href="route('products.index', { tenant: currentTenantId() })"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                                                    @click="showMasterDropdown = false"
                                                >Inventory</Link>
                                                <Link
                                                    v-if="hasPermission('narration_heads.view')"
                                                    :href="route('narration-heads.index', { tenant: currentTenantId() })"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                                                    @click="showMasterDropdown = false"
                                                >Narration Heads</Link>
                                                <Link
                                                    v-if="hasPermission('invoices.view')"
                                                    :href="route('invoices.index', { tenant: currentTenantId() })"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                                                    @click="showMasterDropdown = false"
                                                >Invoices</Link>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-if="hasPermission('chat.view') || hasPermission('transactions.view')">
                                        <span class="self-center h-5 w-px bg-gray-200"></span>

                                        <NavLink
                                            v-if="hasPermission('chat.view')"
                                            :href="route('chat.index', { tenant: currentTenantId() })"
                                            :active="route().current('chat.index')"
                                        >Assistant</NavLink>

                                        <NavLink
                                            v-if="hasPermission('transactions.view')"
                                            :href="route('banking.index', { tenant: currentTenantId() })"
                                            :active="route().current('banking.index')"
                                        >Narration</NavLink>
                                    </template>
                                </template>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-2">

                            <!-- Invitation bell -->
                            <div v-if="!isAdmin()" class="relative" data-dropdown>
                                <button
                                    @click="showInvitations = !showInvitations"
                                    class="relative p-2 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <span v-if="pendingInvitations().length" class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500"></span>
                                </button>

                                <div
                                    v-if="showInvitations"
                                    class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50 overflow-hidden"
                                >
                                    <div class="px-4 py-3 border-b border-gray-100">
                                        <p class="text-sm font-semibold text-gray-700">Pending Invitations</p>
                                    </div>

                                    <div v-if="pendingInvitations().length" class="divide-y divide-gray-50">
                                        <div v-for="inv in pendingInvitations()" :key="inv.token" class="px-4 py-3">
                                            <p class="text-sm font-medium text-gray-900">{{ inv.tenant_name }}</p>
                                            <p class="text-xs text-gray-500 mt-0.5 capitalize">Role: {{ inv.role_name }}</p>
                                            <div class="flex gap-2 mt-2">
                                                <button @click="acceptInvitation(inv.id)" class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700 transition">Accept</button>
                                                <button @click="declineInvitation(inv.id)" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">Decline</button>
                                            </div>
                                        </div>
                                    </div>

                                    <p v-else class="px-4 py-4 text-sm text-gray-400 text-center">No pending invitations</p>
                                </div>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none">
                                                {{ $page.props.auth.user.name }}
                                                <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="homeUrl()" :active="isAdmin() ? route().current('admin.dashboard') : route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">{{ $page.props.auth.user.name }}</div>
                            <div class="text-sm font-medium text-gray-500">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
