<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const $page = usePage()

const props = defineProps({
    stats: Object,
    rows:  Array,
    plans: Array,
})

// ── Helpers ───────────────────────────────────────────────────────────────────

const fmtRupees = (paise) => {
    if (!paise) return '₹0'
    return '₹' + (paise / 100).toLocaleString('en-IN', { maximumFractionDigits: 0 })
}

const statusClass = (status) => ({
    active:    'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    trialing:  'bg-blue-50 text-blue-700 ring-blue-600/20',
    pending:   'bg-amber-50 text-amber-700 ring-amber-600/20',
    halted:    'bg-red-50 text-red-700 ring-red-600/20',
    cancelled: 'bg-gray-100 text-gray-600 ring-gray-500/20',
    expired:   'bg-gray-100 text-gray-500 ring-gray-400/20',
})[status] ?? 'bg-gray-100 text-gray-500 ring-gray-400/20'

const typeLabel = (type) => type === 'ca_firm' ? 'CA Firm' : 'Business'

// ── Search / filter ───────────────────────────────────────────────────────────

const search        = ref('')
const filterStatus  = ref('')

const filtered = computed(() => {
    let rows = props.rows
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        rows = rows.filter(r => r.name.toLowerCase().includes(q))
    }
    if (filterStatus.value) {
        if (filterStatus.value === 'none') {
            rows = rows.filter(r => !r.subscription)
        } else {
            rows = rows.filter(r => r.subscription?.status === filterStatus.value)
        }
    }
    return rows
})

// ── Active modal ──────────────────────────────────────────────────────────────

const modal        = ref(null)   // 'change-plan' | 'rebill' | 'cancel' | 'grant-trial' | 'override'
const activeTenant = ref(null)

function openModal(type, tenant) {
    modal.value        = type
    activeTenant.value = tenant
    if (type === 'change-plan') {
        changePlanForm.plan_id = tenant.subscription?.plan_id ?? ''
    }
    if (type === 'rebill') {
        rebillForm.plan_id = tenant.subscription?.plan_id ?? ''
    }
    if (type === 'override') {
        overrideForm.status        = tenant.subscription?.status ?? 'active'
        overrideForm.plan_id       = tenant.subscription?.plan_id ?? ''
        overrideForm.trial_ends_at = tenant.subscription?.trial_ends_at ?? ''
    }
}

function closeModal() {
    modal.value        = null
    activeTenant.value = null
}

// ── Change Plan ───────────────────────────────────────────────────────────────

const changePlanForm = useForm({ plan_id: '' })

function submitChangePlan() {
    changePlanForm.post(route('admin.billing.change-plan', activeTenant.value.id), {
        onSuccess: closeModal,
    })
}

// ── Change Plan & Rebill ──────────────────────────────────────────────────────

const rebillForm    = useForm({ plan_id: '' })
const copiedRebill  = ref(false)

function submitRebill() {
    rebillForm.post(route('admin.billing.change-plan-rebill', activeTenant.value.id), {
        onSuccess: () => {
            modal.value = null
            activeTenant.value = null
        },
    })
}

function copyPaymentUrl() {
    const url = $page.props.flash?.payment_url
    if (!url) return
    navigator.clipboard.writeText(url)
    copiedRebill.value = true
    setTimeout(() => { copiedRebill.value = false }, 2000)
}

// ── Cancel ────────────────────────────────────────────────────────────────────

const cancelForm = useForm({})

function submitCancel() {
    cancelForm.post(route('admin.billing.cancel', activeTenant.value.id), {
        onSuccess: closeModal,
    })
}

// ── Grant Trial ───────────────────────────────────────────────────────────────

const trialForm = useForm({ trial_ends_at: '' })

function submitTrial() {
    trialForm.post(route('admin.billing.grant-trial', activeTenant.value.id), {
        onSuccess: closeModal,
    })
}

// ── Override Status ───────────────────────────────────────────────────────────

const overrideForm = useForm({
    status:        '',
    plan_id:       '',
    trial_ends_at: '',
})

