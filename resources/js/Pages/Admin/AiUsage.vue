<script setup>
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement, LineElement,
    BarElement, ArcElement, Title, Tooltip, Legend, Filler,
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'

ChartJS.register(
    CategoryScale, LinearScale, PointElement, LineElement,
    BarElement, ArcElement, Title, Tooltip, Legend, Filler,
)

const props = defineProps({
    stats:           Object,
    dailyLabels:     Array,
    dailyCost:       Array,
    dailyCalls:      Array,
    byAgent:         Array,
    byModel:         Array,
    tenantBreakdown: Array,
    recentLogs:      Array,
})

// ── Helpers ───────────────────────────────────────────────────────────────────

const expandedTenant = ref(null)
const toggleTenant = (id) => { expandedTenant.value = expandedTenant.value === id ? null : id }

// Smart cost formatter: shows enough decimals to be non-zero
const fmtCost = (n) => {
    const v = Number(n)
    if (v === 0) return '$0.000000'
    if (v >= 0.01)   return '$' + v.toFixed(4)
    if (v >= 0.0001) return '$' + v.toFixed(6)
    return '$' + v.toFixed(8)
}

const fmt = (n) => fmtCost(n)
const fmtShort = (n) => {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M'
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K'
    return String(n)
}

const COLORS = [
    '#7c3aed','#4f46e5','#0891b2','#059669',
    '#d97706','#dc2626','#db2777','#65a30d',
]

const callTypeBadge = (t) => ({
    chat:       'bg-violet-50 text-violet-700',
    structured: 'bg-blue-50 text-blue-700',
    embedding:  'bg-amber-50 text-amber-700',
})[t] ?? 'bg-gray-100 text-gray-600'

// ── Chart: Daily cost ─────────────────────────────────────────────────────────
const dailyCostChart = {
    labels: props.dailyLabels,
    datasets: [{
        label: 'Cost (USD)',
        data: props.dailyCost,
        borderColor: '#7c3aed',
        backgroundColor: 'rgba(124,58,237,0.08)',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        fill: true,
        tension: 0.4,
    }],
}
const dailyCostOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + fmtCost(c.raw) } } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 10 } },
        y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 }, callback: v => fmtCost(v) } },
    },
}

// ── Chart: Daily calls ────────────────────────────────────────────────────────
const dailyCallsChart = {
    labels: props.dailyLabels,
    datasets: [{
        label: 'API Calls',
        data: props.dailyCalls,
        backgroundColor: 'rgba(124,58,237,0.7)',
        borderRadius: 4,
        borderSkipped: false,
    }],
}
const dailyCallsOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 10 } },
        y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 }, precision: 0 } },
    },
}

// ── Chart: Agent doughnut ─────────────────────────────────────────────────────
const agentDoughnut = {
    labels: props.byAgent.map(a => a.agent),
    datasets: [{
        data: props.byAgent.map(a => a.cost),
        backgroundColor: COLORS.slice(0, props.byAgent.length),
        borderWidth: 0,
        hoverOffset: 6,
    }],
}
const doughnutOptions = {
    responsive: true, maintainAspectRatio: false, cutout: '68%',
    plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12, boxWidth: 12 } },
        tooltip: { callbacks: { label: c => ' ' + fmtCost(c.raw) } },
    },
}

// ── Chart: Model bar ──────────────────────────────────────────────────────────
const modelBarChart = {
    labels: props.byModel.map(m => m.model),
    datasets: [{
        label: 'Cost (USD)',
        data: props.byModel.map(m => m.cost),
        backgroundColor: COLORS.slice(0, props.byModel.length),
        borderRadius: 4,
        borderSkipped: false,
    }],
}
const modelBarOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + fmtCost(c.raw) } } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 }, callback: v => fmtCost(v) } },
    },
}

