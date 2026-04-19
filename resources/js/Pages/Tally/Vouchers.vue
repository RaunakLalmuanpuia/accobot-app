<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant:   Object,
    vouchers: Array,
})

const search     = ref('')
const typeFilter = ref('all')

const voucherTypes = computed(() => {
    const set = new Set(props.vouchers.map(v => v.voucher_type))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.vouchers
    if (typeFilter.value !== 'all') {
        list = list.filter(v => v.voucher_type === typeFilter.value)
    }
    const q = search.value.toLowerCase()
    if (q) {
        list = list.filter(v =>
            (v.voucher_number ?? '').toLowerCase().includes(q) ||
            (v.party_name ?? '').toLowerCase().includes(q) ||
            (v.narration ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

const typeBadge = {
    'Sales':       'bg-emerald-100 text-emerald-700',
    'Purchase':    'bg-blue-100 text-blue-700',
    'Receipt':     'bg-violet-100 text-violet-700',
    'Payment':     'bg-orange-100 text-orange-700',
    'Credit Note': 'bg-yellow-100 text-yellow-700',
    'Debit Note':  'bg-red-100 text-red-700',
    'Contra':      'bg-gray-100 text-gray-600',
    'Journal':     'bg-pink-100 text-pink-700',
}

function badgeCls(type) {
    return typeBadge[type] ?? 'bg-gray-100 text-gray-600'
}

function formatAmount(v) {
    if (v === null || v === undefined) return '—'
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(v)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-IN')
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Vouchers</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ vouchers.length }} vouchers synced from Tally</p>
                </div>
                <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                      class="text-sm text-gray-500 hover:text-gray-700">
                    ← Back to Sync
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search vouchers…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />

                    <div class="flex flex-wrap gap-2">
                        <button v-for="type in voucherTypes" :key="type"
                                @click="typeFilter = type"
                                :class="[
                                    'px-3 py-1.5 text-xs font-medium rounded-full border transition',
                                    typeFilter === type
                                        ? 'bg-violet-600 text-white border-violet-600'
                                        : 'bg-white text-gray-600 border-gray-300 hover:border-violet-400'
                                ]">
                            {{ type === 'all' ? 'All Types' : type }}
                        </button>
                    </div>

                    <span class="text-sm text-gray-400 ml-auto">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-2">Type</div>
                        <div class="col-span-2">Number</div>
                        <div class="col-span-1">Date</div>
                        <div class="col-span-3">Party</div>
                        <div class="col-span-2 text-right">Amount</div>
                        <div class="col-span-1 text-center">Invoice</div>
                        <div class="col-span-1 text-center">Mapped</div>
                    </div>

                    <Link v-for="voucher in filtered" :key="voucher.id"
                          :href="route('tally.vouchers.show', { tenant: tenant.id, voucher: voucher.id })"
                          class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition cursor-pointer">
                        <div class="col-span-2">
                            <span :class="badgeCls(voucher.voucher_type)"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ voucher.voucher_type }}
                            </span>
                        </div>
                        <div class="col-span-2 text-sm font-mono text-gray-700">{{ voucher.voucher_number ?? '—' }}</div>
                        <div class="col-span-1 text-sm text-gray-500">{{ formatDate(voucher.voucher_date) }}</div>
                        <div class="col-span-3">
                            <p class="text-sm text-gray-800 truncate">{{ voucher.party_name ?? '—' }}</p>
                            <p v-if="voucher.narration" class="text-xs text-gray-400 truncate mt-0.5">{{ voucher.narration }}</p>
                        </div>
                        <div class="col-span-2 text-right text-sm font-medium text-gray-900">
                            {{ formatAmount(voucher.voucher_total) }}
                        </div>
                        <div class="col-span-1 text-center">
                            <span v-if="voucher.is_invoice"
                                  class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">Yes</span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span v-if="voucher.mapped_invoice_id"
                                  class="text-xs px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 font-medium">Invoice</span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </div>
                    </Link>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No vouchers found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
