<script setup>
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant:  Object,
    voucher: Object,
})

const typeBadge = {
    'Sales':       'bg-emerald-100 text-emerald-700',
    'Purchase':    'bg-blue-100 text-blue-700',
    'Receipt':     'bg-violet-100 text-violet-700',
    'Payment':     'bg-orange-100 text-orange-700',
    'CreditNote':  'bg-yellow-100 text-yellow-700',
    'DebitNote':   'bg-red-100 text-red-700',
    'Contra':      'bg-gray-100 text-gray-600',
    'Journal':     'bg-pink-100 text-pink-700',
    'Payroll':     'bg-teal-100 text-teal-700',
    'Attendance':  'bg-sky-100 text-sky-700',
}

function badgeCls(type) {
    return typeBadge[type] ?? 'bg-gray-100 text-gray-600'
}

function formatAmount(v) {
    if (v === null || v === undefined) return '—'
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(v)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString()
}

// Buyer address from inbound is [{BuyerAddress:"..."}], from CRUD it's a plain string
function buyerAddressText(raw) {
    if (!raw) return null
    if (Array.isArray(raw)) return raw.map(a => a.BuyerAddress).filter(Boolean).join(', ') || null
    return raw
}

const v = props.voucher

const hasDispatch = v.delivery_note_no || v.delivery_note_date || v.dispatch_doc_no ||
    v.dispatch_through || v.destination || v.carrier_name ||
    v.lr_no || v.lr_date || v.motor_vehicle_no

const hasOrder = v.order_no || v.order_date || v.terms_of_payment ||
    v.terms_of_delivery || v.other_references

