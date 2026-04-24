<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant: Object,
    groups: Array,
})

const canManage = hasPermission('integrations.manage')

// ── List ───────────────────────────────────────────────────────────────────────
const search = ref('')

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return props.groups
    return props.groups.filter(g =>
        g.name.toLowerCase().includes(q) ||
        (g.under_name ?? '').toLowerCase().includes(q) ||
        (g.nature_of_group ?? '').toLowerCase().includes(q)
    )
})

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString()
}

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending',  cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',   cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',   cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',    cls: 'bg-gray-100 text-gray-400'   }
}

// ── CRUD ───────────────────────────────────────────────────────────────────────
const modal   = ref(null) // null | 'create' | {record}
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    name:            '',
    under_name:      '',
    nature_of_group: '',
})

const groupNameOptions = computed(() =>
    props.groups.filter(g => g.is_active).map(g => g.name)
)

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(group) {
    form.name            = group.name
    form.under_name      = group.under_name ?? ''
    form.nature_of_group = group.nature_of_group ?? ''
    form.clearErrors()
    modal.value = group
}

function closeModal() {
    modal.value = null
    form.reset()
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.ledger-groups.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.ledger-groups.update', { tenant: props.tenant.id, ledgerGroup: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(group) {
    const msg = group.tally_id
        ? `Mark "${group.name}" inactive and queue deletion in Tally?`
        : `Delete "${group.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.ledger-groups.destroy', { tenant: props.tenant.id, ledgerGroup: group.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Ledger Groups</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ groups.length }} groups</p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Group
                    </button>
                    <Link :href="route('tally.ledgers.index', { tenant: tenant.id })"
                          class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                        View Ledgers →
                    </Link>
                    <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success"
                     class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.info"
                     class="rounded-lg bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-800">
                    {{ $page.props.flash.info }}
                </div>

                <div class="flex items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search groups…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-3">Name</div>
                        <div class="col-span-3">Under</div>
                        <div class="col-span-2">Nature</div>
                        <div class="col-span-1 text-center">Status</div>
                        <div class="col-span-1 text-center">Tally</div>
                        <div class="col-span-1">Last Synced</div>
                        <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="group in filtered" :key="group.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-3">
                            <p class="text-sm font-medium text-gray-900">{{ group.name }}</p>
                        </div>
                        <div class="col-span-3 text-sm text-gray-500 truncate">{{ group.under_name ?? '—' }}</div>
                        <div class="col-span-2 text-sm text-gray-500">{{ group.nature_of_group ?? '—' }}</div>
                        <div class="col-span-1 text-center">
                            <span :class="group.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ group.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="syncBadge(group.sync_status).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ syncBadge(group.sync_status).label }}
                            </span>
                        </div>
                        <div class="col-span-1 text-xs text-gray-400">{{ formatDate(group.last_synced_at) }}</div>
                        <div class="col-span-1 text-right" v-if="canManage">
                            <button @click="openEdit(group)"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                            <button @click="destroy(group)"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No ledger groups found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Slide-over -->
    <Teleport to="body">
        <div v-if="modal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeModal" />
            <div class="relative z-50 w-full max-w-md bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Ledger Group' : 'New Ledger Group' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.name" type="text" placeholder="e.g. Sundry Debtors"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Under (Parent Group)</label>
                        <input v-model="form.under_name" type="text"
                               list="group-under-options"
                               placeholder="e.g. Current Assets"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <datalist id="group-under-options">
                            <option v-for="n in groupNameOptions" :key="n" :value="n" />
                        </datalist>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nature of Group</label>
                        <select v-model="form.nature_of_group"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="">— Select —</option>
                            <option>Assets</option>
                            <option>Liabilities</option>
                            <option>Income</option>
                            <option>Expenses</option>
                        </select>
                    </div>

                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditing ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
