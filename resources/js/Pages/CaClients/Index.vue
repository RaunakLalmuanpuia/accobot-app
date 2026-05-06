<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const copiedTokenId = ref(null)

function copyToken(bizId, token) {
    navigator.clipboard.writeText(token).then(() => {
        copiedTokenId.value = bizId
        setTimeout(() => { copiedTokenId.value = null }, 2000)
    })
}

const props = defineProps({
    tenant:            Object,
    linkedBusinesses:  Array,
    pendingInvites:    Array,
})

const showAddModal = ref(false)

const form = useForm({
    email:         '',
    business_name: '',
})

function submit() {
    form.post(route('ca.businesses.store', props.tenant.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false
            form.reset()
        },
    })
}

function removeClient(businessTenantId) {
    if (! confirm('Remove this client? Your firm will lose access to their books.')) return
    router.delete(route('ca.businesses.destroy', { tenant: props.tenant.id, businessTenant: businessTenantId }), {
        preserveScroll: true,
    })
}

function revokeInvite(invitationId) {
    if (! confirm('Revoke this invitation?')) return
    router.delete(route('ca.businesses.invites.revoke', { tenant: props.tenant.id, invitation: invitationId }), {
        preserveScroll: true,
    })
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CA Clients" />

        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Clients</h1>
                <button
                    @click="showAddModal = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 transition"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Business
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Linked businesses -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="font-semibold text-gray-800">Connected Clients</h2>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ linkedBusinesses.length }}</span>
                    </div>

                    <div v-if="linkedBusinesses.length === 0" class="px-6 py-10 text-center">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 mb-3">
                            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <p class="text-sm text-gray-500">No clients connected yet.</p>
                        <button @click="showAddModal = true" class="mt-3 text-sm text-violet-600 hover:text-violet-800 font-medium">Add your first client →</button>
                    </div>

                    <ul v-else class="divide-y divide-gray-50">
                        <li v-for="biz in linkedBusinesses" :key="biz.id" class="px-6 py-4 space-y-3">
                            <div class="flex items-center gap-4">
                                <div class="h-9 w-9 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 text-sm font-semibold shrink-0">
                                    {{ biz.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ biz.name }}</p>
                                    <p class="text-xs text-gray-400">
                                        <span v-if="biz.city">{{ biz.city }}<span v-if="biz.state">, {{ biz.state }}</span></span>
                                        <span v-if="biz.gstin" class="ml-2 font-mono">{{ biz.gstin }}</span>
                                    </p>
                                </div>
                                <span :class="[
                                    'text-xs font-medium px-2 py-0.5 rounded-full shrink-0',
                                    biz.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]">{{ biz.status }}</span>
                                <button
                                    @click="removeClient(biz.id)"
                                    class="text-xs text-gray-400 hover:text-red-500 transition shrink-0"
                                    title="Remove client"
                                >Remove</button>
                            </div>

                            <!-- Tally Token -->
                            <div v-if="biz.tally_token" class="ml-13 flex items-center gap-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                                <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                <span class="text-xs text-gray-500 font-medium shrink-0">Tally Token</span>
                                <code class="flex-1 text-xs font-mono text-gray-700 truncate">{{ biz.tally_token }}</code>
                                <span v-if="biz.tally_last_used_at" class="text-xs text-green-600 shrink-0 font-medium">Connected</span>
                                <span v-else class="text-xs text-amber-500 shrink-0 font-medium">Not synced yet</span>
                                <button
                                    @click="copyToken(biz.id, biz.tally_token)"
                                    class="ml-1 shrink-0 rounded px-2 py-0.5 text-xs font-medium transition"
                                    :class="copiedTokenId === biz.id ? 'bg-green-100 text-green-700' : 'bg-violet-100 text-violet-700 hover:bg-violet-200'"
                                >
                                    {{ copiedTokenId === biz.id ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Pending invitations -->
                <div v-if="pendingInvites.length > 0" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="font-semibold text-gray-800">Pending Invitations</h2>
                    </div>
                    <ul class="divide-y divide-gray-50">
                        <li v-for="inv in pendingInvites" :key="inv.id" class="flex items-center gap-4 px-6 py-3.5">
                            <div class="h-8 w-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800">{{ inv.email }}</p>
                                <p class="text-xs text-gray-400">Expires {{ formatDate(inv.expires_at) }}</p>
                            </div>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 shrink-0">Pending</span>
                            <button
                                @click="revokeInvite(inv.id)"
                                class="text-xs text-gray-400 hover:text-red-500 transition shrink-0"
                            >Revoke</button>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- Add Client Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAddModal = false" />
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-semibold text-gray-900">Add Client</h2>
                        <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <p class="text-sm text-gray-500 mb-5">
                        Enter your client's email. If they're already on Accobot, they'll be connected instantly. Otherwise, they'll receive an invitation.
                    </p>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client's Email <span class="text-red-500">*</span></label>
                            <input
                                type="email"
                                v-model="form.email"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                                placeholder="owner@clientbusiness.com"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Suggested Business Name
                                <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input
                                type="text"
                                v-model="form.business_name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                                placeholder="e.g. Priya Textiles Pvt Ltd"
                            />
                            <p class="mt-1 text-xs text-gray-400">Pre-fills the business name if they register via your invitation.</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showAddModal = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                :class="['px-4 py-2 text-sm font-semibold text-white rounded-lg bg-violet-600 hover:bg-violet-700 transition', { 'opacity-50 cursor-not-allowed': form.processing }]"
                            >Send Invitation</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