function submitOverride() {
    overrideForm.post(route('admin.billing.override-status', activeTenant.value.id), {
        onSuccess: closeModal,
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Billing — All Tenants</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

            <!-- ── Stats row ── -->
            <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div v-for="(item, i) in [
                    { label: 'Tenants',  value: stats.total_tenants },
                    { label: 'Active',   value: stats.active },
                    { label: 'Trialing', value: stats.trialing },
                    { label: 'Halted',   value: stats.halted },
                    { label: 'No Sub',   value: stats.no_sub },
                    { label: 'MRR',      value: fmtRupees(stats.mrr_paise) },
                ]" :key="i"
                    class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs text-gray-500">{{ item.label }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-900">{{ item.value }}</p>
                </div>
            </div>

            <!-- ── Flash ── -->
            <div v-if="$page.props.flash?.success"
                class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <!-- ── Payment URL banner (after rebill) ── -->
            <div v-if="$page.props.flash?.payment_url"
                class="mb-4 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3">
                <p class="mb-2 text-sm font-medium text-violet-800">Payment link generated — share this with the tenant:</p>
                <div class="flex items-center gap-2">
                    <input
                        :value="$page.props.flash.payment_url"
                        readonly
                        class="flex-1 rounded-lg border border-violet-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:outline-none"
                    />
                    <button @click="copyPaymentUrl"
                        class="shrink-0 rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-700">
                        {{ copiedRebill ? 'Copied!' : 'Copy' }}
                    </button>
                    <a :href="$page.props.flash.payment_url" target="_blank"
                        class="shrink-0 rounded-lg border border-violet-300 bg-white px-3 py-1.5 text-sm text-violet-700 hover:bg-violet-50">
                        Open
                    </a>
                </div>
            </div>

            <!-- ── Filter bar ── -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search tenants…"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 sm:max-w-xs"
                />
                <select
                    v-model="filterStatus"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="trialing">Trialing</option>
                    <option value="pending">Pending</option>
                    <option value="halted">Halted</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                    <option value="none">No subscription</option>
                </select>
            </div>

            <!-- ── Table ── -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Tenant</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Plan</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Period End</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">Total Paid</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="filtered.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No tenants found.</td>
                        </tr>
                        <tr v-for="row in filtered" :key="row.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ row.name }}</p>
                                <p class="text-xs text-gray-400">{{ typeLabel(row.type) }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ row.subscription?.plan_name ?? '—' }}
                                <span v-if="row.subscription?.has_ai_addon"
                                    class="ml-1 rounded bg-violet-50 px-1.5 py-0.5 text-xs text-violet-600">+AI</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="row.subscription"
                                    :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', statusClass(row.subscription.status)]">
                                    {{ row.subscription.status }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <template v-if="row.subscription?.status === 'trialing'">
                                    {{ row.subscription.trial_ends_at ?? '—' }}
                                </template>
                                <template v-else>
                                    {{ row.subscription?.current_period_end ?? '—' }}
                                </template>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                {{ fmtRupees(row.total_paid_paise) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        v-if="row.subscription"
                                        @click="openModal('change-plan', row)"
                                        class="rounded px-2 py-1 text-xs font-medium text-violet-600 hover:bg-violet-50">
                                        Plan
                                    </button>
                                    <button
                                        v-if="row.subscription"
                                        @click="openModal('rebill', row)"
                                        class="rounded px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                        Rebill
                                    </button>
                                    <button
                                        v-if="row.subscription?.status === 'active'"
                                        @click="openModal('cancel', row)"
                                        class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                        Cancel
                                    </button>
                                    <button
                                        @click="openModal('grant-trial', row)"
                                        class="rounded px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">
                                        Trial
                                    </button>
                                    <button
                                        v-if="row.subscription"
                                        @click="openModal('override', row)"
                                        class="rounded px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">
                                        Override
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             MODALS
        ════════════════════════════════════════════════════════════════════ -->

        <!-- Backdrop -->
        <Teleport to="body">
            <div v-if="modal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeModal">

                <!-- ── Change Plan ── -->
                <div v-if="modal === 'change-plan'"
                    class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-base font-semibold text-gray-900">
                        Change Plan — {{ activeTenant?.name }}
                    </h3>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Select plan</label>
                    <select v-model="changePlanForm.plan_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <option value="" disabled>Choose a plan…</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">
                            {{ p.name }} — {{ fmtRupees(p.price) }}/mo
                        </option>
                    </select>
                    <p v-if="changePlanForm.errors.plan_id" class="mt-1 text-xs text-red-600">
                        {{ changePlanForm.errors.plan_id }}
                    </p>
                    <p class="mt-2 text-xs text-gray-400">
                        This is a local override — the Razorpay subscription is not changed.
                    </p>
                    <div class="mt-5 flex justify-end gap-3">
                        <button @click="closeModal" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</button>
                        <button @click="submitChangePlan" :disabled="changePlanForm.processing || !changePlanForm.plan_id"
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50">
                            {{ changePlanForm.processing ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </div>

                <!-- ── Change Plan & Rebill ── -->
                <div v-if="modal === 'rebill'"
                    class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="mb-1 text-base font-semibold text-gray-900">
                        Change Plan & Rebill — {{ activeTenant?.name }}
                    </h3>
                    <p class="mb-4 text-xs text-gray-500">
                        Cancels the current Razorpay subscription and creates a new one at the selected plan's price.
                        A payment link will be generated for you to share with the tenant.
                    </p>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Select new plan</label>
                    <select v-model="rebillForm.plan_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <option value="" disabled>Choose a plan…</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">
                            {{ p.name }} — {{ fmtRupees(p.price) }}/mo
                        </option>
                    </select>
                    <p v-if="rebillForm.errors.plan_id" class="mt-1 text-xs text-red-600">
                        {{ rebillForm.errors.plan_id }}
                    </p>
                    <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        The tenant's subscription status will be set to <strong>pending</strong> until they complete payment via the generated link.
                    </div>
                    <div class="mt-5 flex justify-end gap-3">
                        <button @click="closeModal" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</button>
                        <button @click="submitRebill" :disabled="rebillForm.processing || !rebillForm.plan_id"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {{ rebillForm.processing ? 'Processing…' : 'Change & Rebill' }}
                        </button>
                    </div>
                </div>

                <!-- ── Cancel Subscription ── -->
                <div v-if="modal === 'cancel'"
                    class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="mb-2 text-base font-semibold text-gray-900">Cancel Subscription</h3>
                    <p class="mb-4 text-sm text-gray-600">
                        Cancel the active subscription for
                        <span class="font-medium text-gray-900">{{ activeTenant?.name }}</span>?
                        It will remain accessible until the end of the current billing period.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button @click="closeModal" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">No, keep it</button>
                        <button @click="submitCancel" :disabled="cancelForm.processing"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                            Yes, cancel
                        </button>
                    </div>
                </div>

                <!-- ── Grant Trial ── -->
                <div v-if="modal === 'grant-trial'"
                    class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-base font-semibold text-gray-900">
                        Grant Trial — {{ activeTenant?.name }}
                    </h3>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Trial ends on</label>
                    <input v-model="trialForm.trial_ends_at" type="date"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <p v-if="trialForm.errors.trial_ends_at" class="mt-1 text-xs text-red-600">
                        {{ trialForm.errors.trial_ends_at }}
                    </p>
                    <p class="mt-2 text-xs text-gray-400">
                        Sets status to <em>trialing</em>. If no subscription exists, one is created using the ca_firm plan.
                    </p>
                    <div class="mt-5 flex justify-end gap-3">
                        <button @click="closeModal" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</button>
                        <button @click="submitTrial" :disabled="trialForm.processing || !trialForm.trial_ends_at"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            Grant Trial
                        </button>
                    </div>
                </div>

                <!-- ── Override Status ── -->
                <div v-if="modal === 'override'"
                    class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-base font-semibold text-gray-900">
                        Override — {{ activeTenant?.name }}
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                            <select v-model="overrideForm.status"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                                <option value="active">active</option>
                                <option value="trialing">trialing</option>
                                <option value="pending">pending</option>
                                <option value="halted">halted</option>
                                <option value="cancelled">cancelled</option>
                                <option value="expired">expired</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Plan (optional)</label>
                            <select v-model="overrideForm.plan_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                                <option value="">Keep current</option>
                                <option v-for="p in plans" :key="p.id" :value="p.id">
                                    {{ p.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="overrideForm.status === 'trialing'">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Trial ends on</label>
                            <input v-model="overrideForm.trial_ends_at" type="date"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-400">
                        Direct DB override — no Razorpay calls are made.
                    </p>
                    <div class="mt-5 flex justify-end gap-3">
                        <button @click="closeModal" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</button>
                        <button @click="submitOverride" :disabled="overrideForm.processing"
                            class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900 disabled:opacity-50">
                            Override
                        </button>
                    </div>
                </div>

            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
