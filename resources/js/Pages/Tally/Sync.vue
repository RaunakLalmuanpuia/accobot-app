<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:     Object,
    connection: Object,
    latestLogs: Array,
    allLogs:    Array,
    reports:    Array,
    stats:      Object,
})

const canManage = hasPermission('integrations.manage')
const activeTab = ref('masters')
const expandedLog = ref(null)

const masterEntities = [
    'ledger_groups', 'ledgers', 'stock_groups', 'stock_categories', 'stock_items',
]
const voucherEntities = [
    'vouchers_sales', 'vouchers_purchase', 'vouchers_creditnote', 'vouchers_debitnote',
    'vouchers_receipt', 'vouchers_payment', 'vouchers_contra', 'vouchers_journal',
]

function entityLabel(entity) {
    const map = {
        ledger_groups:         'Ledger Groups',
        ledgers:               'Ledgers',
        stock_groups:          'Stock Groups',
        stock_categories:      'Stock Categories',
        stock_items:           'Stock Items',
        vouchers_sales:        'Sales Vouchers',
        vouchers_purchase:     'Purchase Vouchers',
        vouchers_creditnote:   'Credit Notes',
        vouchers_debitnote:    'Debit Notes',
        vouchers_receipt:      'Receipts',
        vouchers_payment:      'Payments',
        vouchers_contra:       'Contra',
        vouchers_journal:      'Journal',
    }
    return map[entity] ?? entity
}

function latestLog(entity) {
    return props.latestLogs.find(l => l.entity === entity)
}

