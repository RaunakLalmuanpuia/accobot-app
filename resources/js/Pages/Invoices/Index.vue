<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:   Object,
    invoices: Array,
})

// ── Expand ────────────────────────────────────────────────────
const expanded = ref(new Set())
function toggleExpand(id) {
    expanded.value.has(id) ? expanded.value.delete(id) : expanded.value.add(id)
    expanded.value = new Set(expanded.value)
}

const canCreate  = hasPermission('invoices.create')
const canEdit    = hasPermission('invoices.edit')
const canDelete  = hasPermission('invoices.delete')

// ── Filter ────────────────────────────────────────────────────
const statusFilter = ref('all')
const filteredInvoices = computed(() =>
    statusFilter.value === 'all'
        ? props.invoices
        : props.invoices.filter(i => i.status === statusFilter.value)
)

const filters = [
    { value: 'all',       label: 'All' },
    { value: 'draft',     label: 'Draft' },
    { value: 'sent',      label: 'Sent' },
    { value: 'paid',      label: 'Paid' },
    { value: 'partial',   label: 'Partial' },
    { value: 'overdue',   label: 'Overdue' },
    { value: 'cancelled', label: 'Cancelled' },
]

const statusBadge = {
    draft:     'bg-gray-100 text-gray-600',
    sent:      'bg-blue-100 text-blue-700',
    paid:      'bg-emerald-100 text-emerald-700',
    partial:   'bg-yellow-100 text-yellow-700',
    overdue:   'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-400',
}

function destroy(invoice) {
    if (!confirm(`Delete invoice ${invoice.invoice_number}?`)) return
    router.delete(route('invoices.destroy', { tenant: props.tenant.id, invoice: invoice.id }))
}

function fmt(val) {
    return val != null ? Number(val).toFixed(2) : '—'
}

