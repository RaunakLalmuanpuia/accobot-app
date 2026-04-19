<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant:          Object,
    employees:       Array,
    employeeGroups:  Array,
    payHeads:        Array,
    attendanceTypes: Array,
})

const activeTab = ref('employees')

const tabs = [
    { key: 'employees',       label: 'Employees' },
    { key: 'employeeGroups',  label: 'Employee Groups' },
    { key: 'payHeads',        label: 'Pay Heads' },
    { key: 'attendanceTypes', label: 'Attendance Types' },
]

// ── Employees ──────────────────────────────────────────────────────────────

const empSearch      = ref('')
const empGroupFilter = ref('all')

const empGroups = computed(() => {
    const set = new Set(props.employees.map(e => e.group_name).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filteredEmployees = computed(() => {
    let list = props.employees
    if (empGroupFilter.value !== 'all') {
        list = list.filter(e => e.group_name === empGroupFilter.value)
    }
    const q = empSearch.value.toLowerCase()
    if (q) {
        list = list.filter(e =>
            e.name.toLowerCase().includes(q) ||
            (e.employee_number ?? '').toLowerCase().includes(q) ||
            (e.designation ?? '').toLowerCase().includes(q) ||
            (e.department ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

// ── Pay Heads ──────────────────────────────────────────────────────────────

const paySearch     = ref('')
const payTypeFilter = ref('all')

const payHeadTypes = computed(() => {
    const set = new Set(props.payHeads.map(p => p.pay_head_type).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filteredPayHeads = computed(() => {
    let list = props.payHeads
    if (payTypeFilter.value !== 'all') {
        list = list.filter(p => p.pay_head_type === payTypeFilter.value)
    }
    const q = paySearch.value.toLowerCase()
    if (q) {
        list = list.filter(p => p.name.toLowerCase().includes(q))
    }
    return list
})

// ── Attendance Types ───────────────────────────────────────────────────────

const attSearch = ref('')

const filteredAttendance = computed(() => {
    const q = attSearch.value.toLowerCase()
    if (!q) return props.attendanceTypes
    return props.attendanceTypes.filter(t =>
        t.name.toLowerCase().includes(q) ||
        (t.attendance_type ?? '').toLowerCase().includes(q)
    )
})

// ── Helpers ────────────────────────────────────────────────────────────────

const payHeadTypeColors = {
    'Earning':                              'bg-green-100 text-green-700',
    'Employees\' Statutory Deductions':     'bg-red-100 text-red-700',
    'Employer\'s Statutory Contributions':  'bg-blue-100 text-blue-700',
    'Deduction':                            'bg-orange-100 text-orange-700',
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
                <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                      class="text-sm text-gray-500 hover:text-gray-700">
                    ← Back to Sync
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

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
                            <div class="col-span-2">Designation</div>
                            <div class="col-span-1 text-center">DOJ</div>
                            <div class="col-span-1 text-center">Gender</div>
                            <div class="col-span-1">PF / UAN</div>
                            <div class="col-span-1">Bank</div>
                            <div class="col-span-1 text-center">Status</div>
                        </div>

                        <div v-for="emp in filteredEmployees" :key="emp.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-3">
                                <p class="text-sm font-medium text-gray-900">{{ emp.name }}</p>
                                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ emp.employee_number ?? '—' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-gray-600 truncate">{{ emp.group_name ?? '—' }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ emp.department ?? '' }}</p>
                            </div>
                            <div class="col-span-2 text-sm text-gray-600 truncate">{{ emp.designation ?? '—' }}</div>
                            <div class="col-span-1 text-center text-xs text-gray-500">{{ formatDate(emp.date_of_joining) }}</div>
                            <div class="col-span-1 text-center text-xs text-gray-500">{{ emp.gender ?? '—' }}</div>
                            <div class="col-span-1">
                                <p class="text-xs text-gray-500 font-mono truncate">{{ emp.pf_number ?? '—' }}</p>
                                <p class="text-xs text-gray-400 font-mono truncate">{{ emp.uan_number ?? '' }}</p>
                            </div>
                            <div class="col-span-1 text-xs text-gray-500 truncate">{{ emp.bank_name ?? '—' }}</div>
                            <div class="col-span-1 text-center">
                                <span :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ emp.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <p v-if="!filteredEmployees.length" class="text-center text-gray-400 py-12 text-sm">No employees found.</p>
                    </div>
                </template>

                <!-- ── Employee Groups tab ── -->
                <template v-if="activeTab === 'employeeGroups'">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-5">Group Name</div>
                            <div class="col-span-4">Parent</div>
                            <div class="col-span-2 text-center">Last Synced</div>
                            <div class="col-span-1 text-center">Status</div>
                        </div>

                        <div v-for="grp in employeeGroups" :key="grp.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-5 text-sm font-medium text-gray-900">{{ grp.name }}</div>
                            <div class="col-span-4 text-sm text-gray-500">{{ grp.parent_name ?? '—' }}</div>
                            <div class="col-span-2 text-center text-xs text-gray-400">{{ formatDate(grp.last_synced_at) }}</div>
                            <div class="col-span-1 text-center">
                                <span :class="grp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ grp.is_active ? 'Active' : 'Inactive' }}
                                </span>
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
                            <div class="col-span-3">Type</div>
                            <div class="col-span-2">Ledger</div>
                            <div class="col-span-2">Calculation</div>
                            <div class="col-span-1 text-right">Rate</div>
                            <div class="col-span-1 text-center">Status</div>
                        </div>

                        <div v-for="ph in filteredPayHeads" :key="ph.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-3">
                                <p class="text-sm font-medium text-gray-900">{{ ph.name }}</p>
                                <p v-if="ph.pay_slip_name" class="text-xs text-gray-400 mt-0.5">{{ ph.pay_slip_name }}</p>
                            </div>
                            <div class="col-span-3">
                                <span :class="payHeadTypeColor(ph.pay_head_type)"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ ph.pay_head_type ?? '—' }}
                                </span>
                            </div>
                            <div class="col-span-2 text-xs text-gray-500 truncate">{{ ph.ledger_name ?? '—' }}</div>
                            <div class="col-span-2 text-xs text-gray-500">{{ ph.calculation_type ?? '—' }}</div>
                            <div class="col-span-1 text-right text-sm text-gray-700">
                                <span v-if="ph.rate !== null">{{ ph.rate }}{{ ph.calculation_type === 'Fixed' ? '' : '%' }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </div>
                            <div class="col-span-1 text-center">
                                <span :class="ph.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ ph.is_active ? 'Active' : 'Inactive' }}
                                </span>
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
                            <div class="col-span-4">Name</div>
                            <div class="col-span-4">Attendance Type</div>
                            <div class="col-span-2 text-center">Unit</div>
                            <div class="col-span-2 text-center">Status</div>
                        </div>

                        <div v-for="att in filteredAttendance" :key="att.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-4 text-sm font-medium text-gray-900">{{ att.name }}</div>
                            <div class="col-span-4">
                                <span :class="attTypeColor(att.attendance_type)"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ att.attendance_type ?? '—' }}
                                </span>
                            </div>
                            <div class="col-span-2 text-center text-sm text-gray-500">{{ att.unit_of_measure ?? '—' }}</div>
                            <div class="col-span-2 text-center">
                                <span :class="att.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ att.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <p v-if="!filteredAttendance.length" class="text-center text-gray-400 py-12 text-sm">No attendance types found.</p>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
