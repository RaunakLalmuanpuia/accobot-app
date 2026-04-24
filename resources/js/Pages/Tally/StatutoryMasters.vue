<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant: Object,
    items:  Array,
})

const canManage = hasPermission('integrations.manage')

// ── List ───────────────────────────────────────────────────────────────────────
const search     = ref('')
const typeFilter = ref('all')

const types = computed(() => {
    const set = new Set(props.items.map(i => i.statutory_type).filter(Boolean))
    return ['all', ...Array.from(set).sort()]
})

const filtered = computed(() => {
    let list = props.items
    if (typeFilter.value !== 'all') {
        list = list.filter(i => i.statutory_type === typeFilter.value)
    }
    const q = search.value.toLowerCase()
    if (q) {
        list = list.filter(i =>
            i.name.toLowerCase().includes(q) ||
            (i.registration_number ?? '').toLowerCase().includes(q) ||
            (i.registration_type ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

const typeColors = {
    GST: 'bg-blue-100 text-blue-700',
    TDS: 'bg-amber-100 text-amber-700',
    TCS: 'bg-orange-100 text-orange-700',
    PF:  'bg-green-100 text-green-700',
    ESI: 'bg-teal-100 text-teal-700',
    PT:  'bg-purple-100 text-purple-700',
}

function typeColor(type) {
    return typeColors[type] ?? 'bg-gray-100 text-gray-600'
}

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending', cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',   cls: 'bg-gray-100 text-gray-400'   }
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

// ── CRUD ───────────────────────────────────────────────────────────────────────
const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    name:                '',
    statutory_type:      '',
    registration_number: '',
    state_code:          '',
    registration_type:   '',
    pan:                 '',
    tan:                 '',
    applicable_from:     '',
})

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(item) {
    form.name                = item.name
    form.statutory_type      = item.statutory_type ?? ''
    form.registration_number = item.registration_number ?? ''
    form.state_code          = item.state_code ?? ''
    form.registration_type   = item.registration_type ?? ''
    form.pan                 = item.pan ?? ''
    form.tan                 = item.tan ?? ''
    form.applicable_from     = item.applicable_from ?? ''
    form.clearErrors()
    modal.value = item
}

function closeModal() {
    modal.value = null
    form.reset()
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.statutory-masters.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.statutory-masters.update', { tenant: props.tenant.id, statutoryMaster: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(item) {
    const msg = item.tally_id
        ? `Mark "${item.name}" inactive and queue deletion in Tally?`
        : `Delete "${item.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.statutory-masters.destroy', { tenant: props.tenant.id, statutoryMaster: item.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Statutory Masters</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ items.length }} statutory records</p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Record
                    </button>
                    <Link :href="route('tally.sync.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success"
                     class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.info"
                     class="rounded-lg bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-800">
                    {{ $page.props.flash.info }}
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search statutory masters…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />

                    <select v-model="typeFilter"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="all">All Types</option>
                        <option v-for="t in types.slice(1)" :key="t" :value="t">{{ t }}</option>
                    </select>

                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-1 text-center">Type</div>
                        <div class="col-span-2">Name</div>
                        <div class="col-span-2">Registration No.</div>
                        <div class="col-span-2">Registration Type</div>
                        <div class="col-span-1 text-center">State</div>
                        <div class="col-span-1 text-center">From</div>
                        <div class="col-span-1 text-center">Status</div>
                        <div class="col-span-1 text-center">Tally</div>
                        <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="item in filtered" :key="item.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-1 text-center">
                            <span :class="typeColor(item.statutory_type)"
                                  class="text-xs px-2 py-0.5 rounded-full font-semibold">
                                {{ item.statutory_type ?? '—' }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm font-medium text-gray-900">{{ item.name }}</p>
                            <p v-if="item.pan || item.tan" class="text-xs text-gray-400 mt-0.5 font-mono">
                                {{ item.pan ?? item.tan }}
                            </p>
                        </div>
                        <div class="col-span-2 text-sm text-gray-600 font-mono">{{ item.registration_number ?? '—' }}</div>
                        <div class="col-span-2 text-sm text-gray-500">{{ item.registration_type ?? '—' }}</div>
                        <div class="col-span-1 text-center text-sm text-gray-500">{{ item.state_code ?? '—' }}</div>
                        <div class="col-span-1 text-center text-xs text-gray-500">{{ formatDate(item.applicable_from) }}</div>
                        <div class="col-span-1 text-center">
                            <span :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ item.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="syncBadge(item.sync_status).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ syncBadge(item.sync_status).label }}
                            </span>
                        </div>
                        <div class="col-span-1 text-right" v-if="canManage">
                            <button @click="openEdit(item)"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                            <button @click="destroy(item)"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No statutory masters found.</p>
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
                        {{ isEditing ? 'Edit Statutory Master' : 'New Statutory Master' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="e.g. GSTIN Registration"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Statutory Type</label>
                        <select v-model="form.statutory_type"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="">— Select —</option>
                            <option>GST</option>
                            <option>TDS</option>
                            <option>TCS</option>
                            <option>PF</option>
                            <option>ESI</option>
                            <option>PT</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Registration Number</label>
                        <input v-model="form.registration_number" type="text" placeholder="e.g. 22AAAAA0000A1Z5"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State Code</label>
                            <input v-model="form.state_code" type="text" placeholder="e.g. MH"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Registration Type</label>
                            <select v-model="form.registration_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option>Regular</option>
                                <option>Composition</option>
                                <option>Unregistered</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PAN</label>
                            <input v-model="form.pan" type="text" placeholder="AAAAA0000A"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">TAN</label>
                            <input v-model="form.tan" type="text" placeholder="AAAA00000A"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Applicable From</label>
                        <input v-model="form.applicable_from" type="date"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
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
