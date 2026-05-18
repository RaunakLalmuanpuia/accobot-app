<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant: Object,
    units:  Array,
})

const canManage = hasPermission('integrations.manage')

const search = ref('')

const filteredUnits = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return props.units
    return props.units.filter(u =>
        u.name.toLowerCase().includes(q) ||
        (u.symbol ?? '').toLowerCase().includes(q) ||
        (u.formal_name ?? '').toLowerCase().includes(q) ||
        (u.uqc ?? '').toLowerCase().includes(q)
    )
})

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending', cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',  cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',   cls: 'bg-gray-100 text-gray-400'   }
}

// ── CRUD ──────────────────────────────────────────────────────────────────────
const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    name:           '',
    formal_name:    '',
    decimal_places: 0,
    uqc:            '',
})

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(unit) {
    form.name           = unit.name
    form.formal_name    = unit.formal_name ?? ''
    form.decimal_places = unit.decimal_places ?? 0
    form.uqc            = unit.uqc ?? ''
    form.clearErrors()
    modal.value = unit
}

function closeModal() {
    modal.value = null
    form.reset()
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.units.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.units.update', { tenant: props.tenant.id, unit: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(unit) {
    const msg = unit.tally_id
        ? `Mark "${unit.name}" inactive and queue deletion in Tally?`
        : `Delete "${unit.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.units.destroy', { tenant: props.tenant.id, unit: unit.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Units of Measure</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ units.length }} unit{{ units.length !== 1 ? 's' : '' }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Unit
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

                <!-- Search -->
                <div class="flex items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search units…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                    <span class="text-sm text-gray-400">{{ filteredUnits.length }} result{{ filteredUnits.length !== 1 ? 's' : '' }}</span>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-2">Symbol</div>
                        <div class="col-span-3">Name</div>
                        <div class="col-span-2">Formal Name</div>
                        <div class="col-span-1 text-center">Decimals</div>
                        <div class="col-span-1 text-center">Status</div>
                        <div class="col-span-1 text-center">Tally</div>
                        <div class="col-span-1">Last Synced</div>
                        <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="unit in filteredUnits" :key="unit.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-2">
                            <span class="font-mono text-sm font-medium text-gray-900">{{ unit.symbol || '—' }}</span>
                        </div>
                        <div class="col-span-3">
                            <p class="text-sm font-medium text-gray-900">{{ unit.name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
            <span v-if="!unit.is_simple_unit" class="text-amber-600 font-medium">Compound · </span>
            <span v-if="unit.uqc">{{ unit.uqc }}</span>
        </p>
                        </div>
                        <div class="col-span-2 text-sm text-gray-500">{{ unit.formal_name || '—' }}</div>
                        <div class="col-span-1 text-center text-sm text-gray-500">{{ unit.decimal_places ?? 0 }}</div>
                        <div class="col-span-1 text-center">
                            <span :class="unit.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ unit.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="syncBadge(unit.sync_status).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ syncBadge(unit.sync_status).label }}
                            </span>
                        </div>
                        <div class="col-span-1 text-xs text-gray-400">{{ formatDate(unit.last_synced_at) }}</div>
                        <div class="col-span-1 text-right" v-if="canManage">
                            <button @click="openEdit(unit)"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                            <button @click="destroy(unit)"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                        </div>
                    </div>

                    <p v-if="!filteredUnits.length" class="text-center text-gray-400 py-12 text-sm">No units found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Slide-over -->
    <Teleport to="body">
        <div v-if="modal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Unit' : 'New Unit' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submit" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Unit Definition</div>

                    <div class="tally-row">
                        <span class="tally-label">Name / Symbol <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="form.name" type="text" placeholder="e.g. PCS" class="tally-field" />
                            <p class="mt-0.5 text-xs text-gray-400">Name and symbol are always the same in Tally.</p>
                            <p v-if="form.errors.name" class="mt-0.5 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">Formal Name</span>
                        <div class="tally-input">
                            <input v-model="form.formal_name" type="text" placeholder="e.g. Pieces" class="tally-field" />
                            <p v-if="form.errors.formal_name" class="mt-0.5 text-xs text-red-500">{{ form.errors.formal_name }}</p>
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">Decimal Places</span>
                        <div class="tally-input">
                            <input v-model.number="form.decimal_places" type="number" min="0" max="9" class="tally-field" />
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">UQC</span>
                        <div class="tally-input">
                            <input v-model="form.uqc" type="text" placeholder="e.g. PCS-PIECES" class="tally-field" />
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
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

<style scoped>
.tally-row   { @apply flex items-stretch border-b border-gray-100; }
.tally-label { @apply w-44 shrink-0 text-sm text-gray-600 bg-gray-50 px-4 py-2.5 border-r border-gray-100 flex items-center; }
.tally-input { @apply flex-1 px-3 py-2; }
.tally-field { @apply w-full text-sm border-0 outline-none focus:ring-1 focus:ring-violet-400 rounded bg-transparent; }
.tally-section-header { @apply bg-violet-50 text-violet-700 text-xs font-semibold uppercase tracking-wider px-4 py-1.5 border-b border-violet-100; }
</style>