function fmtDate(val) {
    if (!val) return '—'
    const d = new Date(val)
    return d.toLocaleDateString('en-GB')  // dd/mm/yyyy
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Invoices</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ filteredInvoices.length }} invoice{{ filteredInvoices.length !== 1 ? 's' : '' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Status filter -->
                    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                        <button
                            v-for="f in filters"
                            :key="f.value"
                            @click="statusFilter = f.value"
                            :class="[
                                'px-3 py-1.5 font-medium transition',
                                statusFilter === f.value
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-500 hover:bg-gray-50'
                            ]"
                        >{{ f.label }}</button>
                    </div>
                    <Link
                        v-if="canCreate"
                        :href="route('invoices.create', { tenant: tenant.id })"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Invoice
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 w-8"></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Client</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Issue Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Due Date</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400">Total</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400">Paid</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400">Amount Due</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="inv in filteredInvoices" :key="inv.id">
                                <!-- Invoice row -->
                                <tr class="border-t border-gray-100 hover:bg-gray-50/60 transition">
                                    <td class="px-4 py-3 text-center">
                                        <button @click="toggleExpand(inv.id)" class="text-gray-400 hover:text-gray-600">
                                            <svg :class="['h-4 w-4 transition-transform', expanded.has(inv.id) ? 'rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 font-mono">{{ inv.invoice_number }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ inv.client?.name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ fmtDate(inv.issue_date) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ fmtDate(inv.due_date) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ inv.currency }} {{ fmt(inv.total) }}</td>
                                    <td class="px-4 py-3 text-sm text-right" :class="Number(inv.amount_paid) > 0 ? 'text-emerald-600' : 'text-gray-400'">
                                        {{ Number(inv.amount_paid) > 0 ? `${inv.currency} ${fmt(inv.amount_paid)}` : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right" :class="Number(inv.amount_due) > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">
                                        {{ Number(inv.amount_due) > 0 ? `${inv.currency} ${fmt(inv.amount_due)}` : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusBadge[inv.status]]">
                                            {{ inv.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap text-xs">
                                        <a :href="route('invoices.download', { tenant: tenant.id, invoice: inv.id })" class="text-gray-500 hover:text-gray-700 font-medium mr-2">PDF</a>
                                        <Link v-if="canEdit" :href="route('invoices.edit', { tenant: tenant.id, invoice: inv.id })" class="text-indigo-600 hover:text-indigo-800 font-medium mr-2">Edit</Link>
                                        <button v-if="canDelete" @click="destroy(inv)" class="text-red-500 hover:text-red-700 font-medium">Delete</button>
                                    </td>
                                </tr>

                                <!-- Line items (expanded) -->
                                <tr v-if="expanded.has(inv.id)" class="border-t border-gray-100 bg-gray-50/60">
                                    <td colspan="10" class="px-8 py-3">
                                        <table class="min-w-full text-xs">
                                            <thead>
                                                <tr class="border-b border-gray-200">
                                                    <th class="pb-1.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Description</th>
                                                    <th class="pb-1.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Product</th>
                                                    <th class="pb-1.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Unit</th>
                                                    <th class="pb-1.5 text-right font-semibold text-gray-400 uppercase tracking-wide">Qty</th>
                                                    <th class="pb-1.5 text-right font-semibold text-gray-400 uppercase tracking-wide">Price</th>
                                                    <th class="pb-1.5 text-right font-semibold text-gray-400 uppercase tracking-wide">Tax %</th>
                                                    <th class="pb-1.5 text-right font-semibold text-gray-400 uppercase tracking-wide">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <tr v-for="item in inv.items" :key="item.id" class="text-gray-600">
                                                    <td class="py-1.5">{{ item.description }}</td>
                                                    <td class="py-1.5 text-gray-400">{{ item.product?.name ?? '—' }}</td>
                                                    <td class="py-1.5">{{ item.unit }}</td>
                                                    <td class="py-1.5 text-right">{{ item.quantity }}</td>
                                                    <td class="py-1.5 text-right">{{ fmt(item.unit_price) }}</td>
                                                    <td class="py-1.5 text-right">{{ item.tax_rate }}%</td>
                                                    <td class="py-1.5 text-right font-medium text-gray-800">{{ fmt(item.total) }}</td>
                                                </tr>
                                                <tr v-if="!inv.items?.length">
                                                    <td colspan="7" class="py-2 text-gray-400">No line items.</td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <!-- Totals + notes -->
                                        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                                            <p v-if="inv.notes" class="text-xs text-gray-400 max-w-sm italic">{{ inv.notes }}</p>
                                            <div class="ml-auto text-xs space-y-1 min-w-[180px]">
                                                <div class="flex justify-between text-gray-500">
                                                    <span>Subtotal</span>
                                                    <span>{{ inv.currency }} {{ fmt(inv.subtotal) }}</span>
                                                </div>
                                                <div class="flex justify-between text-gray-500">
                                                    <span>Tax</span>
                                                    <span>{{ inv.currency }} {{ fmt(inv.tax_amount) }}</span>
                                                </div>
                                                <div class="flex justify-between font-semibold text-gray-800 border-t border-gray-200 pt-1">
                                                    <span>Total</span>
                                                    <span>{{ inv.currency }} {{ fmt(inv.total) }}</span>
                                                </div>
                                                <div v-if="Number(inv.amount_paid) > 0" class="flex justify-between text-emerald-600">
                                                    <span>Paid</span>
                                                    <span>{{ inv.currency }} {{ fmt(inv.amount_paid) }}</span>
                                                </div>
                                                <div v-if="Number(inv.amount_due) > 0" class="flex justify-between text-red-600 font-medium">
                                                    <span>Due</span>
                                                    <span>{{ inv.currency }} {{ fmt(inv.amount_due) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!filteredInvoices.length" class="border-t border-gray-100">
                                <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-400">
                                    {{ statusFilter === 'all' ? 'No invoices yet.' : `No ${statusFilter} invoices.` }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
