<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    business: Object,
    roles: Array,
    permissionGroups: Array,
    systemRoles: Array,
})

const business = props.business

// ── Modal state ────────────────────────────────────────────────
const showModal   = ref(false)
const editingRole = ref(null)   // null = create mode, object = edit mode

const form = ref({ name: '', permissions: [] })
const errors = ref({})

function openCreate() {
    editingRole.value = null
    form.value = { name: '', permissions: [] }
    errors.value = {}
    showModal.value = true
}

function openEdit(role) {
    editingRole.value = role
    form.value = {
        name: role.name,
        permissions: role.permissions.map(p => p.name),
    }
    errors.value = {}
    showModal.value = true
}

function closeModal() {
    showModal.value = false
}

// ── Permission helpers ─────────────────────────────────────────
const allSelected = computed(() =>
    props.permissionGroups.every(g =>
        g.permissions.every(p => form.value.permissions.includes(p.name))
    )
)

function toggleAll() {
    if (allSelected.value) {
        form.value.permissions = []
    } else {
        form.value.permissions = props.permissionGroups
            .flatMap(g => g.permissions.map(p => p.name))
    }
}

function toggleGroup(group) {
    const names = group.permissions.map(p => p.name)
    const allIn = names.every(n => form.value.permissions.includes(n))
    if (allIn) {
        form.value.permissions = form.value.permissions.filter(p => !names.includes(p))
    } else {
        form.value.permissions = [...new Set([...form.value.permissions, ...names])]
    }
}

function groupSelected(group) {
    return group.permissions.every(p => form.value.permissions.includes(p.name))
}

function groupPartial(group) {
    const names = group.permissions.map(p => p.name)
    return names.some(n => form.value.permissions.includes(n)) && !groupSelected(group)
}

// ── Submit ─────────────────────────────────────────────────────
function submit() {
    errors.value = {}

    if (editingRole.value) {
        router.put(route('roles.update', { business: business.id, role: editingRole.value.id }), form.value, {
            onSuccess: closeModal,
            onError: e => (errors.value = e),
        })
    } else {
        router.post(route('roles.store', { business: business.id }), form.value, {
            onSuccess: closeModal,
            onError: e => (errors.value = e),
        })
    }
}

// ── Delete ─────────────────────────────────────────────────────
const confirmDelete = ref(null)

function deleteRole(role) {
    router.delete(route('roles.destroy', { business: business.id, role: role.id }), {
        onSuccess: () => (confirmDelete.value = null),
    })
}

// ── Role permission count label ────────────────────────────────
function rolePermCount(role) {
    const n = role.permissions.length
    return n === 1 ? '1 permission' : `${n} permissions`
}

const isSystem = (role) => props.systemRoles.includes(role.name)
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Roles & Permissions</h1>
                <button
                    @click="openCreate"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Role
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Role cards -->
                <div
                    v-for="role in roles"
                    :key="role.id"
                    class="bg-white rounded-xl border border-gray-200 px-6 py-4 flex items-start justify-between gap-4 shadow-sm"
                >
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="font-semibold text-gray-900 capitalize">{{ role.name }}</span>
                            <span
                                v-if="isSystem(role)"
                                class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium"
                            >system</span>
                            <span
                                v-if="role.is_customized"
                                class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium"
                            >customized</span>
                        </div>
                        <p class="text-xs text-gray-400 mb-3">{{ rolePermCount(role) }}</p>

                        <!-- Permission badges grouped -->
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in role.permissions"
                                :key="perm.id"
                                class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"
                            >{{ perm.name }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            @click="openEdit(role)"
                            :disabled="isSystem(role)"
                            class="text-sm text-indigo-600 hover:text-indigo-800 disabled:opacity-30 disabled:cursor-not-allowed font-medium"
                        >Edit</button>
                        <span class="text-gray-300">|</span>
                        <button
                            @click="confirmDelete = role"
                            :disabled="isSystem(role)"
                            class="text-sm text-red-500 hover:text-red-700 disabled:opacity-30 disabled:cursor-not-allowed font-medium"
                        >Delete</button>
                    </div>
                </div>

                <p v-if="!roles.length" class="text-center text-gray-400 py-12">No roles yet.</p>
            </div>
        </div>

        <!-- ── Create / Edit modal ─────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ editingRole ? `Edit "${editingRole.name}"` : 'Create Role' }}
                        </h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Body (scrollable) -->
                    <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

                        <!-- Role name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Accountant"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
                        </div>

                        <!-- Select all toggle -->
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-700">Permissions</p>
                            <button
                                type="button"
                                @click="toggleAll"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                            >{{ allSelected ? 'Deselect all' : 'Select all' }}</button>
                        </div>

                        <!-- Permission groups -->
                        <div
                            v-for="group in permissionGroups"
                            :key="group.group"
                            class="rounded-xl border border-gray-100 overflow-hidden"
                        >
                            <!-- Group header -->
                            <label class="flex items-center gap-3 px-4 py-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition">
                                <input
                                    type="checkbox"
                                    :checked="groupSelected(group)"
                                    :indeterminate="groupPartial(group)"
                                    @change="toggleGroup(group)"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                />
                                <span class="text-sm font-semibold text-gray-700">{{ group.group }}</span>
                                <span class="ml-auto text-xs text-gray-400">
                                    {{ group.permissions.filter(p => form.permissions.includes(p.name)).length }} / {{ group.permissions.length }}
                                </span>
                            </label>

                            <!-- Individual permissions -->
                            <div class="grid grid-cols-2 gap-0 divide-y divide-gray-50">
                                <label
                                    v-for="perm in group.permissions"
                                    :key="perm.id"
                                    class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-indigo-50 transition"
                                >
                                    <input
                                        type="checkbox"
                                        :value="perm.name"
                                        v-model="form.permissions"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                    />
                                    <span class="text-sm text-gray-600">{{ perm.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                        <button
                            @click="closeModal"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 transition"
                        >Cancel</button>
                        <button
                            @click="submit"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition"
                        >{{ editingRole ? 'Save changes' : 'Create role' }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Delete confirm dialog ───────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="confirmDelete"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                            <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Delete "{{ confirmDelete.name }}"?</p>
                            <p class="text-sm text-gray-500 mt-0.5">Users with this role will lose their permissions.</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button
                            @click="confirmDelete = null"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50"
                        >Cancel</button>
                        <button
                            @click="deleteRole(confirmDelete)"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                        >Delete</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
