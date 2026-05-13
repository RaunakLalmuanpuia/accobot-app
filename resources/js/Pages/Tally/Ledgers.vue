<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:       Object,
    ledgers:      Array,
    ledgerGroups: Array,
})

const canManage = hasPermission('integrations.manage')

// ── Group classification (matches Tally Prime field visibility rules) ──────────
const PARTY_GROUPS     = ['Sundry Debtors', 'Sundry Creditors', 'Loans & Advances (Asset)', 'Branch / Divisions']
const BANK_GROUPS      = ['Bank Accounts', 'Bank OD A/c']
const BILLWISE_GROUPS  = ['Sundry Debtors', 'Sundry Creditors', 'Loans & Advances (Asset)', 'Secured Loans', 'Unsecured Loans', 'Bank Accounts', 'Bank OD A/c', 'Deposits (Asset)']
const CREDIT_GROUPS    = ['Sundry Debtors', 'Sundry Creditors']
const INVENTORY_GROUPS = ['Sales Accounts', 'Purchase Accounts', 'Direct Expenses', 'Direct Incomes', 'Indirect Expenses', 'Indirect Incomes', 'Sundry Debtors', 'Sundry Creditors']
const GST_GROUPS       = ['Sundry Debtors', 'Sundry Creditors', 'Branch / Divisions', 'Loans & Advances (Asset)']
const INTEREST_GROUPS  = ['Sundry Debtors', 'Sundry Creditors', 'Loans & Advances (Asset)', 'Secured Loans', 'Unsecured Loans', 'Bank Accounts', 'Bank OD A/c', 'Deposits (Asset)']
const TDS_GROUPS       = ['Sundry Debtors', 'Sundry Creditors', 'Loans & Advances (Asset)']
const ALL_CLASSIFIED   = [...new Set([...PARTY_GROUPS, ...BANK_GROUPS, ...BILLWISE_GROUPS, ...CREDIT_GROUPS, ...INVENTORY_GROUPS, ...GST_GROUPS, ...INTEREST_GROUPS, ...TDS_GROUPS])]

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
    // Core
    ledger_name:          '',
    group_name:           '',
    parent_group:         '',
    mailing_name:         '',
    aliases:              [],
    // Behaviour
    opening_balance:      '',
    opening_balance_type: 'Dr',
    is_bill_wise_on:      false,
    credit_period:        '',
    credit_limit:         '',
    inventory_affected:   false,
    // GST / Tax Registration
    gstin_number:         '',
    pan_number:           '',
    gst_type:             '',
    is_rcm_applicable:    false,
    // TDS
    is_tds_applicable:    false,
    tds_deductee_type:    '',
    // Interest
    is_interest_on:                  false,
    type_of_interest_on:             'Voucher Date',
    is_interest_on_bill_wise:        false,
    override_interest:               false,
    interest_incl_day_of_addition:   true,
    interest_incl_day_of_deduction:  true,
    // Address & Contact
    addresses:                [],
    state_name:               '',
    country_name:             'India',
    pin_code:                 '',
    mobile_number:            '',
    contact_person:           '',
    contact_person_mobile:    '',
    contact_person_email:     '',
    contact_person_email_cc:  '',
    contact_person_fax:       '',
    contact_person_website:   '',
    // Bank Details
    bank_details:         [],
    // Bill Allocations
    bill_allocations:     [],
    // Other
    description:          '',
    notes:                '',
})

// ── Group-based field visibility ───────────────────────────────────────────────
const selectedGroupObj = computed(() =>
    props.ledgerGroups.find(g => g.name === form.group_name)
)

// Resolve to effective standard group name (custom sub-groups inherit from parent)
const effectiveGroup = computed(() => {
    const g = selectedGroupObj.value
    if (!g) return ''
    if (ALL_CLASSIFIED.includes(g.name)) return g.name
    return g.under_name ?? ''
})

