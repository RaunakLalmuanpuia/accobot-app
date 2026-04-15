<script setup>
import { computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    tenant:   Object,
    invoice:  Object,   // null = create mode
    clients:  Array,
    products: Array,
})

const isEdit = computed(() => !!props.invoice)

const form = useForm({
    client_id:  props.invoice?.client_id  ?? '',
    issue_date: props.invoice?.issue_date ?? new Date().toISOString().slice(0, 10),
    due_date:   props.invoice?.due_date   ?? '',
    status:       props.invoice?.status      ?? 'draft',
    currency:     props.invoice?.currency    ?? 'INR',
    amount_paid:  props.invoice?.amount_paid ?? 0,
    notes:        props.invoice?.notes       ?? '',
    items: props.invoice?.items?.map(i => ({
        product_id:  i.product_id  ?? '',
        description: i.description,
        unit:        i.unit        ?? 'unit',
        quantity:    i.quantity,
        unit_price:  i.unit_price,
        tax_rate:    i.tax_rate    ?? 0,
    })) ?? [blankItem()],
})

function blankItem() {
    return { product_id: '', description: '', unit: 'unit', quantity: 1, unit_price: 0, tax_rate: 0 }
}

function addItem() {
    form.items.push(blankItem())
}

function removeItem(idx) {
    form.items.splice(idx, 1)
}

function onProductSelect(idx) {
    const pid = form.items[idx].product_id
    if (!pid) return
    const product = props.products.find(p => p.id == pid)
    if (!product) return
    form.items[idx].description = product.name
    form.items[idx].unit        = product.unit ?? 'unit'
    form.items[idx].unit_price  = product.unit_price
    form.items[idx].tax_rate    = product.tax_rate ?? 0
}

// ── Totals ────────────────────────────────────────────────────
const itemTotals = computed(() =>
    form.items.map(item => {
        const sub = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)
        const tax = sub * (Number(item.tax_rate) || 0) / 100
        return { sub: sub.toFixed(2), tax: tax.toFixed(2), total: (sub + tax).toFixed(2) }
    })
)

const subtotal  = computed(() => form.items.reduce((s, _, i) => s + Number(itemTotals.value[i].sub),   0))
const taxTotal  = computed(() => form.items.reduce((s, _, i) => s + Number(itemTotals.value[i].tax),   0))
const grandTotal = computed(() => (subtotal.value + taxTotal.value).toFixed(2))

function submit() {
    if (isEdit.value) {
        form.put(route('invoices.update', { tenant: props.tenant.id, invoice: props.invoice.id }))
    } else {
        form.post(route('invoices.store', { tenant: props.tenant.id }))
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? `Edit ${invoice.invoice_number}` : 'New Invoice' }}</h1>
                </div>
                <Link :href="route('invoices.index', { tenant: tenant.id })" class="text-sm text-gray-500 hover:text-gray-700">← Back to Invoices</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Header fields -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4">Invoice Details</h2>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                            <select v-model="form.client_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="" disabled>Select client</option>
                                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.client_id" class="mt-1 text-xs text-red-500">{{ form.errors.client_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Issue Date <span class="text-red-500">*</span></label>
                            <input v-model="form.issue_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.issue_date" class="mt-1 text-xs text-red-500">{{ form.errors.issue_date }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input v-model="form.due_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="draft">Draft</option>
                                <option value="sent">Sent</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                                <option value="overdue">Overdue</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                            <input v-model="form.currency" type="text" maxlength="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount Paid</label>
                            <input v-model="form.amount_paid" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.amount_paid" class="mt-1 text-xs text-red-500">{{ form.errors.amount_paid }}</p>
                        </div>
                        <div class="col-span-2 sm:col-span-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Line items -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-700">Line Items</h2>
                        <button @click="addItem" type="button" class="inline-flex items-center gap-1 text-sm text-violet-600 hover:text-violet-800 font-medium">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Line
                        </button>
                    </div>

                    <p v-if="form.errors.items" class="mb-2 text-xs text-red-500">{{ form.errors.items }}</p>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="pb-2 text-left text-xs font-semibold text-gray-400 w-36">Product</th>
                                    <th class="pb-2 text-left text-xs font-semibold text-gray-400">Description</th>
                                    <th class="pb-2 text-left text-xs font-semibold text-gray-400 w-16">Unit</th>
                                    <th class="pb-2 text-right text-xs font-semibold text-gray-400 w-20">Qty</th>
                                    <th class="pb-2 text-right text-xs font-semibold text-gray-400 w-24">Price</th>
                                    <th class="pb-2 text-right text-xs font-semibold text-gray-400 w-16">Tax %</th>
                                    <th class="pb-2 text-right text-xs font-semibold text-gray-400 w-24">Total</th>
                                    <th class="pb-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in form.items" :key="idx" class="border-b border-gray-50 last:border-0">
                                    <td class="py-2 pr-2">
                                        <select
                                            v-model="item.product_id"
                                            @change="onProductSelect(idx)"
                                            class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        >
                                            <option value="">— none —</option>
                                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input v-model="item.description" type="text" placeholder="Description" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                        <p v-if="form.errors[`items.${idx}.description`]" class="mt-0.5 text-xs text-red-500">Required</p>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input v-model="item.unit" type="text" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input v-model="item.quantity" type="number" step="0.01" min="0.01" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input v-model="item.unit_price" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input v-model="item.tax_rate" type="number" step="0.01" min="0" max="100" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                    </td>
                                    <td class="py-2 pr-2 text-right text-sm text-gray-700 font-medium whitespace-nowrap">
                                        {{ itemTotals[idx].total }}
                                    </td>
                                    <td class="py-2 text-center">
                                        <button v-if="form.items.length > 1" @click="removeItem(idx)" type="button" class="text-red-400 hover:text-red-600">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="mt-4 flex justify-end">
                        <div class="w-56 space-y-1 text-sm">
                            <div class="flex justify-between text-gray-500">
                                <span>Subtotal</span>
                                <span>{{ form.currency }} {{ subtotal.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>Tax</span>
                                <span>{{ form.currency }} {{ taxTotal.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between font-semibold text-gray-900 border-t border-gray-200 pt-1">
                                <span>Total</span>
                                <span>{{ form.currency }} {{ grandTotal }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('invoices.index', { tenant: tenant.id })" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 hover:bg-gray-50">
                        Cancel
                    </Link>
                    <button @click="submit" :disabled="form.processing" class="rounded-lg px-5 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 transition disabled:opacity-50">
                        {{ isEdit ? 'Save Changes' : 'Create Invoice' }}
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
