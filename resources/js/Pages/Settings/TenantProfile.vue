<script setup>
import { ref } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:       Object,
    bankAccounts: Array,
})

const page = usePage()
const tenantId = page.props.auth.current_tenant_id
const canEdit  = hasPermission('tenant.update_settings')

// ── Tenant profile form ────────────────────────────────────────────────
const profileForm = useForm({
    name:          props.tenant.name          ?? '',
    phone:         props.tenant.phone         ?? '',
    email:         props.tenant.email         ?? '',
    website:       props.tenant.website       ?? '',
    gstin:         props.tenant.gstin         ?? '',
    pan:           props.tenant.pan           ?? '',
    logo_url:      props.tenant.logo_url      ?? '',
    address_line1: props.tenant.address_line1 ?? '',
    address_line2: props.tenant.address_line2 ?? '',
    city:          props.tenant.city          ?? '',
    state:         props.tenant.state         ?? '',
    pincode:       props.tenant.pincode       ?? '',
})

function submitProfile() {
    profileForm.patch(route('settings.profile.update', { tenant: tenantId }), {
        preserveScroll: true,
    })
}

// ── Bank account modal ─────────────────────────────────────────────────
const emptyAccount = () => ({
    bank_name:            '',
    account_holder_name:  '',
    account_number:       '',
    ifsc_code:            '',
    account_type:         'current',
    branch:               '',
})

const showBankModal  = ref(false)
const editingAccount = ref(null) // null = adding new

const bankForm = useForm(emptyAccount())

function openAdd() {
    editingAccount.value = null
    bankForm.reset()
    Object.assign(bankForm, emptyAccount())
    showBankModal.value = true
}

function openEdit(account) {
    editingAccount.value = account
    bankForm.bank_name           = account.bank_name
    bankForm.account_holder_name = account.account_holder_name
    bankForm.account_number      = account.account_number
    bankForm.ifsc_code           = account.ifsc_code
    bankForm.account_type        = account.account_type
    bankForm.branch              = account.branch ?? ''
    showBankModal.value = true
}

function submitBank() {
    if (editingAccount.value) {
        bankForm.put(route('settings.bank-accounts.update', { tenant: tenantId, bankAccount: editingAccount.value.id }), {
            preserveScroll: true,
            onSuccess: () => { showBankModal.value = false },
        })
    } else {
        bankForm.post(route('settings.bank-accounts.store', { tenant: tenantId }), {
            preserveScroll: true,
            onSuccess: () => { showBankModal.value = false },
        })
    }
}

function setPrimary(account) {
    router.post(route('settings.bank-accounts.set-primary', { tenant: tenantId, bankAccount: account.id }), {}, {
        preserveScroll: true,
    })
}

function deleteAccount(account) {
    if (!confirm(`Remove ${account.bank_name} account ending in ${account.account_number.slice(-4)}?`)) return
    router.delete(route('settings.bank-accounts.destroy', { tenant: tenantId, bankAccount: account.id }), {
        preserveScroll: true,
    })
}

