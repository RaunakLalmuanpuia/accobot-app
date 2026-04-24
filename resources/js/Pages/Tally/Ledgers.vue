<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:           Object,
    ledgers:          Array,
    ledgerGroupNames: Array,
})

const canManage = hasPermission('integrations.manage')

// ── List ───────────────────────────────────────────────────────────────────────
const search      = ref('')
const groupFilter = ref('all')

const groups = computed(() => {
    const set = new Set(props.ledgers.map(l => l.group_name).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.ledgers
    if (groupFilter.value !== 'all') {
        list = list.filter(l => l.group_name === groupFilter.value)
    }
    const q = search.value.toLowerCase()
    if (q) {
        list = list.filter(l =>
            l.ledger_name.toLowerCase().includes(q) ||
            (l.gstin_number ?? '').toLowerCase().includes(q) ||
            (l.state_name ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

function mappingBadge(ledger) {
    if (ledger.mapped_client_id) return { label: 'Client', cls: 'bg-blue-100 text-blue-700' }
    if (ledger.mapped_vendor_id) return { label: 'Vendor', cls: 'bg-orange-100 text-orange-700' }
    return null
}

function formatAmount(v) {
    if (v === null || v === undefined) return '—'
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(v)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString()
}

// ── CRUD ───────────────────────────────────────────────────────────────────────
const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    ledger_name:          '',
    group_name:           '',
    gstin_number:         '',
    pan_number:           '',
    gst_type:             '',
    state_name:           '',
    mobile_number:        '',
    credit_limit:         '',
    opening_balance:      '',
    opening_balance_type: '',
    bank_details:         [],
})

function emptyBank() {
    return { BankName: '', IFSCode: '', AccountNumber: '', PaymentFavouring: '', TransactionName: '', TransactionType: '' }
}
function addBank() { form.bank_details.push(emptyBank()) }
function removeBank(i) { form.bank_details.splice(i, 1) }

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(ledger) {
    form.ledger_name          = ledger.ledger_name
    form.group_name           = ledger.group_name ?? ''
    form.gstin_number         = ledger.gstin_number ?? ''
    form.pan_number           = ledger.pan_number ?? ''
    form.gst_type             = ledger.gst_type ?? ''
    form.state_name           = ledger.state_name ?? ''
    form.mobile_number        = ledger.mobile_number ?? ''
    form.credit_limit         = ledger.credit_limit ?? ''
    form.opening_balance      = ledger.opening_balance ?? ''
    form.opening_balance_type = ledger.opening_balance_type ?? ''
    form.bank_details         = ledger.bank_details ? JSON.parse(JSON.stringify(ledger.bank_details)) : []
    form.clearErrors()
    modal.value = ledger
}

function closeModal() {
    modal.value = null
    form.reset()
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.ledgers.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.ledgers.update', { tenant: props.tenant.id, ledger: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(ledger) {
    const msg = ledger.tally_id
        ? `Mark "${ledger.ledger_name}" inactive and queue deletion in Tally?`
        : `Delete "${ledger.ledger_name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.ledgers.destroy', { tenant: props.tenant.id, ledger: ledger.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Ledgers</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ ledgers.length }} ledgers</p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Ledger
                    </button>
                    <Link :href="route('tally.ledger-groups.index', { tenant: tenant.id })"
                          class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                        Ledger Groups
                    </Link>
                    <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success"
                     class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search ledgers…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />

                    <select v-model="groupFilter"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="all">All Groups</option>
                        <option v-for="g in groups.slice(1)" :key="g" :value="g">{{ g }}</option>
                    </select>

                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-3">Ledger Name</div>
                        <div class="col-span-2">Group</div>
                        <div class="col-span-2">GSTIN</div>
                        <div class="col-span-1">State</div>
                        <div class="col-span-2 text-right">Opening Bal.</div>
                        <div class="col-span-1 text-center">Mapped</div>
                        <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="ledger in filtered" :key="ledger.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-3">
                            <p class="text-sm font-medium text-gray-900">{{ ledger.ledger_name }}</p>
                            <p v-if="ledger.mapped_client?.name || ledger.mapped_vendor?.name"
                               class="text-xs text-gray-400 truncate mt-0.5">
                                {{ ledger.mapped_client?.name ?? ledger.mapped_vendor?.name }}
                            </p>
                            <span :class="ledger.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium mt-1 inline-block">
                                {{ ledger.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-span-2 text-sm text-gray-500 truncate">{{ ledger.group_name ?? '—' }}</div>
                        <div class="col-span-2 text-xs text-gray-500 font-mono">{{ ledger.gstin_number ?? '—' }}</div>
                        <div class="col-span-1 text-sm text-gray-500 truncate">{{ ledger.state_name ?? '—' }}</div>
                        <div class="col-span-2 text-right">
                            <span v-if="ledger.opening_balance" class="text-sm text-gray-700 font-medium">
                                {{ formatAmount(ledger.opening_balance) }}
                                <span class="text-xs text-gray-400 ml-0.5">{{ ledger.opening_balance_type }}</span>
                            </span>
                            <span v-else class="text-sm text-gray-400">—</span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span v-if="mappingBadge(ledger)"
                                  :class="mappingBadge(ledger).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ mappingBadge(ledger).label }}
                            </span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </div>
                        <div class="col-span-1 text-right" v-if="canManage">
                            <button @click="openEdit(ledger)"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                            <button @click="destroy(ledger)"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No ledgers found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Slide-over -->
    <Teleport to="body">
        <div v-if="modal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeModal" />
            <div class="relative z-50 w-full max-w-md bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Ledger' : 'New Ledger' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Ledger Name <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.ledger_name" type="text" placeholder="e.g. ABC Traders"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="form.errors.ledger_name" class="mt-1 text-xs text-red-500">{{ form.errors.ledger_name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Group</label>
                        <input v-model="form.group_name" type="text"
                               list="ledger-group-options"
                               placeholder="e.g. Sundry Debtors"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <datalist id="ledger-group-options">
                            <option v-for="n in ledgerGroupNames" :key="n" :value="n" />
                        </datalist>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN</label>
                            <input v-model="form.gstin_number" type="text" placeholder="22AAAAA0000A1Z5"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PAN</label>
                            <input v-model="form.pan_number" type="text" placeholder="AAAAA0000A"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">GST Type</label>
                            <select v-model="form.gst_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option>Regular</option>
                                <option>Composition</option>
                                <option>Unregistered</option>
                                <option>Consumer</option>
                                <option>Overseas</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <input v-model="form.state_name" type="text" placeholder="e.g. Maharashtra"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                        <input v-model="form.mobile_number" type="text" placeholder="e.g. 9876543210"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Opening Balance</label>
                            <input v-model="form.opening_balance" type="number" step="0.01" placeholder="0.00"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Balance Type</label>
                            <select v-model="form.opening_balance_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Dr / Cr —</option>
                                <option value="Dr">Dr (Debit)</option>
                                <option value="Cr">Cr (Credit)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Bank Accounts</label>
                            <button type="button" @click="addBank"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                        <div v-for="(b, i) in form.bank_details" :key="i"
                             class="border border-gray-200 rounded-lg p-3 space-y-2 mb-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-500">Account {{ i + 1 }}</span>
                                <button type="button" @click="removeBank(i)"
                                        class="text-xs text-red-400 hover:text-red-600">Remove</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Bank Name</label>
                                    <input v-model="b.BankName" type="text" placeholder="e.g. ICICI Bank"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">IFSC Code</label>
                                    <input v-model="b.IFSCode" type="text" placeholder="e.g. ICIC0001234"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">Account Number</label>
                                <input v-model="b.AccountNumber" type="text" placeholder="e.g. 1234567890"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">Payment Favouring</label>
                                <input v-model="b.PaymentFavouring" type="text" placeholder="e.g. ABC Traders"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Transaction Name</label>
                                    <input v-model="b.TransactionName" type="text" placeholder="e.g. Primary"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Transaction Type</label>
                                    <select v-model="b.TransactionType"
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                        <option value="">— Select —</option>
                                        <option>Inter Bank Transfer</option>
                                        <option>Same Bank Transfer</option>
                                        <option>Cash</option>
                                        <option>Cheque</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <p v-if="!form.bank_details.length" class="text-xs text-gray-400">No bank accounts added.</p>
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditing ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
