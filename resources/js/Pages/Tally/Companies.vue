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
const search = ref('')

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return props.items
    return props.items.filter(i =>
        (i.company_name ?? '').toLowerCase().includes(q) ||
        (i.country ?? '').toLowerCase().includes(q) ||
        (i.state ?? '').toLowerCase().includes(q) ||
        (i.tally_serial_no ?? '').toLowerCase().includes(q)
    )
})

function syncBadge(status) {
    if (status === 'pending')   return { label: 'Pending',  cls: 'bg-amber-100 text-amber-700' }
    if (status === 'confirmed') return { label: 'Synced',   cls: 'bg-green-100 text-green-700' }
    if (status === 'synced')    return { label: 'Synced',   cls: 'bg-green-100 text-green-700' }
    return                             { label: 'Local',    cls: 'bg-gray-100 text-gray-400'   }
}

// ── CRUD ───────────────────────────────────────────────────────────────────────
const modal     = ref(null)
const isEditing = computed(() => modal.value && modal.value !== 'create')

const form = useForm({
    company_name:    '',
    formal_name:     '',
    email:           '',
    phone_number:    '',
    fax_number:      '',
    website:         '',
    address:         '',
    address1:        '',
    address2:        '',
    address3:        '',
    state:           '',
    country:         '',
    pincode:         '',
    branch_name:     '',
    connect_name:    '',
    income_tax_number: '',
    ta_number:       '',
    gst_registration_number: '',
    gst_registration_type:   '',
    starting_from:   '',
    books_from:      '',
    tally_serial_no: '',
    licence_type:    '',
})

function openCreate() {
    form.reset()
    form.clearErrors()
    modal.value = 'create'
}

function openEdit(item) {
    form.company_name    = item.company_name ?? ''
    form.formal_name     = item.formal_name ?? ''
    form.email           = item.email ?? ''
    form.phone_number    = item.phone_number ?? ''
    form.fax_number      = item.fax_number ?? ''
    form.website         = item.website ?? ''
    form.address         = item.address ?? ''
    form.address1        = item.address1 ?? ''
    form.address2        = item.address2 ?? ''
    form.address3        = item.address3 ?? ''
    form.state           = item.state ?? ''
    form.country         = item.country ?? ''
    form.pincode         = item.pincode ?? ''
    form.branch_name     = item.branch_name ?? ''
    form.connect_name    = item.connect_name ?? ''
    form.income_tax_number = item.income_tax_number ?? ''
    form.ta_number       = item.ta_number ?? ''
    form.gst_registration_number = item.gst_registration_number ?? ''
    form.gst_registration_type   = item.gst_registration_type ?? ''
    form.starting_from   = item.starting_from ?? ''
    form.books_from      = item.books_from ?? ''
    form.tally_serial_no = item.tally_serial_no ?? ''
    form.licence_type    = item.licence_type ?? ''
    form.clearErrors()
    modal.value = item
}

function closeModal() {
    modal.value = null
    form.reset()
}