const accountTypeLabel = { savings: 'Savings', current: 'Current', overdraft: 'Overdraft' }
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Tenant Settings</h2>
        </template>

        <div class="py-8">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-xl bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-700"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="space-y-8">
                <form id="profile-form" @submit.prevent="submitProfile" class="contents">

                    <!-- Basic info -->
                    <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Basic Info</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Business Name <span class="text-red-500">*</span></label>
                            <input v-model="profileForm.name" type="text" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-600">{{ profileForm.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input v-model="profileForm.phone" type="tel" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                                <p v-if="profileForm.errors.phone" class="mt-1 text-xs text-red-600">{{ profileForm.errors.phone }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Business Email</label>
                                <input v-model="profileForm.email" type="email" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                                <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-600">{{ profileForm.errors.email }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                            <input v-model="profileForm.website" type="url" placeholder="https://example.com" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                            <p v-if="profileForm.errors.website" class="mt-1 text-xs text-red-600">{{ profileForm.errors.website }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Logo URL</label>
                            <input v-model="profileForm.logo_url" type="url" placeholder="https://cdn.example.com/logo.png" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                            <p v-if="profileForm.errors.logo_url" class="mt-1 text-xs text-red-600">{{ profileForm.errors.logo_url }}</p>
                        </div>
                    </section>

                    <!-- Tax Identifiers -->
                    <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Tax Identifiers</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN</label>
                                <input v-model="profileForm.gstin" type="text" maxlength="15" placeholder="22AAAAA0000A1Z5" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm font-mono shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500 uppercase" @input="profileForm.gstin = profileForm.gstin.toUpperCase()" />
                                <p v-if="profileForm.errors.gstin" class="mt-1 text-xs text-red-600">{{ profileForm.errors.gstin }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">PAN</label>
                                <input v-model="profileForm.pan" type="text" maxlength="10" placeholder="ABCDE1234F" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm font-mono shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500 uppercase" @input="profileForm.pan = profileForm.pan.toUpperCase()" />
                                <p v-if="profileForm.errors.pan" class="mt-1 text-xs text-red-600">{{ profileForm.errors.pan }}</p>
                            </div>
                        </div>
                    </section>

                    <!-- Address -->
                    <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Address</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Line 1</label>
                            <input v-model="profileForm.address_line1" type="text" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                            <p v-if="profileForm.errors.address_line1" class="mt-1 text-xs text-red-600">{{ profileForm.errors.address_line1 }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Line 2</label>
                            <input v-model="profileForm.address_line2" type="text" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                            <p v-if="profileForm.errors.address_line2" class="mt-1 text-xs text-red-600">{{ profileForm.errors.address_line2 }}</p>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <input v-model="profileForm.city" type="text" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                                <p v-if="profileForm.errors.city" class="mt-1 text-xs text-red-600">{{ profileForm.errors.city }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                <input v-model="profileForm.state" type="text" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                                <p v-if="profileForm.errors.state" class="mt-1 text-xs text-red-600">{{ profileForm.errors.state }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pincode</label>
                                <input v-model="profileForm.pincode" type="text" maxlength="10" :disabled="!canEdit" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:bg-gray-50 disabled:text-gray-500" />
                                <p v-if="profileForm.errors.pincode" class="mt-1 text-xs text-red-600">{{ profileForm.errors.pincode }}</p>
                            </div>
                        </div>
                    </section>

                </form>

                <!-- Bank Accounts -->
                <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Bank Accounts</h3>
                        <button v-if="canEdit" @click="openAdd" type="button" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-xs font-medium hover:bg-violet-700 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Account
                        </button>
                    </div>

                    <div v-if="bankAccounts.length" class="space-y-3">
                        <div
                            v-for="account in bankAccounts"
                            :key="account.id"
                            class="flex items-start justify-between rounded-xl border border-gray-100 bg-gray-50 px-4 py-3"
                        >
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900">{{ account.bank_name }}</span>
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700 capitalize">{{ accountTypeLabel[account.account_type] }}</span>
                                    <span v-if="account.is_primary" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Primary</span>
                                </div>
                                <p class="text-xs text-gray-500 font-mono">{{ account.account_number }}</p>
                                <p class="text-xs text-gray-400">IFSC: {{ account.ifsc_code }}<template v-if="account.branch"> &middot; {{ account.branch }}</template></p>
                                <p class="text-xs text-gray-500">{{ account.account_holder_name }}</p>
                            </div>

                            <div v-if="canEdit" class="flex items-center gap-2 ml-4 shrink-0">
                                <button v-if="!account.is_primary" @click="setPrimary(account)" type="button" class="text-xs text-violet-600 hover:text-violet-800 transition">Set primary</button>
                                <button @click="openEdit(account)" type="button" class="text-xs text-gray-500 hover:text-gray-700 transition">Edit</button>
                                <button @click="deleteAccount(account)" type="button" class="text-xs text-red-500 hover:text-red-700 transition">Remove</button>
                            </div>
                        </div>
                    </div>

                    <p v-else class="text-sm text-gray-400 text-center py-4">No bank accounts added yet.</p>
                </section>

                <div v-if="canEdit" class="flex justify-end">
                    <button type="submit" form="profile-form" :disabled="profileForm.processing" class="px-6 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-50 transition">
                        Save Changes
                    </button>
                </div>

                </div>
            </div>
        </div>

        <!-- Bank account modal -->
        <div v-if="showBankModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h4 class="text-sm font-semibold text-gray-800">{{ editingAccount ? 'Edit Bank Account' : 'Add Bank Account' }}</h4>
                    <button @click="showBankModal = false" type="button" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitBank" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name <span class="text-red-500">*</span></label>
                        <input v-model="bankForm.bank_name" type="text" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="bankForm.errors.bank_name" class="mt-1 text-xs text-red-600">{{ bankForm.errors.bank_name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Account Holder Name <span class="text-red-500">*</span></label>
                        <input v-model="bankForm.account_holder_name" type="text" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="bankForm.errors.account_holder_name" class="mt-1 text-xs text-red-600">{{ bankForm.errors.account_holder_name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Number <span class="text-red-500">*</span></label>
                            <input v-model="bankForm.account_number" type="text" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm font-mono shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="bankForm.errors.account_number" class="mt-1 text-xs text-red-600">{{ bankForm.errors.account_number }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">IFSC Code <span class="text-red-500">*</span></label>
                            <input v-model="bankForm.ifsc_code" type="text" maxlength="11" placeholder="SBIN0001234" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm font-mono uppercase shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500" @input="bankForm.ifsc_code = bankForm.ifsc_code.toUpperCase()" />
                            <p v-if="bankForm.errors.ifsc_code" class="mt-1 text-xs text-red-600">{{ bankForm.errors.ifsc_code }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Type <span class="text-red-500">*</span></label>
                            <select v-model="bankForm.account_type" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="current">Current</option>
                                <option value="savings">Savings</option>
                                <option value="overdraft">Overdraft</option>
                            </select>
                            <p v-if="bankForm.errors.account_type" class="mt-1 text-xs text-red-600">{{ bankForm.errors.account_type }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                            <input v-model="bankForm.branch" type="text" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="bankForm.errors.branch" class="mt-1 text-xs text-red-600">{{ bankForm.errors.branch }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showBankModal = false" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                        <button type="submit" :disabled="bankForm.processing" class="px-5 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-50 transition">
                            {{ editingAccount ? 'Save Changes' : 'Add Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
