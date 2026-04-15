<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant: Object,
    heads:  Array,
})

const canCreate = hasPermission('narration_heads.create')
const canEdit   = hasPermission('narration_heads.edit')
const canDelete = hasPermission('narration_heads.delete')

// ── Filter ────────────────────────────────────────────────────
const typeFilter = ref('all')
const filteredHeads = computed(() =>
    typeFilter.value === 'all'
        ? props.heads
        : props.heads.filter(h => h.type === typeFilter.value)
)

// ── Expanded rows ─────────────────────────────────────────────
const expanded = ref(new Set())
function toggleExpand(id) {
    expanded.value.has(id) ? expanded.value.delete(id) : expanded.value.add(id)
    expanded.value = new Set(expanded.value)
}

// ── Head modal ────────────────────────────────────────────────
const showHeadModal = ref(false)
const editingHead   = ref(null)

const headForm = useForm({
    name:        '',
    type:        'both',
    description: '',
    sort_order:  0,
    is_active:   true,
})

function openCreateHead() {
    editingHead.value = null
    headForm.reset()
    headForm.type      = 'both'
    headForm.is_active = true
    headForm.sort_order = 0
    showHeadModal.value = true
}

function openEditHead(head) {
    editingHead.value      = head
    headForm.name          = head.name
    headForm.type          = head.type
    headForm.description   = head.description ?? ''
    headForm.sort_order    = head.sort_order
    headForm.is_active     = head.is_active
    showHeadModal.value    = true
}

function submitHead() {
    if (editingHead.value) {
        headForm.put(route('narration-heads.update', { tenant: props.tenant.id, narration_head: editingHead.value.id }), {
            onSuccess: () => (showHeadModal.value = false),
        })
    } else {
        headForm.post(route('narration-heads.store', { tenant: props.tenant.id }), {
            onSuccess: () => (showHeadModal.value = false),
        })
    }
}

function destroyHead(head) {
    if (!confirm(`Remove "${head.name}" and all its sub-heads?`)) return
    router.delete(route('narration-heads.destroy', { tenant: props.tenant.id, narration_head: head.id }))
}

// ── Sub-head modal ────────────────────────────────────────────
const showSubModal    = ref(false)
const editingSubHead  = ref(null)
const subHeadParentId = ref(null)

const subForm = useForm({
    name:           '',
    ledger_code:    '',
    ledger_name:    '',
    description:    '',
    requires_party: false,
    sort_order:     0,
    is_active:      true,
})

function openCreateSubHead(head) {
    subHeadParentId.value = head.id
    editingSubHead.value  = null
    subForm.reset()
    subForm.is_active  = true
    subForm.sort_order = 0
    showSubModal.value = true
}

function openEditSubHead(head, sub) {
    subHeadParentId.value      = head.id
    editingSubHead.value       = sub
    subForm.name               = sub.name
    subForm.ledger_code        = sub.ledger_code    ?? ''
    subForm.ledger_name        = sub.ledger_name    ?? ''
    subForm.description        = sub.description    ?? ''
    subForm.requires_party     = sub.requires_party
    subForm.sort_order         = sub.sort_order
    subForm.is_active          = sub.is_active
    showSubModal.value         = true
}

function submitSubHead() {
    const params = { tenant: props.tenant.id, narration_head: subHeadParentId.value }
    if (editingSubHead.value) {
        subForm.put(route('narration-heads.sub-heads.update', { ...params, narration_sub_head: editingSubHead.value.id }), {
            onSuccess: () => (showSubModal.value = false),
        })
    } else {
        subForm.post(route('narration-heads.sub-heads.store', params), {
            onSuccess: () => (showSubModal.value = false),
        })
    }
}

function destroySubHead(head, sub) {
    if (!confirm(`Remove sub-head "${sub.name}"?`)) return
    router.delete(route('narration-heads.sub-heads.destroy', {
        tenant: props.tenant.id,
        narration_head: head.id,
        narration_sub_head: sub.id,
    }))
}