const hasConsignee = v.consignee_name || v.consignee_gstin
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('tally.vouchers.index', { tenant: tenant.id })"
                          class="text-gray-400 hover:text-gray-600 transition">
                        ←
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-semibold text-gray-900">{{ v.voucher_number ?? 'Voucher' }}</h1>
                            <span :class="badgeCls(v.voucher_type)"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ v.voucher_type }}
                            </span>
                            <span v-if="v.is_deleted" class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600 font-medium">Deleted</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5">{{ formatDate(v.voucher_date) }}</p>
                    </div>
                </div>
                <div v-if="v.mapped_invoice" class="text-sm text-violet-600">
                    Mapped Invoice: <span class="font-medium">{{ v.mapped_invoice.invoice_number }}</span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Party & Summary -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Party</p>
                        <p class="text-base font-semibold text-gray-900">{{ v.party_name ?? '—' }}</p>
                        <p v-if="v.party_ledger?.gstin_number" class="text-xs text-gray-500 mt-0.5 font-mono">{{ v.party_ledger.gstin_number }}</p>
                        <p v-if="v.party_ledger?.state_name" class="text-xs text-gray-400 mt-0.5">{{ v.party_ledger.state_name }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total Amount</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatAmount(v.voucher_total) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ v.place_of_supply ? 'Place: ' + v.place_of_supply : '' }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Details</p>
                        <div class="space-y-1 text-sm text-gray-600">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Invoice</span>
                                <span>{{ v.is_invoice ? 'Yes' : 'No' }}</span>
                            </div>
                            <div v-if="v.reference" class="flex justify-between">
                                <span class="text-gray-400">Reference</span>
                                <span class="font-mono text-xs">{{ v.reference }}</span>
                            </div>
                            <div v-if="v.reference_date" class="flex justify-between">
                                <span class="text-gray-400">Ref Date</span>
                                <span class="text-xs">{{ v.reference_date }}</span>
                            </div>
                            <div v-if="v.cost_centre" class="flex justify-between">
                                <span class="text-gray-400">Cost Centre</span>
                                <span class="text-xs">{{ v.cost_centre }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Last Synced</span>
                                <span class="text-xs">{{ formatDate(v.last_synced_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Narration -->
                <div v-if="v.narration" class="bg-violet-50 rounded-xl border border-violet-100 px-5 py-4">
                    <p class="text-xs font-semibold text-violet-400 uppercase tracking-wide mb-1">Narration</p>
                    <p class="text-sm text-gray-700">{{ v.narration }}</p>
                </div>

                <!-- Inventory Entries -->
                <div v-if="v.inventory_entries?.length">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Inventory Entries</h2>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-5 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-4">Item</div>
                            <div class="col-span-1">HSN</div>
                            <div class="col-span-1 text-center">Unit</div>
                            <div class="col-span-1 text-center">Qty</div>
                            <div class="col-span-2 text-right">Rate</div>
                            <div class="col-span-1 text-right">IGST %</div>
                            <div class="col-span-2 text-right">Amount</div>
                        </div>
                        <div v-for="entry in v.inventory_entries" :key="entry.id"
                             class="grid grid-cols-12 items-center px-5 py-3 border-b border-gray-50 last:border-0">
                            <div class="col-span-4">
                                <p class="text-sm font-medium text-gray-800">{{ entry.stock_item_name ?? entry.stock_item?.name ?? '—' }}</p>
                                <p v-if="entry.godown_name" class="text-xs text-gray-400">{{ entry.godown_name }}</p>
                            </div>
                            <div class="col-span-1 text-xs text-gray-500 font-mono">{{ entry.hsn_code ?? '—' }}</div>
                            <div class="col-span-1 text-center text-sm text-gray-500">{{ entry.unit ?? '—' }}</div>
                            <div class="col-span-1 text-center text-sm text-gray-700">{{ entry.billed_qty }}</div>
                            <div class="col-span-2 text-right text-sm text-gray-700">{{ formatAmount(entry.rate) }}</div>
                            <div class="col-span-1 text-right text-sm text-gray-500">{{ entry.igst_rate ?? '—' }}%</div>
                            <div class="col-span-2 text-right text-sm font-semibold text-gray-900">{{ formatAmount(entry.amount) }}</div>
                        </div>
                        <div class="grid grid-cols-12 px-5 py-3 bg-gray-50 border-t border-gray-100">
                            <div class="col-span-10 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Total Tax</div>
                            <div class="col-span-2 text-right text-sm font-semibold text-gray-700">
                                {{ formatAmount(v.inventory_entries.reduce((s, e) => s + parseFloat(e.tax_amount ?? 0), 0)) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ledger Entries -->
                <div v-if="v.ledger_entries?.length">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Ledger Entries</h2>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-5 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-5">Ledger</div>
                            <div class="col-span-3">Group</div>
                            <div class="col-span-2 text-center">Type</div>
                            <div class="col-span-2 text-right">Amount</div>
                        </div>
                        <div v-for="entry in v.ledger_entries" :key="entry.id"
                             class="grid grid-cols-12 items-center px-5 py-3 border-b border-gray-50 last:border-0">
                            <div class="col-span-5 text-sm text-gray-800">
                                {{ entry.ledger_name ?? entry.ledger?.ledger_name ?? '—' }}
                                <span v-if="entry.is_party_ledger"
                                      class="ml-1 text-xs px-1.5 py-0.5 rounded bg-violet-100 text-violet-600">Party</span>
                            </div>
                            <div class="col-span-3 text-xs text-gray-500">{{ entry.ledger_group ?? '—' }}</div>
                            <div class="col-span-2 text-center text-xs font-medium"
                                 :class="entry.is_deemed_positive ? 'text-green-600' : 'text-red-500'">
                                {{ entry.is_deemed_positive ? 'Dr' : 'Cr' }}
                            </div>
                            <div class="col-span-2 text-right text-sm font-medium"
                                 :class="entry.is_deemed_positive ? 'text-gray-900' : 'text-gray-500'">
                                {{ formatAmount(Math.abs(entry.ledger_amount)) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buyer Info -->
                <div v-if="v.buyer_name || v.buyer_gstin" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Buyer Details</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div v-if="v.buyer_name">
                            <p class="text-xs text-gray-400 mb-0.5">Name</p>
                            <p class="text-gray-800">{{ v.buyer_name }}</p>
                            <p v-if="v.buyer_alias" class="text-xs text-gray-400">{{ v.buyer_alias }}</p>
                        </div>
                        <div v-if="v.buyer_gstin">
                            <p class="text-xs text-gray-400 mb-0.5">GSTIN</p>
                            <p class="font-mono text-gray-800">{{ v.buyer_gstin }}</p>
                            <p v-if="v.buyer_gst_registration_type" class="text-xs text-gray-400">{{ v.buyer_gst_registration_type }}</p>
                        </div>
                        <div v-if="v.buyer_state || v.buyer_country">
                            <p class="text-xs text-gray-400 mb-0.5">Location</p>
                            <p class="text-gray-800">{{ [v.buyer_state, v.buyer_country].filter(Boolean).join(', ') }}</p>
                            <p v-if="v.buyer_pin_code" class="text-xs text-gray-400">PIN {{ v.buyer_pin_code }}</p>
                        </div>
                        <div v-if="v.buyer_email || v.buyer_mobile">
                            <p class="text-xs text-gray-400 mb-0.5">Contact</p>
                            <p v-if="v.buyer_email" class="text-gray-800 text-xs">{{ v.buyer_email }}</p>
                            <p v-if="v.buyer_mobile" class="text-gray-800 text-xs">{{ v.buyer_mobile }}</p>
                        </div>
                        <div v-if="buyerAddressText(v.buyer_address)" class="col-span-2 sm:col-span-4">
                            <p class="text-xs text-gray-400 mb-0.5">Address</p>
                            <p class="text-gray-800 text-sm">{{ buyerAddressText(v.buyer_address) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Consignee Info -->
                <div v-if="hasConsignee" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Consignee Details</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div v-if="v.consignee_name">
                            <p class="text-xs text-gray-400 mb-0.5">Name</p>
                            <p class="text-gray-800">{{ v.consignee_name }}</p>
                        </div>
                        <div v-if="v.consignee_gstin">
                            <p class="text-xs text-gray-400 mb-0.5">GSTIN</p>
                            <p class="font-mono text-gray-800">{{ v.consignee_gstin }}</p>
                            <p v-if="v.consignee_gst_registration_type" class="text-xs text-gray-400">{{ v.consignee_gst_registration_type }}</p>
                        </div>
                        <div v-if="v.consignee_state || v.consignee_country">
                            <p class="text-xs text-gray-400 mb-0.5">Location</p>
                            <p class="text-gray-800">{{ [v.consignee_state, v.consignee_country].filter(Boolean).join(', ') }}</p>
                            <p v-if="v.consignee_pin_code" class="text-xs text-gray-400">PIN {{ v.consignee_pin_code }}</p>
                        </div>
                        <div v-if="v.consignee_tally_group">
                            <p class="text-xs text-gray-400 mb-0.5">Tally Group</p>
                            <p class="text-gray-800">{{ v.consignee_tally_group }}</p>
                        </div>
                    </div>
                </div>

                <!-- Dispatch & Shipping -->
                <div v-if="hasDispatch" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Dispatch &amp; Shipping</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div v-if="v.delivery_note_no">
                            <p class="text-xs text-gray-400 mb-0.5">Delivery Note No</p>
                            <p class="text-gray-800 font-mono text-xs">{{ v.delivery_note_no }}</p>
                        </div>
                        <div v-if="v.delivery_note_date">
                            <p class="text-xs text-gray-400 mb-0.5">Delivery Note Date</p>
                            <p class="text-gray-800 text-xs">{{ v.delivery_note_date }}</p>
                        </div>
                        <div v-if="v.dispatch_doc_no">
                            <p class="text-xs text-gray-400 mb-0.5">Dispatch Doc No</p>
                            <p class="text-gray-800 font-mono text-xs">{{ v.dispatch_doc_no }}</p>
                        </div>
                        <div v-if="v.dispatch_through">
                            <p class="text-xs text-gray-400 mb-0.5">Dispatch Through</p>
                            <p class="text-gray-800 text-xs">{{ v.dispatch_through }}</p>
                        </div>
                        <div v-if="v.destination">
                            <p class="text-xs text-gray-400 mb-0.5">Destination</p>
                            <p class="text-gray-800 text-xs">{{ v.destination }}</p>
                        </div>
                        <div v-if="v.carrier_name">
                            <p class="text-xs text-gray-400 mb-0.5">Carrier</p>
                            <p class="text-gray-800 text-xs">{{ v.carrier_name }}</p>
                        </div>
                        <div v-if="v.lr_no">
                            <p class="text-xs text-gray-400 mb-0.5">LR No</p>
                            <p class="text-gray-800 font-mono text-xs">{{ v.lr_no }}</p>
                        </div>
                        <div v-if="v.lr_date">
                            <p class="text-xs text-gray-400 mb-0.5">LR Date</p>
                            <p class="text-gray-800 text-xs">{{ v.lr_date }}</p>
                        </div>
                        <div v-if="v.motor_vehicle_no">
                            <p class="text-xs text-gray-400 mb-0.5">Vehicle No</p>
                            <p class="text-gray-800 font-mono text-xs">{{ v.motor_vehicle_no }}</p>
                        </div>
                    </div>
                </div>

                <!-- Order Details -->
                <div v-if="hasOrder" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Order Details</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div v-if="v.order_no">
                            <p class="text-xs text-gray-400 mb-0.5">Order No</p>
                            <p class="text-gray-800 font-mono text-xs">{{ v.order_no }}</p>
                        </div>
                        <div v-if="v.order_date">
                            <p class="text-xs text-gray-400 mb-0.5">Order Date</p>
                            <p class="text-gray-800 text-xs">{{ v.order_date }}</p>
                        </div>
                        <div v-if="v.terms_of_payment">
                            <p class="text-xs text-gray-400 mb-0.5">Terms of Payment</p>
                            <p class="text-gray-800 text-xs">{{ v.terms_of_payment }}</p>
                        </div>
                        <div v-if="v.terms_of_delivery">
                            <p class="text-xs text-gray-400 mb-0.5">Terms of Delivery</p>
                            <p class="text-gray-800 text-xs">{{ v.terms_of_delivery }}</p>
                        </div>
                        <div v-if="v.other_references">
                            <p class="text-xs text-gray-400 mb-0.5">Other References</p>
                            <p class="text-gray-800 text-xs">{{ v.other_references }}</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
