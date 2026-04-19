<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    tenant:     Object,
    connection: Object,
    base_url:   String,
})

const canManage   = hasPermission('integrations.manage')
const showToken   = ref(false)
const copySuccess = ref(null)

const form = useForm({
    company_id: props.connection?.company_id ?? '',
    is_active:  props.connection?.is_active ?? true,
})

function save() {
    form.post(route('tally.connection.save', { tenant: props.tenant.id }))
}

function testConnection() {
    router.get(route('tally.connection.test', { tenant: props.tenant.id }))
}

function regenerateToken() {
    if (!confirm('Regenerate token? The old token will stop working immediately.')) return
    router.post(route('tally.connection.regenerate-token', { tenant: props.tenant.id }))
}

function removeConnection() {
    if (!confirm('Remove Tally connection? This cannot be undone.')) return
    router.delete(route('tally.connection.destroy', { tenant: props.tenant.id }))
}

async function copyToClipboard(text, key) {
    await navigator.clipboard.writeText(text)
    copySuccess.value = key
    setTimeout(() => { copySuccess.value = null }, 2000)
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Tally Integration</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Connect Tally ERP via the cloud connector</p>
                </div>
                <a :href="route('tally.sync.index', { tenant: tenant.id })"
                   class="text-sm text-violet-600 hover:text-violet-800 font-medium">
                    View Sync Logs →
                </a>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Token card (shown after first save) -->
                <div v-if="connection" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                    <h2 class="text-base font-semibold text-gray-900">Connection Token</h2>

                    <div class="space-y-3">
                        <!-- Base URL -->
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                            <div>
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Base URL</p>
                                <p class="text-sm text-gray-800 font-mono mt-0.5">{{ base_url }}</p>
                            </div>
                            <button @click="copyToClipboard(base_url, 'base_url')"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium shrink-0 ml-4">
                                {{ copySuccess === 'base_url' ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>

                        <!-- Token -->
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Token</p>
                                <p class="text-sm text-gray-800 font-mono mt-0.5 truncate">
                                    {{ showToken ? connection.inbound_token : '•'.repeat(24) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 ml-4 shrink-0">
                                <button @click="showToken = !showToken"
                                        class="text-xs text-gray-500 hover:text-gray-700">
                                    {{ showToken ? 'Hide' : 'Show' }}
                                </button>
                                <button @click="copyToClipboard(connection.inbound_token, 'token')"
                                        class="text-xs text-violet-600 hover:text-violet-800 font-medium">
                                    {{ copySuccess === 'token' ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </div>

                        <!-- Last used -->
                        <p v-if="connection.inbound_token_last_used_at" class="text-xs text-gray-400 px-1">
                            Last used: {{ new Date(connection.inbound_token_last_used_at).toLocaleString() }}
                        </p>

                        <button v-if="canManage"
                                @click="regenerateToken"
                                class="text-xs text-red-500 hover:text-red-700 font-medium">
                            Regenerate Token
                        </button>
                    </div>
                </div>

                <!-- "Enter These in Tally" table -->
                <div v-if="connection" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Enter These in Tally Connector</h2>
                    <div class="divide-y divide-gray-100 border border-gray-200 rounded-xl overflow-hidden">
                        <div v-for="row in [
                            { label: 'Base URL',    value: base_url,                      key: 'tbl_url' },
                            { label: 'Token',       value: connection.inbound_token,       key: 'tbl_tok' },
                            { label: 'Company ID',  value: connection.company_id || '—',   key: 'tbl_cid' },
                        ]" :key="row.key"
                             class="flex items-center justify-between px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ row.label }}</p>
                                <p class="text-sm text-gray-800 font-mono truncate mt-0.5">{{ row.value }}</p>
                            </div>
                            <button v-if="row.value !== '—'"
                                    @click="copyToClipboard(row.value, row.key)"
                                    class="ml-4 shrink-0 text-xs text-violet-600 hover:text-violet-800 font-medium">
                                {{ copySuccess === row.key ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Settings form -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900">Settings</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Company ID <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.company_id"
                               type="text"
                               placeholder="Tally company UUID"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                               :disabled="!canManage" />
                        <p class="mt-1 text-xs text-gray-400">Found in Tally's company settings (GUID format).</p>
                        <p v-if="form.errors.company_id" class="mt-1 text-xs text-red-500">{{ form.errors.company_id }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <input v-model="form.is_active"
                               type="checkbox"
                               id="is_active"
                               class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                               :disabled="!canManage" />
                        <label for="is_active" class="text-sm text-gray-700">Connection active</label>
                    </div>

                    <div v-if="canManage" class="flex flex-wrap gap-3 pt-2">
                        <button @click="save"
                                :disabled="form.processing"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition disabled:opacity-50">
                            Save
                        </button>
                        <button v-if="connection"
                                @click="testConnection"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Test Connection
                        </button>
                        <button v-if="connection"
                                @click="removeConnection"
                                class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition">
                            Remove
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
