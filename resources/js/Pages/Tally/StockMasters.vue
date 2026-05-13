<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:          Object,
    stockGroups:     Array,
    stockCategories: Array,
    godowns:         Array,
})

const canManage = hasPermission('integrations.manage')
const activeTab = ref('stockGroups')

const tabs = [
    { key: 'stockGroups',     label: 'Stock Groups' },
    { key: 'stockCategories', label: 'Stock Categories' },
    { key: 'godowns',         label: 'Godowns' },
]

// ── Stock Groups ───────────────────────────────────────────────────────────────
const groupSearch = ref('')

const filteredGroups = computed(() => {
    const q = groupSearch.value.toLowerCase()
    if (!q) return props.stockGroups
    return props.stockGroups.filter(g =>
        g.name.toLowerCase().includes(q) ||
        (g.parent ?? '').toLowerCase().includes(q)
    )
})

// ── Stock Categories ───────────────────────────────────────────────────────────
const catSearch = ref('')

const filteredCategories = computed(() => {
    const q = catSearch.value.toLowerCase()
    if (!q) return props.stockCategories
    return props.stockCategories.filter(c =>
        c.name.toLowerCase().includes(q) ||
        (c.parent ?? '').toLowerCase().includes(q)
    )
})

// ── Godowns ────────────────────────────────────────────────────────────────────
const godownSearch = ref('')

const filteredGodowns = computed(() => {
    const q = godownSearch.value.toLowerCase()
    if (!q) return props.godowns
    return props.godowns.filter(g =>
        g.name.toLowerCase().includes(q) ||
        (g.under ?? '').toLowerCase().includes(q)
    )
})

const godownModal     = ref(null)
const isEditingGodown = computed(() => godownModal.value && godownModal.value !== 'create')
const godownForm      = useForm({ name: '', under: '' })

const godownUnderOptions = computed(() =>
    props.godowns
        .filter(g => g.is_active && g.id !== godownModal.value?.id)
        .map(g => g.name)
)

function openCreateGodown() {
    godownForm.reset()
    godownForm.clearErrors()
    godownModal.value = 'create'
}

function openEditGodown(godown) {
    godownForm.name  = godown.name
    godownForm.under = godown.under ?? ''
    godownForm.clearErrors()
    godownModal.value = godown
}

function closeGodownModal() {
    godownModal.value = null
    godownForm.reset()
}

function submitGodown() {
    if (!isEditingGodown.value) {
        godownForm.post(route('tally.godowns.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeGodownModal(),
        })
    } else {
        godownForm.put(route('tally.godowns.update', { tenant: props.tenant.id, godown: godownModal.value.id }), {
            onSuccess: () => closeGodownModal(),
        })
    }
}

function destroyGodown(godown) {
    if (!confirm(`Delete "${godown.name}"?`)) return
    router.delete(route('tally.godowns.destroy', { tenant: props.tenant.id, godown: godown.id }))
}

// ── Helpers ────────────────────────────────────────────────────────────────────
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

// ── Stock Group CRUD ───────────────────────────────────────────────────────────
const groupModal     = ref(null)
const isEditingGroup = computed(() => groupModal.value && groupModal.value !== 'create')

const groupForm = useForm({ name: '', parent: '', aliases: [] })

const groupParentOptions = computed(() =>
    props.stockGroups.filter(g => g.is_active && g.id !== groupModal.value?.id).map(g => g.name)
)

function addGroupAlias()     { groupForm.aliases.push({ Alias: '' }) }
function removeGroupAlias(i) { groupForm.aliases.splice(i, 1) }

function openCreateGroup() {
    groupForm.reset()
    groupForm.clearErrors()
    groupModal.value = 'create'
}

function openEditGroup(group) {
    groupForm.name    = group.name
    groupForm.parent  = group.parent ?? ''
    groupForm.aliases = group.aliases ? JSON.parse(JSON.stringify(group.aliases)) : []
    groupForm.clearErrors()
    groupModal.value = group
}

function closeGroupModal() {
    groupModal.value = null
    groupForm.reset()
}

function submitGroup() {
    if (!isEditingGroup.value) {
        groupForm.post(route('tally.stock-groups.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeGroupModal(),
        })
    } else {
        groupForm.put(route('tally.stock-groups.update', { tenant: props.tenant.id, stockGroup: groupModal.value.id }), {
            onSuccess: () => closeGroupModal(),
        })
    }
}

function destroyGroup(group) {
    const msg = group.tally_id
        ? `Mark "${group.name}" inactive and queue deletion in Tally?`
        : `Delete "${group.name}"? It was never synced to Tally.`
    if (!confirm(msg)) return
    router.delete(route('tally.stock-groups.destroy', { tenant: props.tenant.id, stockGroup: group.id }))
}

// ── Stock Category CRUD ────────────────────────────────────────────────────────
const catModal     = ref(null)
const isEditingCat = computed(() => catModal.value && catModal.value !== 'create')

const catForm = useForm({ name: '', parent: '', aliases: [] })

