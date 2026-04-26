<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    events:      Object,
    filters:     Object,
    eventTypes:  Array,
    teamMembers: Array,
})

const page   = usePage()
const tenant = computed(() => page.props.auth.current_tenant_id)

// ── Filters ───────────────────────────────────────────────────────────────
const search       = ref(props.filters.search ?? '')
const eventType    = ref(props.filters.event_type ?? '')
const actorUserId  = ref(props.filters.actor_user_id ?? '')
const dateFrom     = ref(props.filters.date_from ?? '')
const dateTo       = ref(props.filters.date_to ?? '')

function applyFilters() {
    router.get(
        route('settings.audit', { tenant: tenant.value }),
        {
            search:        search.value || undefined,
            event_type:    eventType.value || undefined,
            actor_user_id: actorUserId.value || undefined,
            date_from:     dateFrom.value || undefined,
            date_to:       dateTo.value || undefined,
        },
        { preserveScroll: true, replace: true },
    )
}

function clearFilters() {
    search.value      = ''
    eventType.value   = ''
    actorUserId.value = ''
    dateFrom.value    = ''
    dateTo.value      = ''
    applyFilters()
}

function goToPage(url) {
    if (url) router.visit(url, { preserveScroll: true })
}

// ── Event badge colours (grouped by category prefix) ─────────────────────
function badgeClass(eventType) {
    if (!eventType) return 'bg-gray-100 text-gray-600'
    const prefix = eventType.split('.')[0]
    const map = {
        auth:        'bg-blue-100 text-blue-700',
        client:      'bg-teal-100 text-teal-700',
        vendor:      'bg-teal-100 text-teal-700',
        product:     'bg-emerald-100 text-emerald-700',
        invoice:     'bg-violet-100 text-violet-700',
        narration:   'bg-amber-100 text-amber-700',
        narration_head: 'bg-amber-100 text-amber-700',
        narration_sub_head: 'bg-amber-100 text-amber-700',
        banking:     'bg-sky-100 text-sky-700',
        chat:        'bg-pink-100 text-pink-700',
        tally:       'bg-slate-100 text-slate-700',
        role:        'bg-red-100 text-red-700',
        profile:     'bg-gray-100 text-gray-600',
    }
    return map[prefix] ?? 'bg-gray-100 text-gray-600'
}

// ── Actor display ─────────────────────────────────────────────────────────
function actorLabel(event) {
    if (event.actor_type === 'integration') return 'Tally Connector'
    if (event.actor_type === 'system')      return event.actor_name ? `${event.actor_name} (via AI)` : 'AI Agent'
    return event.actor_name ?? '—'
}

function actorBadge(event) {
    if (event.actor_type === 'integration') return 'bg-gray-100 text-gray-500 text-xs px-1.5 py-0.5 rounded font-medium'
    if (event.actor_type === 'system')      return 'bg-violet-100 text-violet-700 text-xs px-1.5 py-0.5 rounded font-medium'
    return null
}

// ── Metadata display ──────────────────────────────────────────────────────
function metadataSummary(meta) {
    if (!meta || typeof meta !== 'object') return ''
    return Object.entries(meta)
        .filter(([k]) => k !== 'via')
        .map(([k, v]) => {
            const val = Array.isArray(v) ? v.join(', ') : String(v)
            return `${k}: ${val}`
        })
        .join(' · ')
}

const expandedId = ref(null)
function toggleMeta(id) {
    expandedId.value = expandedId.value === id ? null : id
}

// ── Format date ───────────────────────────────────────────────────────────
function formatDate(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<template>
    <Head title="Audit Log" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
                <p class="text-sm text-gray-500 mt-1">Immutable record of all actions taken in this workspace.</p>
            </div>

            <!-- Filter bar -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 mb-6 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search event or metadata..."
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:ring-violet-500"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="eventType"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:ring-violet-500"
                    >
                        <option value="">All event types</option>
                        <option v-for="et in eventTypes" :key="et" :value="et">{{ et }}</option>
                    </select>
                    <select
                        v-model="actorUserId"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:ring-violet-500"
                    >
                        <option value="">All users</option>
                        <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                    <input
                        v-model="dateTo"
                        type="date"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </div>
                <div class="flex gap-2 mt-3">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 text-sm font-medium bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition"
                    >Apply</button>
                    <button
                        @click="clearFilters"
                        class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
                    >Clear</button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div v-if="events.data.length === 0" class="py-16 text-center text-gray-400 text-sm">
                    No audit events found.
                </div>

                <table v-else class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Time</th>
                            <th class="px-4 py-3 text-left">Event</th>
                            <th class="px-4 py-3 text-left">Actor</th>
                            <th class="px-4 py-3 text-left">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template v-for="event in events.data" :key="event.id">
                            <tr
                                class="hover:bg-gray-50 cursor-pointer transition"
                                @click="toggleMeta(event.id)"
                            >
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500 font-mono text-xs">
                                    {{ formatDate(event.occurred_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="badgeClass(event.event_type)"
                                    >{{ event.event_type }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span v-if="actorBadge(event)" :class="actorBadge(event)">
                                        {{ actorLabel(event) }}
                                    </span>
                                    <span v-else class="text-gray-700">{{ actorLabel(event) }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 max-w-xs truncate text-xs">
                                    {{ metadataSummary(event.metadata) }}
                                </td>
                            </tr>
                            <!-- Expanded metadata row -->
                            <tr v-if="expandedId === event.id" class="bg-violet-50">
                                <td colspan="4" class="px-6 py-3">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                        <div
                                            v-for="(val, key) in event.metadata"
                                            :key="key"
                                            class="text-xs"
                                        >
                                            <span class="font-medium text-gray-500">{{ key }}:</span>
                                            <span class="ml-1 text-gray-700 break-all">
                                                {{ Array.isArray(val) ? val.join(', ') : val }}
                                            </span>
                                        </div>
                                        <div class="text-xs col-span-full text-gray-400">
                                            IP: {{ event.ip ?? '—' }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="events.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <span class="text-sm text-gray-500">
                        Showing {{ events.from }}–{{ events.to }} of {{ events.total }} events
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            @click="goToPage(events.prev_page_url)"
                            :disabled="!events.prev_page_url"
                            class="px-3 py-1.5 text-sm rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >Previous</button>
                        <span class="text-sm text-gray-500 font-medium">{{ events.current_page }} / {{ events.last_page }}</span>
                        <button
                            @click="goToPage(events.next_page_url)"
                            :disabled="!events.next_page_url"
                            class="px-3 py-1.5 text-sm rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >Next</button>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