function submit() {
    if (!isEditing.value) {
        form.post(route('tally.companies.store', { tenant: props.tenant.id }), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.put(route('tally.companies.update', { tenant: props.tenant.id, company: modal.value.id }), {
            onSuccess: () => closeModal(),
        })
    }
}

function destroy(item) {
    if (!confirm(`Delete company "${item.company_name}"? This cannot be undone.`)) return
    router.delete(route('tally.companies.destroy', { tenant: props.tenant.id, company: item.id }))
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Companies</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ items.length }} company record{{ items.length !== 1 ? 's' : '' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canManage"
                            @click="openCreate"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 transition">
                        + New Company
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
                    <input v-model="search" type="text" placeholder="Search companies…"
                           class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                    <span class="text-sm text-gray-400">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-3">Company Name</div>
                        <div class="col-span-2">State / Country</div>
                        <div class="col-span-1">GST No.</div>
                        <div class="col-span-1">PAN / TAN</div>
                        <div class="col-span-1">Serial No.</div>
                        <div class="col-span-1">Licence</div>
                        <div class="col-span-2 text-center">Tally Sync</div>
                        <div class="col-span-1 text-right" v-if="canManage">Actions</div>
                    </div>

                    <div v-for="item in filtered" :key="item.id"
                         class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition">

                        <div class="col-span-3">
                            <p class="text-sm font-medium text-gray-900">{{ item.company_name ?? '—' }}</p>
                            <p v-if="item.formal_name && item.formal_name !== item.company_name"
                               class="text-xs text-gray-400 truncate">{{ item.formal_name }}</p>
                            <p v-if="item.email" class="text-xs text-gray-400 truncate">{{ item.email }}</p>
                        </div>

                        <div class="col-span-2 text-sm text-gray-600">
                            <span v-if="item.state || item.country">
                                {{ [item.state, item.country].filter(Boolean).join(', ') }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                            <p v-if="item.pincode" class="text-xs text-gray-400">{{ item.pincode }}</p>
                        </div>

                        <div class="col-span-1 text-xs text-gray-500 font-mono truncate">
                            {{ item.gst_registration_number ?? '—' }}
                        </div>

                        <div class="col-span-1 text-xs text-gray-500 space-y-0.5">
                            <p v-if="item.income_tax_number" class="font-mono">{{ item.income_tax_number }}</p>
                            <p v-if="item.ta_number" class="font-mono text-gray-400">{{ item.ta_number }}</p>
                            <span v-if="!item.income_tax_number && !item.ta_number" class="text-gray-300">—</span>
                        </div>

                        <div class="col-span-1 text-sm text-gray-600 font-mono truncate">{{ item.tally_serial_no ?? '—' }}</div>

                        <div class="col-span-1 text-sm text-gray-500">
                            <span v-if="item.licence_type"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                                {{ item.licence_type }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </div>

                        <div class="col-span-2 text-center">
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

                    <p v-if="!filtered.length" class="text-center text-gray-400 py-12 text-sm">No companies found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Slide-over -->
    <Teleport to="body">
        <div v-if="modal !== null" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeModal" />
            <div class="relative z-50 w-full max-w-xl bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Company' : 'New Company' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">

                    <!-- Identity -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
                            <input v-model="form.company_name" type="text" placeholder="e.g. Accobot Pvt Ltd"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.company_name" class="mt-1 text-xs text-red-500">{{ form.errors.company_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Formal Name</label>
                            <input v-model="form.formal_name" type="text" placeholder="e.g. Accobot Technologies Pvt Ltd"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="form.email" type="email" placeholder="e.g. info@company.in"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input v-model="form.phone_number" type="text" placeholder="e.g. 9876543210"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fax</label>
                            <input v-model="form.fax_number" type="text" placeholder="e.g. 01141234567"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                            <input v-model="form.website" type="url" placeholder="e.g. https://company.in"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address (primary)</label>
                        <input v-model="form.address" type="text" placeholder="Street / building"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 mb-2" />
                        <div class="grid grid-cols-3 gap-2">
                            <input v-model="form.address1" type="text" placeholder="Line 1"
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <input v-model="form.address2" type="text" placeholder="Line 2"
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <input v-model="form.address3" type="text" placeholder="Line 3"
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <input v-model="form.state" type="text" placeholder="e.g. Rajasthan"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <input v-model="form.country" type="text" placeholder="e.g. India"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pincode</label>
                            <input v-model="form.pincode" type="text" placeholder="e.g. 302001"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Branch Name</label>
                            <input v-model="form.branch_name" type="text" placeholder="e.g. Delhi Branch"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Connect Name</label>
                            <input v-model="form.connect_name" type="text" placeholder="e.g. Shopify Demo"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <!-- Tax Registration -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PAN / Income Tax No.</label>
                            <input v-model="form.income_tax_number" type="text" placeholder="e.g. AAICI2170A"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">TAN Number</label>
                            <input v-model="form.ta_number" type="text" placeholder="e.g. JPRB01648D"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">GST Registration No.</label>
                            <input v-model="form.gst_registration_number" type="text" placeholder="e.g. 27AAAAA0000A1Z5"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">GST Registration Type</label>
                            <select v-model="form.gst_registration_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option>Regular</option>
                                <option>Composition</option>
                                <option>Unregistered</option>
                                <option>Consumer</option>
                                <option>Overseas</option>
                                <option>SEZ</option>
                            </select>
                        </div>
                    </div>

                    <!-- Financial Period -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Starting From</label>
                            <input v-model="form.starting_from" type="text" placeholder="e.g. 1-Apr-24"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Books From</label>
                            <input v-model="form.books_from" type="text" placeholder="e.g. 1-Apr-24"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>

                    <!-- Tally -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tally Serial No.</label>
                            <input v-model="form.tally_serial_no" type="text" placeholder="e.g. 775580148"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Licence Type</label>
                            <select v-model="form.licence_type"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">— Select —</option>
                                <option>Gold</option>
                                <option>Silver</option>
                                <option>Auditor</option>
                            </select>
                        </div>
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
