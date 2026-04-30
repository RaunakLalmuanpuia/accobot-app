<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:       Object,
    ledgers:      Array,
    ledgerGroups: Array,
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

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending', cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',   cls: 'bg-gray-100 text-gray-400'   }
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
    parent_group:         '',
    is_bill_wise_on:      false,
    inventory_affected:   false,
    gstin_number:         '',
    pan_number:           '',
    gst_type:             '',
    mailing_name:         '',
    mobile_number:        '',
    contact_person:       '',
    contact_person_email:    '',
    contact_person_email_cc: '',
    contact_person_fax:      '',
    contact_person_website:  '',
    contact_person_mobile:   '',
    addresses:            [],
    state_name:           '',
    country_name:         '',
    pin_code:             '',
    credit_period:        '',
    credit_limit:         '',
    opening_balance:      '',
    opening_balance_type: '',
    aliases:              [],
    description:          '',
    notes:                '',
    bank_details:         [],
    bill_allocations:     [],
})

function onGroupChange() {
    const selected = props.ledgerGroups.find(g => g.name === form.group_name)
    form.parent_group = selected?.under_name ?? ''
}

function addAddress()     { form.addresses.push({ Address: '' }) }
function removeAddress(i) { form.addresses.splice(i, 1) }
function addAlias()       { form.aliases.push({ Alias: '' }) }
function removeAlias(i)   { form.aliases.splice(i, 1) }

function emptyBank() {
    return { BankName: '', IFSCode: '', AccountNumber: '', PaymentFavouring: '', TransactionName: '', TransactionType: '' }
}
function addBank() { form.bank_details.push(emptyBank()) }
function removeBank(i) { form.bank_details.splice(i, 1) }