// ── Chart: Tenant bar ─────────────────────────────────────────────────────────
const tenantBarChart = {
    labels: props.tenantBreakdown.map(t => t.tenant_name),
    datasets: [{
        label: 'Cost (USD)',
        data: props.tenantBreakdown.map(t => t.total_cost),
        backgroundColor: 'rgba(124,58,237,0.75)',
        borderRadius: 4,
        borderSkipped: false,
    }],
}
const tenantBarOptions = {
    indexAxis: 'y',
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + fmtCost(c.raw) } } },
    scales: {
        x: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 }, callback: v => fmtCost(v) } },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } },
    },
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-500 mb-0.5">Platform Admin</p>
                <h1 class="text-xl font-semibold text-gray-900">AI Usage Monitor</h1>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ── Stat cards ───────────────────────────────────────── -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-400 mb-1">Cost Today</p>
                        <p class="text-2xl font-bold text-violet-600">{{ fmt(stats.cost_today) }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-400 mb-1">Cost This Month</p>
                        <p class="text-2xl font-bold text-gray-900">{{ fmt(stats.cost_month) }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-400 mb-1">All-Time Cost</p>
                        <p class="text-2xl font-bold text-gray-900">{{ fmt(stats.cost_total) }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-400 mb-1">Tokens This Month</p>
                        <p class="text-2xl font-bold text-gray-900">{{ fmtShort(stats.tokens_month) }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-400 mb-1">Calls Today</p>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.calls_today }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-400 mb-1">Calls This Month</p>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.calls_month }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs text-gray-400 mb-1">Errors This Month</p>
                        <p class="text-2xl font-bold" :class="stats.errors_month > 0 ? 'text-red-500' : 'text-gray-900'">{{ stats.errors_month }}</p>
                    </div>
                </div>

                <!-- ── Daily charts ─────────────────────────────────────── -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <h2 class="text-sm font-semibold text-gray-700 mb-4">Daily Cost — Last 30 Days</h2>
                        <div class="h-52">
                            <Line :data="dailyCostChart" :options="dailyCostOptions" />
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <h2 class="text-sm font-semibold text-gray-700 mb-4">Daily API Calls — Last 30 Days</h2>
                        <div class="h-52">
                            <Bar :data="dailyCallsChart" :options="dailyCallsOptions" />
                        </div>
                    </div>
                </div>

                <!-- ── Agent doughnut + table + model bar ──────────────── -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <h2 class="text-sm font-semibold text-gray-700 mb-4">Cost by Agent</h2>
                        <div class="h-56" v-if="byAgent.length">
                            <Doughnut :data="agentDoughnut" :options="doughnutOptions" />
                        </div>
                        <p v-else class="text-sm text-gray-400 text-center mt-12">No data yet</p>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-700">Agent Breakdown</h2>
                        </div>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50">
                                    <th class="px-4 py-2 text-left text-gray-400 font-medium">Agent</th>
                                    <th class="px-4 py-2 text-right text-gray-400 font-medium">Calls</th>
                                    <th class="px-4 py-2 text-right text-gray-400 font-medium">Tokens</th>
                                    <th class="px-4 py-2 text-right text-gray-400 font-medium">Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="a in byAgent" :key="a.agent">
                                    <td class="px-4 py-2.5 font-medium text-gray-800 truncate max-w-[110px]">{{ a.agent }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-500">{{ a.calls }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-500">{{ fmtShort(a.tokens) }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-violet-700">{{ fmtCost(a.cost) }}</td>
                                </tr>
                                <tr v-if="!byAgent.length">
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">No data yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <h2 class="text-sm font-semibold text-gray-700 mb-4">Cost by Model</h2>
                        <div class="h-56" v-if="byModel.length">
                            <Bar :data="modelBarChart" :options="modelBarOptions" />
                        </div>
                        <p v-else class="text-sm text-gray-400 text-center mt-12">No data yet</p>
                    </div>
                </div>

                <!-- ── Per-tenant breakdown ─────────────────────────────── -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700">Cost by Tenant</h2>
                        <span class="text-xs text-gray-400">{{ tenantBreakdown.length }} tenant{{ tenantBreakdown.length !== 1 ? 's' : '' }}</span>
                    </div>

                    <!-- Tenant bar chart -->
                    <div class="p-6 border-b border-gray-100" v-if="tenantBreakdown.length">
                        <div :style="{ height: Math.max(120, tenantBreakdown.length * 36) + 'px' }">
                            <Bar :data="tenantBarChart" :options="tenantBarOptions" />
                        </div>
                    </div>

                    <!-- Expandable tenant rows -->
                    <div v-if="tenantBreakdown.length">
                        <div
                            v-for="t in tenantBreakdown"
                            :key="t.tenant_id"
                            class="border-b border-gray-50 last:border-0"
                        >
                            <!-- Tenant summary row -->
                            <button
                                @click="toggleTenant(t.tenant_id)"
                                class="w-full flex items-center justify-between px-6 py-3.5 hover:bg-gray-50 transition text-left"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <svg
                                        :class="['h-4 w-4 text-gray-400 transition-transform shrink-0', expandedTenant === t.tenant_id ? 'rotate-90' : '']"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    <span class="text-sm font-semibold text-gray-800 truncate">{{ t.tenant_name }}</span>
                                    <span v-if="t.total_errors > 0" class="shrink-0 text-[10px] font-semibold bg-red-50 text-red-500 px-1.5 py-0.5 rounded-full">
                                        {{ t.total_errors }} error{{ t.total_errors !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-6 shrink-0 ml-4">
                                    <span class="text-xs text-gray-400">{{ t.total_calls }} calls</span>
                                    <span class="text-xs text-gray-400">{{ fmtShort(t.total_tokens) }} tokens</span>
                                    <span class="text-sm font-bold text-violet-700 w-20 text-right">{{ fmtCost(t.total_cost) }}</span>
                                </div>
                            </button>

                            <!-- Expanded: per-agent breakdown -->
                            <div v-if="expandedTenant === t.tenant_id" class="bg-gray-50 border-t border-gray-100">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="pl-14 pr-4 py-2 text-left text-gray-400 font-medium">Agent</th>
                                            <th class="px-4 py-2 text-right text-gray-400 font-medium">Calls</th>
                                            <th class="px-4 py-2 text-right text-gray-400 font-medium">Tokens</th>
                                            <th class="px-6 py-2 text-right text-gray-400 font-medium">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="a in t.by_agent" :key="a.agent" class="hover:bg-gray-100/50">
                                            <td class="pl-14 pr-4 py-2.5 font-medium text-gray-700">{{ a.agent }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-500">{{ a.calls }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-500">{{ fmtShort(a.tokens) }}</td>
                                            <td class="px-6 py-2.5 text-right font-semibold text-violet-700">{{ fmtCost(a.cost) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <p v-else class="px-6 py-10 text-center text-sm text-gray-400">No tenant data yet</p>
                </div>

                <!-- ── Recent calls log ────────────────────────────────── -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700">Recent API Calls</h2>
                        <span class="text-xs text-gray-400">Last 50</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50">
                                    <th class="px-4 py-2.5 text-left text-gray-400 font-medium">Tenant</th>
                                    <th class="px-4 py-2.5 text-left text-gray-400 font-medium">Agent</th>
                                    <th class="px-4 py-2.5 text-left text-gray-400 font-medium">Model</th>
                                    <th class="px-4 py-2.5 text-left text-gray-400 font-medium">Type</th>
                                    <th class="px-4 py-2.5 text-right text-gray-400 font-medium">Prompt</th>
                                    <th class="px-4 py-2.5 text-right text-gray-400 font-medium">Completion</th>
                                    <th class="px-4 py-2.5 text-right text-gray-400 font-medium">Steps</th>
                                    <th class="px-4 py-2.5 text-right text-gray-400 font-medium">Cost</th>
                                    <th class="px-4 py-2.5 text-left text-gray-400 font-medium">User</th>
                                    <th class="px-4 py-2.5 text-left text-gray-400 font-medium">When</th>
                                    <th class="px-4 py-2.5 text-center text-gray-400 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="log in recentLogs" :key="log.id" :class="log.is_error ? 'bg-red-50/40' : ''">
                                    <td class="px-4 py-2.5 font-medium text-gray-700 whitespace-nowrap">{{ log.tenant_name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 font-medium text-gray-800 whitespace-nowrap">{{ log.agent }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">{{ log.model ?? '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        <span :class="['font-semibold px-1.5 py-0.5 rounded-full text-[10px]', callTypeBadge(log.call_type)]">
                                            {{ log.call_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-500">{{ log.prompt_tokens.toLocaleString() }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-500">{{ log.completion_tokens.toLocaleString() }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-500">{{ log.tool_steps || '—' }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold" :class="log.cost_usd > 0 ? 'text-violet-700' : 'text-gray-400'">{{ fmtCost(log.cost_usd) }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">{{ log.user_name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-gray-400 whitespace-nowrap">{{ log.created_at }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span v-if="log.is_error" class="font-semibold px-1.5 py-0.5 rounded-full text-[10px] bg-red-100 text-red-600" :title="log.error_message">Error</span>
                                        <span v-else class="font-semibold px-1.5 py-0.5 rounded-full text-[10px] bg-emerald-50 text-emerald-600">OK</span>
                                    </td>
                                </tr>
                                <tr v-if="!recentLogs.length">
                                    <td colspan="11" class="px-4 py-12 text-center text-gray-400">No AI calls logged yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
