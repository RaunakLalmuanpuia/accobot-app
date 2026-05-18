<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:          Object,
    stockCategories: Array,
})

const canManage = hasPermission('integrations.manage')

const search = ref('')

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return props.stockCategories
    return props.stockCategories.filter(c =>
        c.name.toLowerCase().includes(q) ||
        (c.parent_name ?? '').toLowerCase().includes(q)
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

function aliasText(aliases) {
    if (!aliases || !aliases.length) return null
    return aliases.map(a => a.Alias).filter(Boolean).join(', ')
}

// ── CRUD ──────────────────────────────────────────────────────────────────────
const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({ name: '', parent_name: '', aliases: [] })

const parentOptions = computed(() =>
    props.stockCategories.filter(c => c.is_active && c.id !== modal.value?.id).map(c => c.name)
)

function addAlias()     { form.aliases.push({ Alias: '' }) }
function removeAlias(i) { form.aliases.splice(i, 1) }

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(cat) {
    form.name        = cat.name
    form.parent_name = cat.parent_name ?? ''
    form.aliases     = cat.aliases ? JSON.parse(JSON.stringify(cat.aliases)) : []
    form.clearErrors()
    modal.value = cat
}

function closeModal() {
    modal.value = null
    form.reset()
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.stock-categories.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.stock-categories.update', { tenant: props.tenant.id, stockCategory: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(cat) {
    const msg = cat.tally_id
        ? `Mark "${cat.name}" inactive and queue deletion in Tally?`
        : `Delete "${cat.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.stock-categories.destroy', { tenant: props.tenant.id, stockCategory: cat.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Stock Categories</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ stockCategories.length }} categor{{ stockCategories.length !== 1 ? 'ies' : 'y' }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Category
                    </button>
                    <Link :href="route('tally.stock-masters.index', { tenant: tenant.id })"
                          class="text-sm text-gray-500 hover:text-gray-700">
                        ← Stock Masters
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

                <div v-if="$page.props.flash?.success"
                     class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.info"
                     class="rounded-lg bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-800">
                    {{ $page.props.flash.info }}
                </div>

                <div class="flex items-center gap-3">
                    <input v-model="search" type="text" placeholder="Search categories…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-4">Name</div>
                        <div class="col-span-3">Parent</div>
                        <div class="col-span-1 text-center">Status</div>
                        <div class="col-span-1 text-center">Tally</div>
                        <div class="col-span-1">Last Synced</div>
                        <div class="col-span-2 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="cat in filtered" :key="cat.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                        <div class="col-span-4">
                            <p class="text-sm font-medium text-gray-900">{{ cat.name }}</p>
                            <p v-if="aliasText(cat.aliases)" class="text-xs text-gray-400 mt-0.5 truncate">
                                {{ aliasText(cat.aliases) }}
                            </p>
                        </div>
                        <div class="col-span-3 text-sm text-gray-500">{{ cat.parent_name ?? '—' }}</div>
                        <div class="col-span-1 text-center">
                            <span :class="cat.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ cat.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-span-1 text-center">
                            <span :class="syncBadge(cat.sync_status).cls"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ syncBadge(cat.sync_status).label }}
                            </span>
                        </div>
                        <div class="col-span-1 text-xs text-gray-400">{{ formatDate(cat.last_synced_at) }}</div>
                        <div class="col-span-2 text-right" v-if="canManage">
                            <button @click="openEdit(cat)"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                            <button @click="destroy(cat)"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                        </div>
                    </div>

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No stock categories found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <Teleport to="body">
        <div v-if="modal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Stock Category' : 'New Stock Category' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submit" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="form.name" type="text" placeholder="e.g. Accessories" class="tally-field" />
                            <p v-if="form.errors.name" class="mt-0.5 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">Parent Category</span>
                        <div class="tally-input">
                            <select v-model="form.parent_name" class="tally-field">
                                <option value="">Primary</option>
                                <option v-for="n in parentOptions" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Aliases</span>
                        <div class="tally-input">
                            <div v-for="(al, i) in form.aliases" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="al.Alias" type="text" placeholder="Alias name" class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="removeAlias(i)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="addAlias"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Alias</button>
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
