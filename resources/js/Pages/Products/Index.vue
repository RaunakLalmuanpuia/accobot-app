<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:   Object,
    products: Array,
})

const showModal = ref(false)
const editing   = ref(null)

const canCreate = hasPermission('products.create')
const canEdit   = hasPermission('products.edit')
const canDelete = hasPermission('products.delete')

const form = useForm({
    name:           '',
    description:    '',
    sku:            '',
    unit:           '',
    unit_price:     '',
    tax_rate:       '',
    stock_quantity: '',
    category:       '',
    sub_category:   '',
    main_group:     '',
    sub_group:      '',
    is_active:      true,
})

function openCreate() {
    editing.value = null
    form.reset()
    form.is_active = true
    showModal.value = true
}

function openEdit(product) {
    editing.value       = product
    form.name           = product.name
    form.description    = product.description    ?? ''
    form.sku            = product.sku            ?? ''
    form.unit           = product.unit           ?? ''
    form.unit_price     = product.unit_price     ?? ''
    form.tax_rate       = product.tax_rate       ?? ''
    form.stock_quantity = product.stock_quantity ?? ''
    form.category       = product.category       ?? ''
    form.sub_category   = product.sub_category   ?? ''
    form.main_group     = product.main_group     ?? ''
    form.sub_group      = product.sub_group      ?? ''
    form.is_active      = product.is_active
    showModal.value = true
}

function submit() {
    if (editing.value) {
        form.put(route('products.update', { tenant: props.tenant.id, product: editing.value.id }), {
            onSuccess: () => (showModal.value = false),
        })
    } else {
        form.post(route('products.store', { tenant: props.tenant.id }), {
            onSuccess: () => (showModal.value = false),
        })
    }
}

function destroy(product) {
    if (!confirm(`Remove ${product.name}?`)) return
    router.delete(route('products.destroy', { tenant: props.tenant.id, product: product.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Inventory</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ products.length }} product{{ products.length !== 1 ? 's' : '' }}</p>
                </div>
                <button
                    v-if="canCreate"
                    @click="openCreate"
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Product
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-x-auto">

                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">SKU</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Unit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400">Price</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400">Tax %</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400">Stock</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Group</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr
                                v-for="product in products"
                                :key="product.id"
                                class="hover:bg-gray-50/60 transition"
                            >
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
                                    <div v-if="product.description" class="text-xs text-gray-400 truncate max-w-[180px]">{{ product.description }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ product.sku ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ product.unit ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ product.unit_price != null ? Number(product.unit_price).toFixed(2) : '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 text-right">{{ product.tax_rate != null ? Number(product.tax_rate).toFixed(2) : '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 text-right">{{ product.stock_quantity ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-700">{{ product.category ?? '—' }}</div>
                                    <div v-if="product.sub_category" class="text-xs text-gray-400">{{ product.sub_category }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-700">{{ product.main_group ?? '—' }}</div>
                                    <div v-if="product.sub_group" class="text-xs text-gray-400">{{ product.sub_group }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="product.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                                        {{ product.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button v-if="canEdit" @click="openEdit(product)" class="text-xs text-violet-600 hover:text-violet-800 font-medium mr-2">Edit</button>
                                    <button v-if="canDelete" @click="destroy(product)" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!products.length">
                                <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-400">No products yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @click.self="showModal = false"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-gray-900">{{ editing ? 'Edit Product' : 'Add Product' }}</h2>

                    <div class="space-y-3">

                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"></textarea>
                        </div>

                        <!-- SKU / Unit -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                                <input v-model="form.sku" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                <p v-if="form.errors.sku" class="mt-1 text-xs text-red-500">{{ form.errors.sku }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                                <input v-model="form.unit" type="text" placeholder="e.g. kg, pcs, box" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>

                        <!-- Unit Price / Tax Rate -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price <span class="text-red-500">*</span></label>
                                <input v-model="form.unit_price" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                <p v-if="form.errors.unit_price" class="mt-1 text-xs text-red-500">{{ form.errors.unit_price }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tax Rate (%)</label>
                                <input v-model="form.tax_rate" type="number" step="0.01" min="0" max="100" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                <p v-if="form.errors.tax_rate" class="mt-1 text-xs text-red-500">{{ form.errors.tax_rate }}</p>
                            </div>
                        </div>

                        <!-- Stock Quantity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                            <input v-model="form.stock_quantity" type="number" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.stock_quantity" class="mt-1 text-xs text-red-500">{{ form.errors.stock_quantity }}</p>
                        </div>

                        <!-- Category / Sub Category -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                <input v-model="form.category" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sub Category</label>
                                <input v-model="form.sub_category" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>

                        <!-- Main Group / Sub Group -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Main Group</label>
                                <input v-model="form.main_group" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sub Group</label>
                                <input v-model="form.sub_group" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>

                        <!-- Is Active -->
                        <div class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" id="is_active" class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                            <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button @click="submit" :disabled="form.processing" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 transition">
                            {{ editing ? 'Save' : 'Add Product' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
