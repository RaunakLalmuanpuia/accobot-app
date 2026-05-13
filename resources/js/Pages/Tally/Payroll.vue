<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:          Object,
    employees:       Array,
    employeeGroups:  Array,
    payHeads:        Array,
    attendanceTypes: Array,
})

const canManage = hasPermission('integrations.manage')
const activeTab = ref('employees')

const tabs = [
    { key: 'employees',       label: 'Employees' },
    { key: 'employeeGroups',  label: 'Employee Groups' },
    { key: 'payHeads',        label: 'Pay Heads' },
    { key: 'attendanceTypes', label: 'Attendance Types' },
]

// ── Employees ──────────────────────────────────────────────────────────────────
const empSearch      = ref('')
const empGroupFilter = ref('all')

const empGroups = computed(() => {
    const set = new Set(props.employees.map(e => e.parent).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filteredEmployees = computed(() => {
    let list = props.employees
    if (empGroupFilter.value !== 'all') {
        list = list.filter(e => e.parent === empGroupFilter.value)
    }
    const q = empSearch.value.toLowerCase()
    if (q) {
        list = list.filter(e =>
            e.name.toLowerCase().includes(q) ||
            (e.employee_number ?? '').toLowerCase().includes(q) ||
            (e.designation ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

// ── Pay Heads ──────────────────────────────────────────────────────────────────
const paySearch     = ref('')
const payTypeFilter = ref('all')

const payHeadTypes = computed(() => {
    const set = new Set(props.payHeads.map(p => p.pay_type).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filteredPayHeads = computed(() => {
    let list = props.payHeads
    if (payTypeFilter.value !== 'all') {
        list = list.filter(p => p.pay_type === payTypeFilter.value)
    }
    const q = paySearch.value.toLowerCase()
    if (q) {
        list = list.filter(p => p.name.toLowerCase().includes(q))
    }
    return list
})

// ── Attendance Types ───────────────────────────────────────────────────────────
const attSearch = ref('')

const filteredAttendance = computed(() => {
    const q = attSearch.value.toLowerCase()
    if (!q) return props.attendanceTypes
    return props.attendanceTypes.filter(t =>
        t.name.toLowerCase().includes(q) ||
        (t.attendance_type ?? '').toLowerCase().includes(q)
    )
})

// ── Colours ────────────────────────────────────────────────────────────────────
const payHeadTypeColors = {
    'Earnings for Employees':               'bg-green-100 text-green-700',
    'Employees\' Statutory Deductions':     'bg-red-100 text-red-700',
    'Employer\'s Statutory Contributions':  'bg-blue-100 text-blue-700',
    'Deductions':                           'bg-orange-100 text-orange-700',
}

function payHeadTypeColor(type) {
    return payHeadTypeColors[type] ?? 'bg-gray-100 text-gray-600'
}

const attendanceTypeColors = {
    'Attendance':        'bg-green-100 text-green-700',
    'Leave with Pay':    'bg-blue-100 text-blue-700',
    'Leave without Pay': 'bg-red-100 text-red-700',
    'Productivity':      'bg-violet-100 text-violet-700',
}

function attTypeColor(type) {
    return attendanceTypeColors[type] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending', cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',   cls: 'bg-gray-100 text-gray-400'   }
}

// ── Employee CRUD ──────────────────────────────────────────────────────────────
const empModal     = ref(null)
const isEditingEmp = computed(() => empModal.value && empModal.value !== 'create')

const empForm = useForm({
    name:               '',
    employee_number:    '',
    parent:             '',
    designation:        '',
    employee_function:  '',
    location:           '',
    gender:             '',
    date_of_joining:    '',
    date_of_leaving:    '',
    date_of_birth:      '',
    father_name:        '',
    spouse_name:        '',
    contact_number:     '',
    email_address:      '',
    address:            [],
    aliases:            [],
})

const empGroupOptions = computed(() =>
    props.employeeGroups.filter(g => g.is_active).map(g => g.name)
)

function openCreateEmp() {
    empForm.reset()
    empForm.clearErrors()
    empModal.value = 'create'
}

function openEditEmp(emp) {
    empForm.name               = emp.name
    empForm.employee_number    = emp.employee_number ?? ''
    empForm.parent             = emp.parent ?? ''
    empForm.designation        = emp.designation ?? ''
    empForm.employee_function  = emp.employee_function ?? ''
    empForm.location           = emp.location ?? ''
    empForm.gender             = emp.gender ?? ''
    empForm.date_of_joining    = emp.date_of_joining ?? ''
    empForm.date_of_leaving    = emp.date_of_leaving ?? ''
    empForm.date_of_birth      = emp.date_of_birth ?? ''
    empForm.father_name        = emp.father_name ?? ''
    empForm.spouse_name        = emp.spouse_name ?? ''
    empForm.contact_number     = emp.contact_number ?? ''
    empForm.email_address      = emp.email_address ?? ''
    empForm.address            = emp.address ? [...emp.address] : []
    empForm.aliases            = emp.aliases ? [...emp.aliases] : []
    empForm.clearErrors()
    empModal.value = emp
}

function closeEmpModal() {
    empModal.value = null
    empForm.reset()
}

function submitEmp() {
    if (!isEditingEmp.value) {
        empForm.post(route('tally.employees.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeEmpModal(),
        })
    } else {
        empForm.put(route('tally.employees.update', { tenant: props.tenant.id, employee: empModal.value.id }), {
            onSuccess: () => closeEmpModal(),
        })
    }
}

function destroyEmp(emp) {
    const msg = emp.tally_id
        ? `Mark "${emp.name}" inactive and queue deletion in Tally?`
        : `Delete "${emp.name}"? They were never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.employees.destroy', { tenant: props.tenant.id, employee: emp.id }))
}

// ── Employee Group CRUD ────────────────────────────────────────────────────────
const grpModal     = ref(null)
const isEditingGrp = computed(() => grpModal.value && grpModal.value !== 'create')

const grpForm = useForm({
    name:                 '',
    under:                'Primary',
    cost_centre_category: '',
    aliases:              [],
})

function openCreateGrp() {
    grpForm.reset()
    grpForm.clearErrors()
    grpModal.value = 'create'
}

function openEditGrp(grp) {
    grpForm.name                 = grp.name
    grpForm.under                = grp.under ?? ''
    grpForm.cost_centre_category = grp.cost_centre_category ?? ''
    grpForm.aliases              = grp.aliases ? [...grp.aliases] : []
    grpForm.clearErrors()
    grpModal.value = grp
}

function closeGrpModal() {
    grpModal.value = null
    grpForm.reset()
}

function submitGrp() {
    if (!isEditingGrp.value) {
        grpForm.post(route('tally.employee-groups.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeGrpModal(),
        })
    } else {
        grpForm.put(route('tally.employee-groups.update', { tenant: props.tenant.id, employeeGroup: grpModal.value.id }), {
            onSuccess: () => closeGrpModal(),
        })
    }
}

function destroyGrp(grp) {
    const msg = grp.tally_id
        ? `Mark "${grp.name}" inactive and queue deletion in Tally?`
        : `Delete "${grp.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.employee-groups.destroy', { tenant: props.tenant.id, employeeGroup: grp.id }))
}

const grpUnderOptions = computed(() => {
    const editing = isEditingGrp.value ? grpModal.value : null
    return props.employeeGroups.filter(g => g.is_active && (!editing || g.id !== editing.id)).map(g => g.name)
})

// ── Pay Head CRUD ──────────────────────────────────────────────────────────────
const phModal     = ref(null)
const isEditingPh = computed(() => phModal.value && phModal.value !== 'create')

const phForm = useForm({
    name:               '',
    pay_type:           '',
    income_type:        '',
    parent_group:       '',
    calculation_type:   '',
    leave_type:         '',
    calculation_period: '',
})

function openCreatePh() {
    phForm.reset()
    phForm.clearErrors()
    phModal.value = 'create'
}

function openEditPh(ph) {
    phForm.name               = ph.name
    phForm.pay_type           = ph.pay_type ?? ''
    phForm.income_type        = ph.income_type ?? ''
    phForm.parent_group       = ph.parent_group ?? ''
    phForm.calculation_type   = ph.calculation_type ?? ''
    phForm.leave_type         = ph.leave_type ?? ''
    phForm.calculation_period = ph.calculation_period ?? ''
    phForm.clearErrors()
    phModal.value = ph
}

function closePhModal() {
    phModal.value = null
    phForm.reset()
}

function submitPh() {
    if (!isEditingPh.value) {
        phForm.post(route('tally.pay-heads.store', { tenant: props.tenant.id }), {
            onSuccess: () => closePhModal(),
        })
    } else {
        phForm.put(route('tally.pay-heads.update', { tenant: props.tenant.id, payHead: phModal.value.id }), {
            onSuccess: () => closePhModal(),
        })
    }
}

function destroyPh(ph) {
    const msg = ph.tally_id
        ? `Mark "${ph.name}" inactive and queue deletion in Tally?`
        : `Delete "${ph.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.pay-heads.destroy', { tenant: props.tenant.id, payHead: ph.id }))
}

// ── Attendance Type CRUD ───────────────────────────────────────────────────────
const attModal     = ref(null)
const isEditingAtt = computed(() => attModal.value && attModal.value !== 'create')

const attForm = useForm({
    name:              '',
    under:             '',
    attendance_type:   '',
    attendance_period: '',
    aliases:           [],
})

const attUnderOptions = computed(() =>
    props.attendanceTypes.filter(t => t.is_active).map(t => t.name)
)

function openCreateAtt() {
    attForm.reset()
    attForm.clearErrors()
    attModal.value = 'create'
}

function openEditAtt(att) {
    attForm.name              = att.name
    attForm.under             = att.under ?? ''
    attForm.attendance_type   = att.attendance_type ?? ''
    attForm.attendance_period = att.attendance_period ?? ''
    attForm.aliases           = att.aliases ? [...att.aliases] : []
    attForm.clearErrors()
    attModal.value = att
}

function closeAttModal() {
    attModal.value = null
    attForm.reset()
}

function submitAtt() {
    if (!isEditingAtt.value) {
        attForm.post(route('tally.attendance-types.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeAttModal(),
        })
    } else {
        attForm.put(route('tally.attendance-types.update', { tenant: props.tenant.id, attendanceType: attModal.value.id }), {
            onSuccess: () => closeAttModal(),
        })
    }
}

function destroyAtt(att) {
    const msg = att.tally_id
        ? `Mark "${att.name}" inactive and queue deletion in Tally?`
        : `Delete "${att.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.attendance-types.destroy', { tenant: props.tenant.id, attendanceType: att.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Payroll Masters</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ employees.length }} employees · {{ employeeGroups.length }} groups · {{ payHeads.length }} pay heads · {{ attendanceTypes.length }} attendance types
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canManage && activeTab === 'employees'"
                            @click="openCreateEmp"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Employee
                    </button>
                    <button v-if="canManage && activeTab === 'employeeGroups'"
                            @click="openCreateGrp"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Group
                    </button>
                    <button v-if="canManage && activeTab === 'payHeads'"
                            @click="openCreatePh"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Pay Head
                    </button>
                    <button v-if="canManage && activeTab === 'attendanceTypes'"
                            @click="openCreateAtt"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Attendance Type
                    </button>
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

                <!-- Tabs -->
                <div class="flex gap-1 border-b border-gray-200">
                    <button v-for="tab in tabs" :key="tab.key"
                            @click="activeTab = tab.key"
                            :class="activeTab === tab.key
                                ? 'border-violet-600 text-violet-700 font-semibold'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="px-4 py-2.5 text-sm border-b-2 transition -mb-px">
                        {{ tab.label }}
                        <span class="ml-1.5 text-xs rounded-full px-1.5 py-0.5"
                              :class="activeTab === tab.key ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-500'">
                            {{ tab.key === 'employees' ? employees.length
                               : tab.key === 'employeeGroups' ? employeeGroups.length
                               : tab.key === 'payHeads' ? payHeads.length
                               : attendanceTypes.length }}
                        </span>
                    </button>
                </div>

                <!-- ── Employees tab ── -->
                <template v-if="activeTab === 'employees'">
                    <div class="flex flex-wrap items-center gap-3">
                        <input v-model="empSearch" type="text" placeholder="Search employees…"
                               class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                        <select v-model="empGroupFilter"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="all">All Groups</option>
                            <option v-for="g in empGroups.slice(1)" :key="g" :value="g">{{ g }}</option>
                        </select>
                        <span class="text-sm text-gray-400">{{ filteredEmployees.length }} result{{ filteredEmployees.length !== 1 ? 's' : '' }}</span>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-3">Employee</div>
                            <div class="col-span-2">Group / Dept</div>
                            <div class="col-span-1">Designation</div>
                            <div class="col-span-1 text-center">DOJ</div>
                            <div class="col-span-1 text-center">Gender</div>
                            <div class="col-span-1 text-center">Status</div>
                            <div class="col-span-1 text-center">Tally</div>
                            <div class="col-span-2 text-right" v-if="canManage">Actions</div>
                        </div>

                        <div v-for="emp in filteredEmployees" :key="emp.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-3">
                                <p class="text-sm font-medium text-gray-900">{{ emp.name }}</p>
                                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ emp.employee_number ?? '—' }}</p>
                            </div>
                            <div class="col-span-2 text-xs text-gray-600 truncate">{{ emp.parent ?? '—' }}</div>
                            <div class="col-span-1 text-xs text-gray-600 truncate">{{ emp.designation ?? '—' }}</div>
                            <div class="col-span-1 text-center text-xs text-gray-500">{{ formatDate(emp.date_of_joining) }}</div>
                            <div class="col-span-1 text-center text-xs text-gray-500">{{ emp.gender ?? '—' }}</div>
                            <div class="col-span-1 text-center">
                                <span :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ emp.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-span-1 text-center">
                                <span :class="syncBadge(emp.sync_status).cls"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ syncBadge(emp.sync_status).label }}
                                </span>
                            </div>
                            <div class="col-span-2 text-right" v-if="canManage">
                                <button @click="openEditEmp(emp)"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                                <button @click="destroyEmp(emp)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                            </div>
                        </div>

                        <p v-if="!filteredEmployees.length" class="text-center text-gray-400 py-12 text-sm">No employees found.</p>
                    </div>
                </template>

                <!-- ── Employee Groups tab ── -->
                <template v-if="activeTab === 'employeeGroups'">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-3">Group Name</div>
                            <div class="col-span-3">Parent</div>
                            <div class="col-span-2">Cost Centre Category</div>
                            <div class="col-span-1 text-center">Status</div>
                            <div class="col-span-1 text-center">Tally</div>
                            <div class="col-span-2 text-right" v-if="canManage">Actions</div>
                        </div>

                        <div v-for="grp in employeeGroups" :key="grp.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-3 text-sm font-medium text-gray-900">{{ grp.name }}</div>
                            <div class="col-span-3 text-sm text-gray-500">{{ grp.under ?? '—' }}</div>
                            <div class="col-span-2 text-xs text-gray-500 truncate">{{ grp.cost_centre_category ?? '—' }}</div>
                            <div class="col-span-1 text-center">
                                <span :class="grp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ grp.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-span-1 text-center">
                                <span :class="syncBadge(grp.sync_status).cls"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ syncBadge(grp.sync_status).label }}
                                </span>
                            </div>
                            <div class="col-span-2 text-right" v-if="canManage">
                                <button @click="openEditGrp(grp)"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                                <button @click="destroyGrp(grp)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                            </div>
                        </div>

                        <p v-if="!employeeGroups.length" class="text-center text-gray-400 py-12 text-sm">No employee groups found.</p>
                    </div>
                </template>

                <!-- ── Pay Heads tab ── -->
                <template v-if="activeTab === 'payHeads'">
                    <div class="flex flex-wrap items-center gap-3">
                        <input v-model="paySearch" type="text" placeholder="Search pay heads…"
                               class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                        <select v-model="payTypeFilter"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="all">All Types</option>
                            <option v-for="t in payHeadTypes.slice(1)" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <span class="text-sm text-gray-400">{{ filteredPayHeads.length }} result{{ filteredPayHeads.length !== 1 ? 's' : '' }}</span>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-3">Pay Head</div>
                            <div class="col-span-2">Type</div>
                            <div class="col-span-2">Parent Group</div>
                            <div class="col-span-1">Calculation</div>
                            <div class="col-span-1">Period</div>
                            <div class="col-span-1 text-center">Status</div>
                            <div class="col-span-1 text-center">Tally</div>
                            <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                        </div>

                        <div v-for="ph in filteredPayHeads" :key="ph.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-3">
                                <p class="text-sm font-medium text-gray-900">{{ ph.name }}</p>
                                <p v-if="ph.income_type" class="text-xs text-gray-400 mt-0.5">{{ ph.income_type }}</p>
                            </div>
                            <div class="col-span-2">
                                <span :class="payHeadTypeColor(ph.pay_type)"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ ph.pay_type ?? '—' }}
                                </span>
                            </div>
                            <div class="col-span-2 text-xs text-gray-500 truncate">{{ ph.parent_group ?? '—' }}</div>
                            <div class="col-span-1 text-xs text-gray-500">{{ ph.calculation_type ?? '—' }}</div>
                            <div class="col-span-1 text-xs text-gray-500">{{ ph.calculation_period ?? '—' }}</div>
                            <div class="col-span-1 text-center">
                                <span :class="ph.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ ph.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-span-1 text-center">
                                <span :class="syncBadge(ph.sync_status).cls"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ syncBadge(ph.sync_status).label }}
                                </span>
                            </div>
                            <div class="col-span-1 text-right" v-if="canManage">
                                <button @click="openEditPh(ph)"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                                <button @click="destroyPh(ph)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                            </div>
                        </div>

                        <p v-if="!filteredPayHeads.length" class="text-center text-gray-400 py-12 text-sm">No pay heads found.</p>
                    </div>
                </template>

                <!-- ── Attendance Types tab ── -->
                <template v-if="activeTab === 'attendanceTypes'">
                    <div class="flex flex-wrap items-center gap-3">
                        <input v-model="attSearch" type="text" placeholder="Search attendance types…"
                               class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                        <span class="text-sm text-gray-400">{{ filteredAttendance.length }} result{{ filteredAttendance.length !== 1 ? 's' : '' }}</span>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-3">Name</div>
                            <div class="col-span-3">Attendance Type</div>
                            <div class="col-span-2 text-center">Unit</div>
                            <div class="col-span-1 text-center">Status</div>
                            <div class="col-span-1 text-center">Tally</div>
                            <div class="col-span-2 text-right" v-if="canManage">Actions</div>
                        </div>

                        <div v-for="att in filteredAttendance" :key="att.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-3 text-sm font-medium text-gray-900">{{ att.name }}</div>
                            <div class="col-span-3">
                                <span :class="attTypeColor(att.attendance_type)"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ att.attendance_type ?? '—' }}
                                </span>
                            </div>
                            <div class="col-span-2 text-center text-sm text-gray-500">{{ att.attendance_period ?? '—' }}</div>
                            <div class="col-span-1 text-center">
                                <span :class="att.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ att.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-span-1 text-center">
                                <span :class="syncBadge(att.sync_status).cls"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ syncBadge(att.sync_status).label }}
                                </span>
                            </div>
                            <div class="col-span-2 text-right" v-if="canManage">
                                <button @click="openEditAtt(att)"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                                <button @click="destroyAtt(att)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                            </div>
                        </div>

                        <p v-if="!filteredAttendance.length" class="text-center text-gray-400 py-12 text-sm">No attendance types found.</p>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Employee slide-over -->
    <Teleport to="body">
        <div v-if="empModal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeEmpModal" />
            <div class="relative z-50 w-full max-w-xl bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">{{ isEditingEmp ? 'Edit Employee' : 'New Employee' }}</h2>
                    <button @click="closeEmpModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submitEmp" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="empForm.name" type="text" placeholder="Full name" class="tally-field" />
                            <p v-if="empForm.errors.name" class="mt-0.5 text-xs text-red-500">{{ empForm.errors.name }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Employee No.</span>
                        <div class="tally-input">
                            <input v-model="empForm.employee_number" type="text" placeholder="e.g. EMP001" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Gender</span>
                        <div class="tally-input">
                            <select v-model="empForm.gender" class="tally-field">
                                <option value="">— Select —</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Transgender</option>
                                <option>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Employee Group</span>
                        <div class="tally-input">
                            <select v-model="empForm.parent" class="tally-field">
                                <option value="">— Select Group —</option>
                                <option v-for="n in empGroupOptions" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Designation</span>
                        <div class="tally-input">
                            <input v-model="empForm.designation" type="text" placeholder="e.g. Manager" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Function</span>
                        <div class="tally-input">
                            <input v-model="empForm.employee_function" type="text" placeholder="e.g. Sales" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Location</span>
                        <div class="tally-input">
                            <input v-model="empForm.location" type="text" placeholder="e.g. Mumbai" class="tally-field" />
                        </div>
                    </div>

                    <div class="tally-section-header">Dates</div>

                    <div class="tally-row">
                        <span class="tally-label">Date of Joining</span>
                        <div class="tally-input">
                            <input v-model="empForm.date_of_joining" type="date" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Date of Resignation</span>
                        <div class="tally-input">
                            <input v-model="empForm.date_of_leaving" type="date" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Date of Birth</span>
                        <div class="tally-input">
                            <input v-model="empForm.date_of_birth" type="date" class="tally-field" />
                        </div>
                    </div>

                    <div class="tally-section-header">Personal</div>

                    <div class="tally-row">
                        <span class="tally-label">Father's Name</span>
                        <div class="tally-input">
                            <input v-model="empForm.father_name" type="text" placeholder="e.g. Ramesh" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Spouse's Name</span>
                        <div class="tally-input">
                            <input v-model="empForm.spouse_name" type="text" placeholder="e.g. Priya" class="tally-field" />
                        </div>
                    </div>

                    <div class="tally-section-header">Contact</div>

                    <div class="tally-row">
                        <span class="tally-label">Contact Number</span>
                        <div class="tally-input">
                            <input v-model="empForm.contact_number" type="text" placeholder="+91 9876543210" class="tally-field" />
                            <p v-if="empForm.errors.contact_number" class="mt-0.5 text-xs text-red-500">{{ empForm.errors.contact_number }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Email Address</span>
                        <div class="tally-input">
                            <input v-model="empForm.email_address" type="email" placeholder="emp@company.com" class="tally-field" />
                            <p v-if="empForm.errors.email_address" class="mt-0.5 text-xs text-red-500">{{ empForm.errors.email_address }}</p>
                        </div>
                    </div>
                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Address</span>
                        <div class="tally-input">
                            <div v-for="(addr, i) in empForm.address" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="empForm.address[i].Address" type="text" placeholder="Address line"
                                       class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="empForm.address.splice(i, 1)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="empForm.address.push({ Address: '' })"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Line</button>
                        </div>
                    </div>
                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Aliases</span>
                        <div class="tally-input">
                            <div v-for="(alias, i) in empForm.aliases" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="empForm.aliases[i].Alias" type="text" placeholder="Alias name"
                                       class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="empForm.aliases.splice(i, 1)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="empForm.aliases.push({ Alias: '' })"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Alias</button>
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
                        <button type="submit" :disabled="empForm.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditingEmp ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeEmpModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Employee Group slide-over -->
    <Teleport to="body">
        <div v-if="grpModal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeGrpModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">{{ isEditingGrp ? 'Edit Employee Group' : 'New Employee Group' }}</h2>
                    <button @click="closeGrpModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submitGrp" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="grpForm.name" type="text" placeholder="e.g. Primary Cost Centre" class="tally-field" />
                            <p v-if="grpForm.errors.name" class="mt-0.5 text-xs text-red-500">{{ grpForm.errors.name }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Under (Parent)</span>
                        <div class="tally-input">
                            <select v-model="grpForm.under" class="tally-field">
                                <option value="Primary">Primary</option>
                                <option v-for="g in grpUnderOptions" :key="g" :value="g">{{ g }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Cost Centre Category</span>
                        <div class="tally-input">
                            <input v-model="grpForm.cost_centre_category" type="text" placeholder="e.g. Primary Cost Category" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Aliases</span>
                        <div class="tally-input">
                            <div v-for="(alias, i) in grpForm.aliases" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="grpForm.aliases[i].Alias" type="text" placeholder="Alias name"
                                       class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="grpForm.aliases.splice(i, 1)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="grpForm.aliases.push({ Alias: '' })"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Alias</button>
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
                        <button type="submit" :disabled="grpForm.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditingGrp ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeGrpModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Pay Head slide-over -->
    <Teleport to="body">
        <div v-if="phModal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closePhModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">{{ isEditingPh ? 'Edit Pay Head' : 'New Pay Head' }}</h2>
                    <button @click="closePhModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submitPh" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="phForm.name" type="text" placeholder="e.g. Basic Pay" class="tally-field" />
                            <p v-if="phForm.errors.name" class="mt-0.5 text-xs text-red-500">{{ phForm.errors.name }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Pay Type</span>
                        <div class="tally-input">
                            <select v-model="phForm.pay_type" class="tally-field">
                                <option value="">— Select —</option>
                                <option>Earnings for Employees</option>
                                <option>Employees' Statutory Deductions</option>
                                <option>Employer's Statutory Contributions</option>
                                <option>Deductions</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Income Type</span>
                        <div class="tally-input">
                            <input v-model="phForm.income_type" type="text" placeholder="e.g. Fixed" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Parent Group</span>
                        <div class="tally-input">
                            <select v-model="phForm.parent_group" class="tally-field">
                                <option value="">— Select —</option>
                                <option>Direct Expenses</option>
                                <option>Indirect Expenses</option>
                                <option>Misc. Expenses (ASSET)</option>
                            </select>
                        </div>
                    </div>

                    <div class="tally-section-header">Calculation</div>

                    <div class="tally-row">
                        <span class="tally-label">Calculation Type</span>
                        <div class="tally-input">
                            <input v-model="phForm.calculation_type" type="text" placeholder="e.g. As Computed Value" class="tally-field" />
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Calculation Period</span>
                        <div class="tally-input">
                            <select v-model="phForm.calculation_period" class="tally-field">
                                <option value="">— Select —</option>
                                <option>Days</option>
                                <option>Fortnights</option>
                                <option>Months</option>
                                <option>Weeks</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Leave Type</span>
                        <div class="tally-input">
                            <input v-model="phForm.leave_type" type="text" placeholder="e.g. Casual Leave" class="tally-field" />
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
                        <button type="submit" :disabled="phForm.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditingPh ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closePhModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Attendance Type slide-over -->
    <Teleport to="body">
        <div v-if="attModal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeAttModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">{{ isEditingAtt ? 'Edit Attendance Type' : 'New Attendance Type' }}</h2>
                    <button @click="closeAttModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submitAtt" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="attForm.name" type="text" placeholder="e.g. Present" class="tally-field" />
                            <p v-if="attForm.errors.name" class="mt-0.5 text-xs text-red-500">{{ attForm.errors.name }}</p>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Under</span>
                        <div class="tally-input">
                            <select v-model="attForm.under" class="tally-field">
                                <option value="Primary">Primary</option>
                                <option v-for="n in attUnderOptions" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Attendance Type</span>
                        <div class="tally-input">
                            <select v-model="attForm.attendance_type" class="tally-field">
                                <option value="">— Select —</option>
                                <option>Attendance / Leave with Pay</option>
                                <option>Leave without Pay</option>
                                <option>Production</option>
                                <option>User Defined Calender Type</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row">
                        <span class="tally-label">Attendance Period</span>
                        <div class="tally-input">
                            <select v-model="attForm.attendance_period" class="tally-field">
                                <option value="">— Select —</option>
                                <option>Days</option>
                                <option>Hours</option>
                                <option>Minutes</option>
                            </select>
                        </div>
                    </div>
                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Aliases</span>
                        <div class="tally-input">
                            <div v-for="(alias, i) in attForm.aliases" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="attForm.aliases[i].Alias" type="text" placeholder="Alias name"
                                       class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="attForm.aliases.splice(i, 1)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="attForm.aliases.push({ Alias: '' })"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Alias</button>
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
                        <button type="submit" :disabled="attForm.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditingAtt ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeAttModal"
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
.tally-label { @apply w-44 shrink-0 text-sm text-gray-600 bg-gray-50 px-4 py-2.5 border-r border-gray-100 flex items-center; }
.tally-input { @apply flex-1 px-3 py-2; }
.tally-field { @apply w-full text-sm border-0 outline-none focus:ring-1 focus:ring-violet-400 rounded bg-transparent; }
.tally-section-header { @apply bg-violet-50 text-violet-700 text-xs font-semibold uppercase tracking-wider px-4 py-1.5 border-b border-violet-100; }
</style>