function statusBadge(status) {
    const classes = {
        success: 'bg-green-100 text-green-700',
        failed:  'bg-red-100 text-red-700',
        running: 'bg-yellow-100 text-yellow-700',
    }
    return classes[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString()
}

function triggerSync() {
    router.post(route('tally.sync.trigger', { tenant: props.tenant.id }))
}

function toggleLog(id) {
    expandedLog.value = expandedLog.value === id ? null : id
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Tally Sync</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Last synced: {{ formatDate(stats.last_synced_at) }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a :href="route('tally.connection.show', { tenant: tenant.id })"
                       class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                        Settings
                    </a>
                    <button v-if="canManage"
                            @click="triggerSync"
                            class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        Sync Now
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    {{ $page.props.flash.error }}
                </div>

                <!-- No connection warning -->
                <div v-if="!connection" class="rounded-xl bg-yellow-50 border border-yellow-200 px-4 py-4 text-sm text-yellow-800">
                    No Tally connection configured.
                    <a :href="route('tally.connection.show', { tenant: tenant.id })"
                       class="underline font-medium ml-1">Configure now</a>
                </div>

                <!-- Stats bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div v-for="(s, label) in {
                        'Ledger Groups':  stats.total_ledger_groups,
                        'Ledgers':        stats.total_ledgers,
                        'Stock Items':    stats.total_stock_items,
                        'Vouchers':       stats.total_vouchers,
                    }" :key="label"
                         class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ s }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ label }}</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="flex gap-6" aria-label="Tabs">
                        <button v-for="tab in ['masters', 'vouchers', 'reports', 'logs']"
                                :key="tab"
                                @click="activeTab = tab"
                                :class="[
                                    'pb-3 text-sm font-medium capitalize border-b-2 transition',
                                    activeTab === tab
                                        ? 'border-violet-600 text-violet-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]">
                            {{ tab }}
                        </button>
                    </nav>
                </div>

                <!-- Masters tab -->
                <div v-if="activeTab === 'masters'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-4">Entity</div>
                        <div class="col-span-2 text-center">Created</div>
                        <div class="col-span-2 text-center">Updated</div>
                        <div class="col-span-2 text-center">Skipped</div>
                        <div class="col-span-2">Last Synced</div>
                    </div>
                    <div v-for="entity in masterEntities" :key="entity"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0">
                        <div class="col-span-4">
                            <p class="text-sm font-medium text-gray-900">{{ entityLabel(entity) }}</p>
                            <span v-if="latestLog(entity)"
                                  :class="['mt-0.5 inline-block text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(latestLog(entity).status)]">
                                {{ latestLog(entity).status }}
                            </span>
                            <span v-else class="text-xs text-gray-400">never</span>
                        </div>
                        <div class="col-span-2 text-center text-sm text-gray-500">{{ latestLog(entity)?.records_created ?? '—' }}</div>
                        <div class="col-span-2 text-center text-sm text-gray-500">{{ latestLog(entity)?.records_updated ?? '—' }}</div>
                        <div class="col-span-2 text-center text-sm text-gray-500">{{ latestLog(entity)?.records_skipped ?? '—' }}</div>
                        <div class="col-span-2 text-xs text-gray-400">{{ formatDate(latestLog(entity)?.completed_at) }}</div>
                    </div>
                </div>

                <!-- Vouchers tab -->
                <div v-if="activeTab === 'vouchers'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-4">Voucher Type</div>
                        <div class="col-span-2 text-center">Created</div>
                        <div class="col-span-2 text-center">Updated</div>
                        <div class="col-span-2 text-center">Skipped</div>
                        <div class="col-span-2">Last Synced</div>
                    </div>
                    <div v-for="entity in voucherEntities" :key="entity"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0">
                        <div class="col-span-4">
                            <p class="text-sm font-medium text-gray-900">{{ entityLabel(entity) }}</p>
                            <span v-if="latestLog(entity)"
                                  :class="['mt-0.5 inline-block text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(latestLog(entity).status)]">
                                {{ latestLog(entity).status }}
                            </span>
                            <span v-else class="text-xs text-gray-400">never</span>
                        </div>
                        <div class="col-span-2 text-center text-sm text-gray-500">{{ latestLog(entity)?.records_created ?? '—' }}</div>
                        <div class="col-span-2 text-center text-sm text-gray-500">{{ latestLog(entity)?.records_updated ?? '—' }}</div>
                        <div class="col-span-2 text-center text-sm text-gray-500">{{ latestLog(entity)?.records_skipped ?? '—' }}</div>
                        <div class="col-span-2 text-xs text-gray-400">{{ formatDate(latestLog(entity)?.completed_at) }}</div>
                    </div>
                </div>

                <!-- Reports tab -->
                <div v-if="activeTab === 'reports'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-3">Report Type</div>
                        <div class="col-span-3">Period</div>
                        <div class="col-span-3">Generated At</div>
                        <div class="col-span-3">Synced At</div>
                    </div>
                    <div v-for="report in reports" :key="report.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0">
                        <div class="col-span-3 text-sm font-medium text-gray-900 capitalize">
                            {{ report.report_type.replace(/_/g, ' ') }}
                        </div>
                        <div class="col-span-3 text-sm text-gray-500">
                            {{ report.period_from ? report.period_from + ' – ' : '' }}{{ report.period_to }}
                        </div>
                        <div class="col-span-3 text-sm text-gray-500">{{ formatDate(report.generated_at) }}</div>
                        <div class="col-span-3 text-sm text-gray-500">{{ formatDate(report.synced_at) }}</div>
                    </div>
                    <p v-if="!reports.length" class="text-center text-gray-400 py-12 text-sm">No report snapshots yet.</p>
                </div>

                <!-- Logs tab -->
                <div v-if="activeTab === 'logs'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-3">Entity</div>
                        <div class="col-span-1">Dir</div>
                        <div class="col-span-2">Status</div>
                        <div class="col-span-1 text-center">+</div>
                        <div class="col-span-1 text-center">~</div>
                        <div class="col-span-1 text-center">-</div>
                        <div class="col-span-3">Completed</div>
                    </div>
                    <template v-for="log in allLogs" :key="log.id">
                        <div class="grid grid-cols-12 items-center px-6 py-3 border-b border-gray-50 hover:bg-gray-50/60 transition cursor-pointer"
                             @click="toggleLog(log.id)">
                            <div class="col-span-3 text-sm text-gray-700 truncate">{{ entityLabel(log.entity) }}</div>
                            <div class="col-span-1 text-xs text-gray-400 capitalize">{{ log.direction }}</div>
                            <div class="col-span-2">
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(log.status)]">
                                    {{ log.status }}
                                </span>
                            </div>
                            <div class="col-span-1 text-center text-sm text-green-600">{{ log.records_created }}</div>
                            <div class="col-span-1 text-center text-sm text-blue-600">{{ log.records_updated }}</div>
                            <div class="col-span-1 text-center text-sm text-gray-400">{{ log.records_skipped }}</div>
                            <div class="col-span-3 text-xs text-gray-400">{{ formatDate(log.completed_at) }}</div>
                        </div>
                        <div v-if="expandedLog === log.id && log.error_message"
                             class="px-6 py-3 bg-red-50 border-b border-red-100 text-xs text-red-700 font-mono break-words">
                            {{ log.error_message }}
                        </div>
                    </template>
                    <p v-if="!allLogs.length" class="text-center text-gray-400 py-12 text-sm">No sync logs yet.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