const isPartyLedger        = computed(() => PARTY_GROUPS.includes(effectiveGroup.value))
const isBankLedger         = computed(() => BANK_GROUPS.includes(effectiveGroup.value))
const showBillWise         = computed(() => BILLWISE_GROUPS.includes(effectiveGroup.value))
const showCreditTerms      = computed(() => CREDIT_GROUPS.includes(effectiveGroup.value))
const showInventoryAffected= computed(() => INVENTORY_GROUPS.includes(effectiveGroup.value))
const showGstSection       = computed(() => GST_GROUPS.includes(effectiveGroup.value))
const showInterestSection  = computed(() => INTEREST_GROUPS.includes(effectiveGroup.value))
const showTdsSection       = computed(() => TDS_GROUPS.includes(effectiveGroup.value))
const showAddressSection   = computed(() => isPartyLedger.value || isBankLedger.value)
const showBankSection      = computed(() => isBankLedger.value)
const showBillAllocations  = computed(() => showBillWise.value && form.is_bill_wise_on)

// ── Watchers ───────────────────────────────────────────────────────────────────
function onGroupChange() {
    const selected = selectedGroupObj.value
    form.parent_group = selected?.under_name ?? ''
    // Auto-fill mailing name from ledger name when switching to a party group
    if (isPartyLedger.value && !form.mailing_name) {
        form.mailing_name = form.ledger_name
    }
}

watch(() => form.ledger_name, (val) => {
    // Keep mailing_name in sync if it's still the default (same as ledger name)
    if (!isEditing.value && isPartyLedger.value) {
        form.mailing_name = val
    }
})

// ── Array helpers ──────────────────────────────────────────────────────────────
function addAddress()     { form.addresses.push({ Address: '' }) }
function removeAddress(i) { form.addresses.splice(i, 1) }
function addAlias()       { form.aliases.push({ Alias: '' }) }
function removeAlias(i)   { form.aliases.splice(i, 1) }

function emptyBank() {
    return { BankName: '', IFSCode: '', AccountNumber: '', PaymentFavouring: '', TransactionName: '', TransactionType: '' }
}
function addBank()        { form.bank_details.push(emptyBank()) }
function removeBank(i)    { form.bank_details.splice(i, 1) }

function emptyBill()      { return { Date: '', BillName: '', Amount: '', AmountType: 'Dr' } }
function addBill()        { form.bill_allocations.push(emptyBill()) }
function removeBill(i)    { form.bill_allocations.splice(i, 1) }