const catParentOptions = computed(() =>
    props.stockCategories.filter(c => c.is_active && c.id !== catModal.value?.id).map(c => c.name)
)

function addCatAlias()     { catForm.aliases.push({ Alias: '' }) }
function removeCatAlias(i) { catForm.aliases.splice(i, 1) }

function openCreateCat() {
    catForm.reset()
    catForm.clearErrors()
    catModal.value = 'create'
}

function openEditCat(cat) {
    catForm.name    = cat.name
    catForm.parent  = cat.parent ?? ''
    catForm.aliases = cat.aliases ? JSON.parse(JSON.stringify(cat.aliases)) : []
    catForm.clearErrors()
    catModal.value = cat
}

function closeCatModal() {
    catModal.value = null
    catForm.reset()
}

function submitCat() {
    if (!isEditingCat.value) {
        catForm.post(route('tally.stock-categories.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeCatModal(),
        })
    } else {
        catForm.put(route('tally.stock-categories.update', { tenant: props.tenant.id, stockCategory: catModal.value.id }), {
            onSuccess: () => closeCatModal(),
        })
    }
}

function destroyCat(cat) {
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
                    <h1 class="text-xl font-semibold text-gray-900">Stock Masters</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ stockGroups.length }} groups · {{ stockCategories.length }} categories · {{ godowns.length }} godowns
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <button v-if="canManage && activeTab === 'stockGroups'"
                            @click="openCreateGroup"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Group
                    </button>
                    <button v-if="canManage && activeTab === 'stockCategories'"
                            @click="openCreateCat"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Category
                    </button>
                    <button v-if="canManage && activeTab === 'godowns'"
                            @click="openCreateGodown"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Godown
                    </button>
                    <Link :href="route('tally.stock-items.index', { tenant: tenant.id })"
                          class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                        View Stock Items →
                    </Link>
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

                <!-- Tabs -->
                <div class="flex gap-1 border-b border-gray-200">
                    <button v-for="tab in tabs" :key="tab.key"
                            @click="activeTab = tab.key"
                            :class="activeTab === tab.key
                                ? 'border-violet-600 text-violet-700 font-semibold'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="px-4 py-2.5 text-sm border-b-2 transition -mb-px">
                        {{ tab.label }}
                        <span class="ml-1.5 text-xs rounded-full px-1.5 py-0.5"
                              :class="activeTab === tab.key ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-500'">
                            {{ tab.key === 'stockGroups' ? stockGroups.length
                               : tab.key === 'stockCategories' ? stockCategories.length
                               : godowns.length }}
                        </span>
                    </button>
                </div>

                <!-- ── Stock Groups tab ── -->
                <template v-if="activeTab === 'stockGroups'">
                    <div class="flex items-center gap-3">
                        <input v-model="groupSearch" type="text" placeholder="Search groups…"
                               class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                        <span class="text-sm text-gray-400">{{ filteredGroups.length }} result{{ filteredGroups.length !== 1 ? 's' : '' }}</span>
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

                        <div v-for="group in filteredGroups" :key="group.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-4">
                                <p class="text-sm font-medium text-gray-900">{{ group.name }}</p>
                                <p v-if="aliasText(group.aliases)" class="text-xs text-gray-400 mt-0.5 truncate">
                                    {{ aliasText(group.aliases) }}
                                </p>
                            </div>
                            <div class="col-span-3 text-sm text-gray-500 truncate">{{ group.parent ?? '—' }}</div>
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
                            <div class="col-span-2 text-right" v-if="canManage">
                                <button @click="openEditGroup(group)"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                                <button @click="destroyGroup(group)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                            </div>
                        </div>

                        <p v-if="!filteredGroups.length" class="text-center text-gray-400 py-12 text-sm">No stock groups found.</p>
                    </div>
                </template>

                <!-- ── Stock Categories tab ── -->
                <template v-if="activeTab === 'stockCategories'">
                    <div class="flex items-center gap-3">
                        <input v-model="catSearch" type="text" placeholder="Search categories…"
                               class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                        <span class="text-sm text-gray-400">{{ filteredCategories.length }} result{{ filteredCategories.length !== 1 ? 's' : '' }}</span>
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

                        <div v-for="cat in filteredCategories" :key="cat.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-4">
                                <p class="text-sm font-medium text-gray-900">{{ cat.name }}</p>
                                <p v-if="aliasText(cat.aliases)" class="text-xs text-gray-400 mt-0.5 truncate">
                                    {{ aliasText(cat.aliases) }}
                                </p>
                            </div>
                            <div class="col-span-3 text-sm text-gray-500">{{ cat.parent ?? '—' }}</div>
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
                                <button @click="openEditCat(cat)"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                                <button @click="destroyCat(cat)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                            </div>
                        </div>

                        <p v-if="!filteredCategories.length" class="text-center text-gray-400 py-12 text-sm">No stock categories found.</p>
                    </div>
                </template>

                <!-- ── Godowns tab ── -->
                <template v-if="activeTab === 'godowns'">
                    <div class="flex items-center gap-3">
                        <input v-model="godownSearch" type="text" placeholder="Search godowns…"
                               class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                        <span class="text-sm text-gray-400">{{ filteredGodowns.length }} result{{ filteredGodowns.length !== 1 ? 's' : '' }}</span>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <div class="col-span-4">Name</div>
                            <div class="col-span-3">Under</div>
                            <div class="col-span-2">GUID</div>
                            <div class="col-span-1 text-center">Status</div>
                            <div class="col-span-1">Last Synced</div>
                            <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                        </div>

                        <div v-for="godown in filteredGodowns" :key="godown.id"
                             class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">
                            <div class="col-span-4 text-sm font-medium text-gray-900">{{ godown.name }}</div>
                            <div class="col-span-3 text-sm text-gray-500">{{ godown.under ?? '—' }}</div>
                            <div class="col-span-2 text-xs text-gray-400 font-mono truncate">{{ godown.guid ?? '—' }}</div>
                            <div class="col-span-1 text-center">
                                <span :class="godown.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ godown.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-span-1 text-xs text-gray-400">{{ formatDate(godown.last_synced_at) }}</div>
                            <div class="col-span-1 text-right" v-if="canManage">
                                <button @click="openEditGodown(godown)"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                                <button @click="destroyGodown(godown)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium ml-2">Del</button>
                            </div>
                        </div>

                        <p v-if="!filteredGodowns.length" class="text-center text-gray-400 py-12 text-sm">No godowns found.</p>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Stock Group slide-over -->
    <Teleport to="body">
        <div v-if="groupModal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeGroupModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditingGroup ? 'Edit Stock Group' : 'New Stock Group' }}
                    </h2>
                    <button @click="closeGroupModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submitGroup" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="groupForm.name" type="text" placeholder="e.g. Electronics" class="tally-field" />
                            <p v-if="groupForm.errors.name" class="mt-0.5 text-xs text-red-500">{{ groupForm.errors.name }}</p>
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">Parent Group</span>
                        <div class="tally-input">
                            <select v-model="groupForm.parent" class="tally-field">
                                <option value="">— None —</option>
                                <option v-for="n in groupParentOptions" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Aliases</span>
                        <div class="tally-input">
                            <div v-for="(al, i) in groupForm.aliases" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="al.Alias" type="text" placeholder="Alias name" class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="removeGroupAlias(i)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="addGroupAlias"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Alias</button>
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
                        <button type="submit" :disabled="groupForm.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditingGroup ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeGroupModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Godown slide-over -->
    <Teleport to="body">
        <div v-if="godownModal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeGodownModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditingGodown ? 'Edit Godown' : 'New Godown' }}
                    </h2>
                    <button @click="closeGodownModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submitGodown" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="godownForm.name" type="text" placeholder="e.g. Main Warehouse" class="tally-field" />
                            <p v-if="godownForm.errors.name" class="mt-0.5 text-xs text-red-500">{{ godownForm.errors.name }}</p>
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">Under</span>
                        <div class="tally-input">
                            <select v-model="godownForm.under" class="tally-field">
                                <option value="">— Select —</option>
                                <option value="Primary">Primary</option>
                                <option v-for="n in godownUnderOptions" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
                        <button type="submit" :disabled="godownForm.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditingGodown ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeGodownModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Stock Category slide-over -->
    <Teleport to="body">
        <div v-if="catModal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeCatModal" />
            <div class="relative z-50 w-full max-w-lg bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditingCat ? 'Edit Stock Category' : 'New Stock Category' }}
                    </h2>
                    <button @click="closeCatModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="submitCat" class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <div class="tally-section-header">Basic Information</div>

                    <div class="tally-row">
                        <span class="tally-label">Name <span class="text-red-500">*</span></span>
                        <div class="tally-input">
                            <input v-model="catForm.name" type="text" placeholder="e.g. Accessories" class="tally-field" />
                            <p v-if="catForm.errors.name" class="mt-0.5 text-xs text-red-500">{{ catForm.errors.name }}</p>
                        </div>
                    </div>

                    <div class="tally-row">
                        <span class="tally-label">Parent Category</span>
                        <div class="tally-input">
                            <select v-model="catForm.parent" class="tally-field">
                                <option value="">— None —</option>
                                <option v-for="n in catParentOptions" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="tally-row items-start">
                        <span class="tally-label pt-2.5">Aliases</span>
                        <div class="tally-input">
                            <div v-for="(al, i) in catForm.aliases" :key="i" class="flex gap-2 mb-1.5">
                                <input v-model="al.Alias" type="text" placeholder="Alias name" class="tally-field flex-1 border border-gray-200 rounded px-2 py-1" />
                                <button type="button" @click="removeCatAlias(i)" class="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                            </div>
                            <button type="button" @click="addCatAlias"
                                    class="mt-1 text-xs text-violet-600 hover:text-violet-800 font-medium">+ Add Alias</button>
                        </div>
                    </div>

                    <div class="flex gap-3 px-4 py-4">
                        <button type="submit" :disabled="catForm.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            {{ isEditingCat ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="closeCatModal"
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
