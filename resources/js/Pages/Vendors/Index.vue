<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    business: Object,
    vendors:  Array,
})

// ── Form ──────────────────────────────────────────────────────
const showModal  = ref(false)
const editing    = ref(null)

const canCreate = hasPermission('create vendor')
const canEdit   = hasPermission('edit vendor')
const canDelete = hasPermission('delete vendor')

const form = useForm({ name: '', email: '', phone: '' })

function openCreate() {
    editing.value = null
    form.reset()
    showModal.value = true
}

function openEdit(vendor) {
    editing.value = vendor
    form.name  = vendor.name
    form.email = vendor.email ?? ''
    form.phone = vendor.phone ?? ''
    showModal.value = true
}

function submit() {
    if (editing.value) {
        form.put(route('vendors.update', { business: props.business.id, vendor: editing.value.id }), {
            onSuccess: () => (showModal.value = false),
        })
    } else {
        form.post(route('vendors.store', { business: props.business.id }), {
            onSuccess: () => (showModal.value = false),
        })
    }
}

function destroy(vendor) {
    if (!confirm(`Remove ${vendor.name}?`)) return
    router.delete(route('vendors.destroy', { business: props.business.id, vendor: vendor.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Vendors</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ vendors.length }} vendor{{ vendors.length !== 1 ? 's' : '' }}</p>
                </div>
                <button
                    v-if="canCreate"
                    @click="openCreate"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Vendor
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-4">Name</div>
                        <div class="col-span-4">Email</div>
                        <div class="col-span-3">Phone</div>
                        <div class="col-span-1"></div>
                    </div>

                    <div
                        v-for="vendor in vendors"
                        :key="vendor.id"
                        class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition"
                    >
                        <div class="col-span-4 text-sm font-medium text-gray-900">{{ vendor.name }}</div>
                        <div class="col-span-4 text-sm text-gray-500">{{ vendor.email ?? '—' }}</div>
                        <div class="col-span-3 text-sm text-gray-500">{{ vendor.phone ?? '—' }}</div>
                        <div class="col-span-1 flex justify-end gap-2">
                            <button v-if="canEdit" @click="openEdit(vendor)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button v-if="canDelete" @click="destroy(vendor)" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                        </div>
                    </div>

                    <p v-if="!vendors.length" class="text-center text-gray-400 py-12 text-sm">No vendors yet.</p>
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
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ editing ? 'Edit Vendor' : 'Add Vendor' }}</h2>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="form.email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input v-model="form.phone" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button @click="submit" :disabled="form.processing" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition">
                            {{ editing ? 'Save' : 'Add Vendor' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