// ── Modal helpers ──────────────────────────────────────────────────────────────
function openCreate() {
    form.reset()
    form.opening_balance_type = 'Dr'
    form.country_name         = 'India'
    form.type_of_interest_on  = 'Voucher Date'
    form.interest_incl_day_of_addition  = true
    form.interest_incl_day_of_deduction = true
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(ledger) {
    form.ledger_name                     = ledger.ledger_name
    form.group_name                      = ledger.group_name ?? ''
    form.parent_group                    = ledger.parent_group ?? ''
    form.mailing_name                    = ledger.mailing_name ?? ''
    form.aliases                         = ledger.aliases ? JSON.parse(JSON.stringify(ledger.aliases)) : []
    form.opening_balance                 = ledger.opening_balance ?? ''
    form.opening_balance_type            = ledger.opening_balance_type ?? 'Dr'
    form.is_bill_wise_on                 = ledger.is_bill_wise_on ?? false
    form.credit_period                   = ledger.credit_period ?? ''
    form.credit_limit                    = ledger.credit_limit ?? ''
    form.inventory_affected              = ledger.inventory_affected ?? false
    form.gstin_number                    = ledger.gstin_number ?? ''
    form.pan_number                      = ledger.pan_number ?? ''
    form.gst_type                        = ledger.gst_type ?? ''
    form.is_rcm_applicable               = ledger.is_rcm_applicable ?? false
    form.is_tds_applicable               = ledger.is_tds_applicable ?? false
    form.tds_deductee_type               = ledger.tds_deductee_type ?? ''
    form.is_interest_on                  = ledger.is_interest_on ?? false
    form.type_of_interest_on             = ledger.type_of_interest_on ?? 'Voucher Date'
    form.is_interest_on_bill_wise        = ledger.is_interest_on_bill_wise ?? false
    form.override_interest               = ledger.override_interest ?? false
    form.interest_incl_day_of_addition   = ledger.interest_incl_day_of_addition ?? true
    form.interest_incl_day_of_deduction  = ledger.interest_incl_day_of_deduction ?? true
    form.addresses                       = ledger.addresses ? JSON.parse(JSON.stringify(ledger.addresses)) : []
    form.state_name                      = ledger.state_name ?? ''
    form.country_name                    = ledger.country_name ?? 'India'
    form.pin_code                        = ledger.pin_code ?? ''
    form.mobile_number                   = ledger.mobile_number ?? ''
    form.contact_person                  = ledger.contact_person ?? ''
    form.contact_person_mobile           = ledger.contact_person_mobile ?? ''
    form.contact_person_email            = ledger.contact_person_email ?? ''
    form.contact_person_email_cc         = ledger.contact_person_email_cc ?? ''
    form.contact_person_fax              = ledger.contact_person_fax ?? ''
    form.contact_person_website          = ledger.contact_person_website ?? ''
    form.bank_details                    = ledger.bank_details ? JSON.parse(JSON.stringify(ledger.bank_details)) : []
    form.bill_allocations                = ledger.bill_allocations ? JSON.parse(JSON.stringify(ledger.bill_allocations)) : []
    form.description                     = ledger.description ?? ''
    form.notes                           = ledger.notes ?? ''
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
                            <span v-if="mappingBadge(ledger)" :class="mappingBadge(ledger).cls"
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
            <div class="relative z-50 w-full max-w-2xl bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Ledger' : 'New Ledger' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto divide-y divide-gray-100">

                    <!-- ── Name & Classification ─────────────────────────── -->
                    <div class="tally-section-header">Name & Classification</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="form.ledger_name" type="text" placeholder="e.g. ABC Traders" class="tally-field" />
                            <p v-if="form.errors.ledger_name" class="mt-0.5 text-xs text-red-500">{{ form.errors.ledger_name }}</p>
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">Under (Group) <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <select v-model="form.group_name" @change="onGroupChange" class="tally-field">
                                <option value="">— Select Group —</option>
                                <option v-for="g in ledgerGroups" :key="g.id" :value="g.name">{{ g.name }}</option>
                            </select>
                            <p v-if="form.parent_group" class="mt-0.5 text-xs text-gray-400">Parent: {{ form.parent_group }}</p>
                            <p v-if="form.errors.group_name" class="mt-0.5 text-xs text-red-500">{{ form.errors.group_name }}</p>
                        </div>
                    </div>

                    <div v-if="showAddressSection || form.group_name" class="tally-row">
                        <span class="tally-label">Mailing Name</span>
                        <div class="tally-input">
                            <input v-model="form.mailing_name" type="text" placeholder="e.g. ABC Traders Pvt Ltd" class="tally-field" />
                        </div>
                    </div>

                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Alias(es)</span>
                        <div class="tally-input">
                            <div v-for="(al, i) in form.aliases" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="al.Alias" type="text" placeholder="Alias"
                                       class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="removeAlias(i)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="addAlias"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add</button>
                        </div>
                    </div>

                    <!-- ── Behaviour ─────────────────────────────────────── -->
                    <div class="tally-section-header">Behaviour</div>

                    <div class="tally-row">
                        <span class="tally-label">Opening Balance</span>
                        <div class="tally-input flex gap-2">
                            <input v-model="form.opening_balance" type="number" step="0.01" placeholder="0.00"
                                   class="tally-field flex-1" />
                            <select v-model="form.opening_balance_type"
                                    class="w-24 text-sm border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-violet-400">
                                <option value="Dr">Dr</option>
                                <option value="Cr">Cr</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="showBillWise" class="tally-row">
                        <span class="tally-label">Bill-wise Details <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <select v-model="form.is_bill_wise_on" class="tally-field">
                                <option :value="true">Yes</option>
                                <option :value="false">No</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="showCreditTerms" class="tally-row">
                        <span class="tally-label">Credit Period (days)</span>
                        <div class="tally-input">
                            <input v-model="form.credit_period" type="number" min="0" placeholder="e.g. 30" class="tally-field" />
                        </div>
                    </div>

                    <div v-if="showCreditTerms" class="tally-row">
                        <span class="tally-label">Credit Limit</span>
                        <div class="tally-input">
                            <input v-model="form.credit_limit" type="number" step="0.01" placeholder="0.00" class="tally-field" />
                        </div>
                    </div>

                    <div v-if="showInventoryAffected" class="tally-row">
                        <span class="tally-label">Inventory Affected</span>
                        <div class="tally-input">
                            <select v-model="form.inventory_affected" class="tally-field">
                                <option :value="false">No</option>
                                <option :value="true">Yes</option>
                            </select>
                        </div>
                    </div>

                    <!-- ── GST / Tax Registration ─────────────────────────── -->
                    <template v-if="showGstSection">
                        <div class="tally-section-header">GST / Tax Registration</div>

                        <div class="tally-row">
                            <span class="tally-label">GSTIN</span>
                            <div class="tally-input">
                                <input v-model="form.gstin_number" type="text" placeholder="22AAAAA0000A1Z5" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">PAN</span>
                            <div class="tally-input">
                                <input v-model="form.pan_number" type="text" placeholder="AAAAA0000A" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">GST Reg. Type <span class="text-red-500">*</span></span>
                            <div class="tally-input">
                                <select v-model="form.gst_type" class="tally-field">
                                    <option value="">— Select —</option>
                                    <option>Regular</option>
                                    <option>Composition</option>
                                    <option>Unregistered</option>
                                    <option>Consumer</option>
                                    <option>Overseas</option>
                                    <option>Unknown</option>
                                </select>
                                <p v-if="form.errors.gst_type" class="mt-0.5 text-xs text-red-500">{{ form.errors.gst_type }}</p>
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Is RCM Applicable</span>
                            <div class="tally-input">
                                <select v-model="form.is_rcm_applicable" class="tally-field">
                                    <option :value="false">No</option>
                                    <option :value="true">Yes</option>
                                </select>
                            </div>
                        </div>
                    </template>

                    <!-- ── TDS ───────────────────────────────────────────── -->
                    <template v-if="showTdsSection">
                        <div class="tally-section-header">TDS</div>

                        <div class="tally-row">
                            <span class="tally-label">Is TDS Applicable</span>
                            <div class="tally-input">
                                <select v-model="form.is_tds_applicable" class="tally-field">
                                    <option :value="false">No</option>
                                    <option :value="true">Yes</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="form.is_tds_applicable" class="tally-row">
                            <span class="tally-label">Deductee Type <span class="text-red-500">*</span></span>
                            <div class="tally-input">
                                <select v-model="form.tds_deductee_type" class="tally-field">
                                    <option value="">— Select —</option>
                                    <option>Company Deductee</option>
                                    <option>Non Company Deductee</option>
                                </select>
                                <p v-if="form.errors.tds_deductee_type" class="mt-0.5 text-xs text-red-500">{{ form.errors.tds_deductee_type }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- ── Interest ──────────────────────────────────────── -->
                    <template v-if="showInterestSection">
                        <div class="tally-section-header">Interest</div>

                        <div class="tally-row">
                            <span class="tally-label">Activate Interest</span>
                            <div class="tally-input">
                                <select v-model="form.is_interest_on" class="tally-field">
                                    <option :value="false">No</option>
                                    <option :value="true">Yes</option>
                                </select>
                            </div>
                        </div>

                        <template v-if="form.is_interest_on">
                            <div class="tally-row">
                                <span class="tally-label">Calculate On</span>
                                <div class="tally-input">
                                    <select v-model="form.type_of_interest_on" class="tally-field">
                                        <option>Voucher Date</option>
                                        <option>Transaction Date</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tally-row">
                                <span class="tally-label">Interest on Bill-wise</span>
                                <div class="tally-input">
                                    <select v-model="form.is_interest_on_bill_wise" class="tally-field">
                                        <option :value="false">No</option>
                                        <option :value="true">Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tally-row">
                                <span class="tally-label">Override Parameters</span>
                                <div class="tally-input">
                                    <select v-model="form.override_interest" class="tally-field">
                                        <option :value="false">No</option>
                                        <option :value="true">Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tally-row">
                                <span class="tally-label">Incl. Day of Addition</span>
                                <div class="tally-input">
                                    <select v-model="form.interest_incl_day_of_addition" class="tally-field">
                                        <option :value="true">Yes</option>
                                        <option :value="false">No</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tally-row">
                                <span class="tally-label">Incl. Day of Deduction</span>
                                <div class="tally-input">
                                    <select v-model="form.interest_incl_day_of_deduction" class="tally-field">
                                        <option :value="true">Yes</option>
                                        <option :value="false">No</option>
                                    </select>
                                </div>
                            </div>
                        </template>
                    </template>

                    <!-- ── Address & Contact ─────────────────────────────── -->
                    <template v-if="showAddressSection">
                        <div class="tally-section-header">Address & Contact</div>

                        <div class="tally-row items-start">
                            <span class="tally-label pt-2.5">Address</span>
                            <div class="tally-input">
                                <div v-for="(a, i) in form.addresses" :key="i" class="flex gap-2 mb-1.5">
                                    <input v-model="a.Address" type="text" placeholder="Address line"
                                           class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                    <button type="button" @click="removeAddress(i)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                                </div>
                                <button type="button" @click="addAddress"
                                        class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Line</button>
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">State</span>
                            <div class="tally-input">
                                <input v-model="form.state_name" type="text" placeholder="e.g. Maharashtra" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Country</span>
                            <div class="tally-input">
                                <input v-model="form.country_name" type="text" placeholder="India" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">PIN Code</span>
                            <div class="tally-input">
                                <input v-model="form.pin_code" type="text" placeholder="400001" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Mobile</span>
                            <div class="tally-input">
                                <input v-model="form.mobile_number" type="text" placeholder="9876543210" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Contact Person</span>
                            <div class="tally-input">
                                <input v-model="form.contact_person" type="text" placeholder="e.g. Rajesh Kumar" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Contact Mobile</span>
                            <div class="tally-input">
                                <input v-model="form.contact_person_mobile" type="text" placeholder="9876543210" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Contact Email</span>
                            <div class="tally-input">
                                <input v-model="form.contact_person_email" type="email" placeholder="contact@example.in" class="tally-field" />
                                <p v-if="form.errors.contact_person_email" class="mt-0.5 text-xs text-red-500">{{ form.errors.contact_person_email }}</p>
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Email CC</span>
                            <div class="tally-input">
                                <input v-model="form.contact_person_email_cc" type="email" placeholder="cc@example.in" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Fax</span>
                            <div class="tally-input">
                                <input v-model="form.contact_person_fax" type="text" placeholder="0111234567" class="tally-field" />
                            </div>
                        </div>

                        <div class="tally-row">
                            <span class="tally-label">Website</span>
                            <div class="tally-input">
                                <input v-model="form.contact_person_website" type="url" placeholder="https://example.in" class="tally-field" />
                            </div>
                        </div>
                    </template>

                    <!-- ── Bank Details ──────────────────────────────────── -->
                    <template v-if="showBankSection">
                        <div class="tally-section-header flex items-center justify-between pr-4">
                            <span>Bank Details</span>
                            <button type="button" @click="addBank"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium normal-case tracking-normal">+ Add Account</button>
                        </div>

                        <div v-if="!form.bank_details.length" class="px-4 py-3 text-xs text-gray-400">No bank accounts added.</div>

                        <div v-for="(b, i) in form.bank_details" :key="i" class="border-b border-gray-100">
                            <div class="flex items-center justify-between px-4 py-2 bg-gray-50">
                                <span class="text-xs font-medium text-gray-500">Account {{ i + 1 }}</span>
                                <button type="button" @click="removeBank(i)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Bank Name</span>
                                <div class="tally-input"><input v-model="b.BankName" type="text" placeholder="e.g. ICICI Bank" class="tally-field" /></div>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">IFSC Code</span>
                                <div class="tally-input"><input v-model="b.IFSCode" type="text" placeholder="ICIC0001234" class="tally-field" /></div>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Account Number</span>
                                <div class="tally-input"><input v-model="b.AccountNumber" type="text" placeholder="1234567890" class="tally-field" /></div>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Payment Favouring</span>
                                <div class="tally-input"><input v-model="b.PaymentFavouring" type="text" placeholder="e.g. ABC Traders" class="tally-field" /></div>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Transaction Name</span>
                                <div class="tally-input"><input v-model="b.TransactionName" type="text" placeholder="e.g. Primary" class="tally-field" /></div>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Transaction Type</span>
                                <div class="tally-input">
                                    <select v-model="b.TransactionType" class="tally-field">
                                        <option value="">— Select —</option>
                                        <option>Inter Bank Transfer</option>
                                        <option>Same Bank Transfer</option>
                                        <option>NEFT</option>
                                        <option>RTGS</option>
                                        <option>UPI</option>
                                        <option>Cheque</option>
                                        <option>Cash</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ── Opening Bill Allocations ──────────────────────── -->
                    <template v-if="showBillAllocations">
                        <div class="tally-section-header flex items-center justify-between pr-4">
                            <span>Opening Bill Allocations</span>
                            <button type="button" @click="addBill"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium normal-case tracking-normal">+ Add Bill</button>
                        </div>

                        <div v-if="!form.bill_allocations.length" class="px-4 py-3 text-xs text-gray-400">No bill allocations added.</div>

                        <div v-for="(bill, i) in form.bill_allocations" :key="i" class="border-b border-gray-100">
                            <div class="flex items-center justify-between px-4 py-2 bg-gray-50">
                                <span class="text-xs font-medium text-gray-500">Bill {{ i + 1 }}</span>
                                <button type="button" @click="removeBill(i)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Bill Name / Ref</span>
                                <div class="tally-input"><input v-model="bill.BillName" type="text" placeholder="e.g. INV-001" class="tally-field" /></div>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Date</span>
                                <div class="tally-input"><input v-model="bill.Date" type="date" class="tally-field" /></div>
                            </div>
                            <div class="tally-row">
                                <span class="tally-label">Amount</span>
                                <div class="tally-input flex gap-2">
                                    <input v-model="bill.Amount" type="number" step="0.01" placeholder="0.00" class="tally-field flex-1" />
                                    <select v-model="bill.AmountType"
                                            class="w-20 text-sm border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-violet-400">
                                        <option value="Dr">Dr</option>
                                        <option value="Cr">Cr</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ── Other ─────────────────────────────────────────── -->
                    <div class="tally-section-header">Other</div>

                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Description</span>
                        <div class="tally-input">
                            <textarea v-model="form.description" rows="2" placeholder="Optional description"
                                      class="tally-field resize-none" />
                        </div>
                    </div>

                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Notes</span>
                        <div class="tally-input">
                            <textarea v-model="form.notes" rows="2" placeholder="Optional notes"
                                      class="tally-field resize-none" />
                        </div>
                    </div>

                    <!-- ── Submit ─────────────────────────────────────────── -->
                    <div class="flex gap-3 px-4 py-4">
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

<style scoped>
.tally-row   { @apply flex items-stretch border-b border-gray-100; }
.tally-label { @apply w-48 shrink-0 text-sm text-gray-600 bg-gray-50 px-4 py-2.5 border-r border-gray-100 flex items-center; }
.tally-input { @apply flex-1 px-3 py-2; }
.tally-field { @apply w-full text-sm border-0 outline-none focus:ring-1 focus:ring-violet-400 rounded bg-transparent; }
.tally-section-header { @apply bg-violet-50 text-violet-700 text-xs font-semibold uppercase tracking-wider px-4 py-1.5 border-b border-violet-100; }
</style>