const typeLabel = { credit: 'Credit', debit: 'Debit', both: 'Both' }
const typeBadge = { credit: 'bg-blue-100 text-blue-700', debit: 'bg-orange-100 text-orange-700', both: 'bg-purple-100 text-purple-700' }
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Narration Heads</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ heads.length }} head{{ heads.length !== 1 ? 's' : '' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                        <button
                            v-for="opt in [{ value: 'all', label: 'All' }, { value: 'credit', label: 'Credit' }, { value: 'debit', label: 'Debit' }, { value: 'both', label: 'Both' }]"
                            :key="opt.value"
                            @click="typeFilter = opt.value"
                            :class="[
                                'px-3 py-1.5 font-medium transition',
                                typeFilter === opt.value
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-500 hover:bg-gray-50'
                            ]"
                        >{{ opt.label }}</button>
                    </div>
                    <button
                        v-if="canCreate"
                        @click="openCreateHead"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Head
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-2">

                <div v-if="!filteredHeads.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-12 text-center text-sm text-gray-400">
                    {{ typeFilter === 'all' ? 'No narration heads yet.' : `No ${typeFilter} heads found.` }}
                </div>

                <div
                    v-for="head in filteredHeads"
                    :key="head.id"
                    class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <!-- Head row -->
                    <div class="flex items-center gap-3 px-5 py-4">
                        <!-- Expand toggle -->
                        <button @click="toggleExpand(head.id)" class="text-gray-400 hover:text-gray-600 shrink-0">
                            <svg :class="['h-4 w-4 transition-transform', expanded.has(head.id) ? 'rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-semibold text-gray-900">{{ head.name }}</span>
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', typeBadge[head.type]]">{{ typeLabel[head.type] }}</span>
                                <span v-if="!head.is_active" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                            </div>
                            <p v-if="head.description" class="text-xs text-gray-400 mt-0.5 truncate">{{ head.description }}</p>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 text-xs">
                            <span class="text-gray-400">{{ head.sub_heads?.length ?? head.subHeads?.length ?? 0 }} sub-heads</span>
                            <button v-if="canCreate" @click="openCreateSubHead(head)" class="text-indigo-600 hover:text-indigo-800 font-medium">+ Sub-head</button>
                            <button v-if="canEdit" @click="openEditHead(head)" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button v-if="canDelete" @click="destroyHead(head)" class="text-red-500 hover:text-red-700 font-medium">Delete</button>
                        </div>
                    </div>

                    <!-- Sub-heads (expanded) -->
                    <div v-if="expanded.has(head.id) && (head.sub_heads ?? head.subHeads ?? []).length" class="border-t border-gray-100">
                        <div
                            v-for="sub in (head.sub_heads ?? head.subHeads ?? [])"
                            :key="sub.id"
                            class="flex items-center gap-3 px-5 py-3 border-b border-gray-50 last:border-0 bg-gray-50/40 hover:bg-gray-50 transition"
                        >
                            <div class="w-4 shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-medium text-gray-800">{{ sub.name }}</span>
                                    <span v-if="sub.ledger_code" class="text-xs text-gray-400 font-mono">{{ sub.ledger_code }}</span>
                                    <span v-if="sub.requires_party" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700">Requires Party</span>
                                    <span v-if="!sub.is_active" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                                </div>
                                <p v-if="sub.ledger_name || sub.description" class="text-xs text-gray-400 mt-0.5">
                                    {{ [sub.ledger_name, sub.description].filter(Boolean).join(' · ') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 text-xs">
                                <button v-if="canEdit" @click="openEditSubHead(head, sub)" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                                <button v-if="canDelete" @click="destroySubHead(head, sub)" class="text-red-500 hover:text-red-700 font-medium">Delete</button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="expanded.has(head.id)" class="border-t border-gray-100 px-5 py-3 text-xs text-gray-400 bg-gray-50/40">
                        No sub-heads yet.
                    </div>
                </div>
            </div>
        </div>

        <!-- Head Modal -->
        <Teleport to="body">
            <div
                v-if="showHeadModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @click.self="showHeadModal = false"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ editingHead ? 'Edit Head' : 'Add Narration Head' }}</h2>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input v-model="headForm.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="headForm.errors.name" class="mt-1 text-xs text-red-500">{{ headForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                            <select v-model="headForm.type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="both">Both</option>
                                <option value="credit">Credit</option>
                                <option value="debit">Debit</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="headForm.description" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input v-model="headForm.sort_order" type="number" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="headForm.is_active" type="checkbox" id="head_is_active" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <label for="head_is_active" class="text-sm font-medium text-gray-700">Active</label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button @click="showHeadModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button @click="submitHead" :disabled="headForm.processing" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition">
                            {{ editingHead ? 'Save' : 'Add Head' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Sub-head Modal -->
        <Teleport to="body">
            <div
                v-if="showSubModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @click.self="showSubModal = false"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ editingSubHead ? 'Edit Sub-head' : 'Add Sub-head' }}</h2>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input v-model="subForm.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="subForm.errors.name" class="mt-1 text-xs text-red-500">{{ subForm.errors.name }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ledger Code</label>
                                <input v-model="subForm.ledger_code" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ledger Name</label>
                                <input v-model="subForm.ledger_name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="subForm.description" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input v-model="subForm.sort_order" type="number" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <input v-model="subForm.requires_party" type="checkbox" id="requires_party" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <label for="requires_party" class="text-sm font-medium text-gray-700">Requires Party</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="subForm.is_active" type="checkbox" id="sub_is_active" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <label for="sub_is_active" class="text-sm font-medium text-gray-700">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button @click="showSubModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button @click="submitSubHead" :disabled="subForm.processing" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition">
                            {{ editingSubHead ? 'Save' : 'Add Sub-head' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