function emptyBill() {
    return { Date: '', BillName: '', Amount: '', AmountType: 'Dr' }
}
function addBill() { form.bill_allocations.push(emptyBill()) }
function removeBill(i) { form.bill_allocations.splice(i, 1) }

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(ledger) {
    form.ledger_name           = ledger.ledger_name
    form.group_name            = ledger.group_name ?? ''
    form.parent_group          = ledger.parent_group ?? ''
    form.is_bill_wise_on       = ledger.is_bill_wise_on ?? false
    form.inventory_affected    = ledger.inventory_affected ?? false
    form.gstin_number          = ledger.gstin_number ?? ''
    form.pan_number            = ledger.pan_number ?? ''
    form.gst_type              = ledger.gst_type ?? ''
    form.mailing_name          = ledger.mailing_name ?? ''
    form.mobile_number         = ledger.mobile_number ?? ''
    form.contact_person        = ledger.contact_person ?? ''
    form.contact_person_email    = ledger.contact_person_email ?? ''
    form.contact_person_email_cc = ledger.contact_person_email_cc ?? ''
    form.contact_person_fax      = ledger.contact_person_fax ?? ''
    form.contact_person_website  = ledger.contact_person_website ?? ''
    form.contact_person_mobile   = ledger.contact_person_mobile ?? ''
    form.addresses             = ledger.addresses ? JSON.parse(JSON.stringify(ledger.addresses)) : []
    form.state_name            = ledger.state_name ?? ''
    form.country_name          = ledger.country_name ?? ''
    form.pin_code              = ledger.pin_code ?? ''
    form.credit_period         = ledger.credit_period ?? ''
    form.credit_limit          = ledger.credit_limit ?? ''
    form.opening_balance       = ledger.opening_balance ?? ''
    form.opening_balance_type  = ledger.opening_balance_type ?? ''
    form.aliases               = ledger.aliases ? JSON.parse(JSON.stringify(ledger.aliases)) : []
    form.description           = ledger.description ?? ''
    form.notes                 = ledger.notes ?? ''
    form.bank_details          = ledger.bank_details ? JSON.parse(JSON.stringify(ledger.bank_details)) : []
    form.bill_allocations      = ledger.bill_allocations ? JSON.parse(JSON.stringify(ledger.bill_allocations)) : []
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
                <div v-if="$page.props.flash?.info"
                     class="rounded-lg bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-800">
                    {{ $page.props.flash.info }}
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
                        <div class="col-span-1 text-right">Opening Bal.</div>
                        <div class="col-span-1 text-center">Mapped</div>
                        <div class="col-span-1 text-center">Tally</div>
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
                        <div class="col-span-1 text-right">
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
                        <div class="col-span-1 text-center">
                            <span :class="syncBadge(ledger.sync_status).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ syncBadge(ledger.sync_status).label }}
                            </span>
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
            <div class="relative z-50 w-full max-w-xl bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Ledger' : 'New Ledger' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">

                    <!-- Basic -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Ledger Name <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.ledger_name" type="text" placeholder="e.g. ABC Traders"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="form.errors.ledger_name" class="mt-1 text-xs text-red-500">{{ form.errors.ledger_name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Group <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.group_name" @change="onGroupChange"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="">— Select Group —</option>
                            <option v-for="g in ledgerGroups" :key="g.id" :value="g.name">{{ g.name }}</option>
                        </select>
                        <p v-if="form.parent_group" class="mt-1 text-xs text-gray-400">Under: {{ form.parent_group }}</p>
                        <p v-if="form.errors.group_name" class="mt-1 text-xs text-red-500">{{ form.errors.group_name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mailing Name</label>
                        <input v-model="form.mailing_name" type="text" placeholder="e.g. ABC Traders Pvt Ltd"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bill-wise</label>
                            <select v-model="form.is_bill_wise_on"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option :value="true">Yes</option>
                                <option :value="false">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Inventory Affected</label>
                            <select v-model="form.inventory_affected"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option :value="false">No</option>
                                <option :value="true">Yes</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tax -->
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

                    <!-- Contact -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                        <input v-model="form.contact_person" type="text" placeholder="e.g. Rajesh Kumar"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                            <input v-model="form.mobile_number" type="text" placeholder="9876543210"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Mobile</label>
                            <input v-model="form.contact_person_mobile" type="text" placeholder="9876543210"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                            <input v-model="form.contact_person_email" type="email" placeholder="e.g. contact@example.in"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.contact_person_email" class="mt-1 text-xs text-red-500">{{ form.errors.contact_person_email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email CC</label>
                            <input v-model="form.contact_person_email_cc" type="email" placeholder="e.g. cc@example.in"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.contact_person_email_cc" class="mt-1 text-xs text-red-500">{{ form.errors.contact_person_email_cc }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fax</label>
                            <input v-model="form.contact_person_fax" type="text" placeholder="e.g. 0111234567"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                            <input v-model="form.contact_person_website" type="url" placeholder="e.g. https://example.in"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Addresses</label>
                            <button type="button" @click="addAddress"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Line</button>
                        </div>
                        <div v-for="(a, i) in form.addresses" :key="i" class="flex gap-2 mb-2">
                            <input v-model="a.Address" type="text" placeholder="Address line"
                                   class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <button type="button" @click="removeAddress(i)"
                                    class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                        </div>
                        <p v-if="!form.addresses.length" class="text-xs text-gray-400">No address lines added.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <input v-model="form.state_name" type="text" placeholder="e.g. Maharashtra"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <input v-model="form.country_name" type="text" placeholder="e.g. India"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pin Code</label>
                        <input v-model="form.pin_code" type="text" placeholder="e.g. 400001"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <!-- Credit & Balance -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Credit Period (days)</label>
                            <input v-model="form.credit_period" type="number" min="0" placeholder="e.g. 30"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Credit Limit</label>
                            <input v-model="form.credit_limit" type="number" step="0.01" placeholder="0.00"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
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

                    <!-- Aliases -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Aliases</label>
                            <button type="button" @click="addAlias"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                        <div v-for="(al, i) in form.aliases" :key="i" class="flex gap-2 mb-2">
                            <input v-model="al.Alias" type="text" placeholder="Alias name"
                                   class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <button type="button" @click="removeAlias(i)"
                                    class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                        </div>
                        <p v-if="!form.aliases.length" class="text-xs text-gray-400">No aliases added.</p>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="2" placeholder="Optional description"
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="form.notes" rows="2" placeholder="Optional notes"
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
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

                    <!-- Bill Allocations -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Bill Allocations</label>
                            <button type="button" @click="addBill"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                        <div v-for="(bill, i) in form.bill_allocations" :key="i"
                             class="border border-gray-200 rounded-lg p-3 space-y-2 mb-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-500">Bill {{ i + 1 }}</span>
                                <button type="button" @click="removeBill(i)"
                                        class="text-xs text-red-400 hover:text-red-600">Remove</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Bill Name</label>
                                    <input v-model="bill.BillName" type="text" placeholder="e.g. INV-001"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Date</label>
                                    <input v-model="bill.Date" type="date"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Amount</label>
                                    <input v-model="bill.Amount" type="number" step="0.01" placeholder="0.00"
                                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Dr / Cr</label>
                                    <select v-model="bill.AmountType"
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-violet-500">
                                        <option value="Dr">Dr (Debit)</option>
                                        <option value="Cr">Cr (Credit)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <p v-if="!form.bill_allocations.length" class="text-xs text-gray-400">No bill allocations added.</p>
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
