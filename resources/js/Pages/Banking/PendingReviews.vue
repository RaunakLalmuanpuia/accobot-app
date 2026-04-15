<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { hasPermission } from '@/utils/permissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PendingTransactionCard from '@/Components/Banking/PendingTransactionCard.vue';
import {
    Upload, MessageSquare, Mail, RefreshCw, ChevronLeft, ChevronRight,
    AlertCircle, CheckCircle, Clock,
} from 'lucide-vue-next';

const props = defineProps({
    transactions: { type: Object, required: true }, // paginated resource
    heads:        { type: Array,  default: () => [] },
});

const page = usePage();
const currentTenantId = () => page.props.auth.current_tenant_id;

// ── Ingestion panel state ──────────────────────────────────────────────────
const activeTab = ref('sms'); // 'sms' | 'email' | 'file'

const smsForm = useForm({
    raw_sms:          '',
    bank_account_name: '',
});

const emailForm = useForm({
    email_body:        '',
    email_subject:     '',
    bank_account_name: '',
});

const fileForm = useForm({
    statement:         null,
    bank_account_name: '',
});

const fileInput = ref(null);
const showIngestion = ref(false);

const canReview = hasPermission('transactions.review');
const canEdit   = hasPermission('transactions.edit');
const canImport = hasPermission('transactions.import');

// Flash messages shared by the Inertia middleware
const flash = computed(() => page.props.flash ?? {});

// ── Submit helpers ─────────────────────────────────────────────────────────

const submitSms = () => {
    smsForm.post(route('banking.transactions.sms', { tenant: currentTenantId() }), {
        preserveScroll: true,
        onSuccess: () => { smsForm.reset(); },
    });
};

const submitEmail = () => {
    emailForm.post(route('banking.transactions.email', { tenant: currentTenantId() }), {
        preserveScroll: true,
        onSuccess: () => { emailForm.reset(); },
    });
};

const submitFile = () => {
    fileForm.post(route('banking.transactions.statement', { tenant: currentTenantId() }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            fileForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const handleFileChange = (e) => {
    fileForm.statement = e.target.files[0] ?? null;
};

// ── Stats from paginated data ─────────────────────────────────────────────
const pendingCount  = computed(() => props.transactions.data.filter(t => t.review_status === 'pending').length);
const reviewedCount = computed(() => props.transactions.data.filter(t => t.review_status !== 'pending').length);
const totalItems    = computed(() => props.transactions.total ?? 0);

// ── Pagination ────────────────────────────────────────────────────────────
const goToPage = (url) => {
    if (url) router.visit(url, { preserveScroll: true });
};
</script>

<template>
    <Head title="Bank Transactions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Bank Transactions</h2>
                <button
                    v-if="canImport"
                    @click="showIngestion = !showIngestion"
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition-colors"
                >
                    <Upload :size="16" />
                    Import
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ── Flash banner ── -->
                <div v-if="flash.success" class="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 flex items-start gap-2 text-sm text-emerald-700">
                    <CheckCircle :size="16" class="flex-shrink-0 mt-0.5 text-emerald-500" />
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="rounded-xl bg-red-50 border border-red-100 px-4 py-3 flex items-start gap-2 text-sm text-red-700">
                    <AlertCircle :size="16" class="flex-shrink-0 mt-0.5 text-red-500" />
                    {{ flash.error }}
                </div>

                <!-- ── Ingestion Panel ── -->
                <div v-if="showIngestion && canImport" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 pt-5 pb-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Add Transactions</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Paste SMS alerts, email content, or upload a bank statement file.</p>
                    </div>

                    <!-- Tabs -->
                    <div class="flex border-b border-gray-100">
                        <button
                            v-for="tab in [{ id: 'sms', icon: MessageSquare, label: 'SMS Alert' }, { id: 'email', icon: Mail, label: 'Email Alert' }, { id: 'file', icon: Upload, label: 'Statement File' }]"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'flex items-center gap-2 px-5 py-3 text-sm font-medium transition-colors',
                                activeTab === tab.id
                                    ? 'border-b-2 border-violet-600 text-violet-600'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            <component :is="tab.icon" :size="15" />
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="p-5">
                        <!-- SMS Tab -->
                        <form v-if="activeTab === 'sms'" @submit.prevent="submitSms" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">SMS Text *</label>
                                <textarea
                                    v-model="smsForm.raw_sms"
                                    rows="4"
                                    placeholder="Paste your bank SMS alert here…&#10;e.g. INR 5,000.00 debited from A/c XX1234 on 14-Apr-2026. UPI Ref 123456789. Avl Bal INR 22,500.00"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500"
                                ></textarea>
                                <p v-if="smsForm.errors.raw_sms" class="mt-1 text-xs text-red-500">{{ smsForm.errors.raw_sms }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bank Account Label (Optional)</label>
                                <input
                                    v-model="smsForm.bank_account_name"
                                    type="text"
                                    placeholder="e.g. HDFC Current Account"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                                />
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="smsForm.processing || !smsForm.raw_sms.trim()"
                                    class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50 transition-colors"
                                >
                                    <RefreshCw v-if="smsForm.processing" :size="15" class="animate-spin" />
                                    {{ smsForm.processing ? 'Processing…' : 'Parse & Import' }}
                                </button>
                            </div>
                        </form>

                        <!-- Email Tab -->
                        <form v-else-if="activeTab === 'email'" @submit.prevent="submitEmail" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Email Subject (Optional)</label>
                                <input
                                    v-model="emailForm.email_subject"
                                    type="text"
                                    placeholder="Transaction Alert — HDFC Bank"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Email Body *</label>
                                <textarea
                                    v-model="emailForm.email_body"
                                    rows="5"
                                    placeholder="Paste the email body here…"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-violet-500"
                                ></textarea>
                                <p v-if="emailForm.errors.email_body" class="mt-1 text-xs text-red-500">{{ emailForm.errors.email_body }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bank Account Label (Optional)</label>
                                <input
                                    v-model="emailForm.bank_account_name"
                                    type="text"
                                    placeholder="e.g. ICICI Business Account"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                                />
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="emailForm.processing || !emailForm.email_body.trim()"
                                    class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50 transition-colors"
                                >
                                    <RefreshCw v-if="emailForm.processing" :size="15" class="animate-spin" />
                                    {{ emailForm.processing ? 'Processing…' : 'Parse & Import' }}
                                </button>
                            </div>
                        </form>

                        <!-- File Upload Tab -->
                        <form v-else @submit.prevent="submitFile" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Statement File * <span class="text-gray-400 font-normal">(PDF, CSV, XLSX, XLS, JPG, PNG · max 20 MB)</span></label>
                                <div
                                    class="relative rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-violet-300 hover:bg-violet-50/30 transition-colors cursor-pointer"
                                    @click="fileInput?.click()"
                                >
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept=".pdf,.csv,.xlsx,.xls,.jpg,.jpeg,.png"
                                        @change="handleFileChange"
                                        class="sr-only"
                                    />
                                    <div class="py-8 flex flex-col items-center gap-2">
                                        <Upload :size="28" class="text-gray-300" />
                                        <p class="text-sm font-medium text-gray-500">
                                            {{ fileForm.statement ? fileForm.statement.name : 'Click or drag to upload' }}
                                        </p>
                                        <p v-if="fileForm.statement" class="text-xs text-gray-400">
                                            {{ (fileForm.statement.size / 1024).toFixed(0) }} KB
                                        </p>
                                    </div>
                                </div>
                                <p v-if="fileForm.errors.statement" class="mt-1 text-xs text-red-500">{{ fileForm.errors.statement }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bank Account Label (Optional)</label>
                                <input
                                    v-model="fileForm.bank_account_name"
                                    type="text"
                                    placeholder="e.g. SBI Savings Account"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                                />
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="fileForm.processing || !fileForm.statement"
                                    class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50 transition-colors"
                                >
                                    <RefreshCw v-if="fileForm.processing" :size="15" class="animate-spin" />
                                    {{ fileForm.processing ? 'Uploading…' : 'Upload & Parse' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── Stats Bar ── -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center">
                            <Clock :size="18" class="text-gray-500" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">This Page</p>
                            <p class="text-xl font-bold text-gray-800">{{ transactions.data.length }}</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-violet-100 px-5 py-4 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-violet-50 flex items-center justify-center">
                            <AlertCircle :size="18" class="text-violet-500" />
                        </div>
                        <div>
                            <p class="text-xs text-violet-400 font-medium uppercase tracking-wide">Pending</p>
                            <p class="text-xl font-bold text-violet-700">{{ pendingCount }}</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 px-5 py-4 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center">
                            <CheckCircle :size="18" class="text-emerald-500" />
                        </div>
                        <div>
                            <p class="text-xs text-emerald-400 font-medium uppercase tracking-wide">Reviewed</p>
                            <p class="text-xl font-bold text-emerald-700">{{ reviewedCount }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Transaction List ── -->
                <div v-if="transactions.data.length" class="space-y-4">
                    <PendingTransactionCard
                        v-for="tx in transactions.data"
                        :key="tx.id"
                        :transaction="tx"
                        :heads="heads"
                    />
                </div>

                <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100 py-16 flex flex-col items-center gap-3 text-center">
                    <div class="w-14 h-14 rounded-full bg-gray-50 flex items-center justify-center">
                        <CheckCircle :size="28" class="text-gray-300" />
                    </div>
                    <p class="text-base font-semibold text-gray-700">No transactions yet</p>
                    <p class="text-sm text-gray-400 max-w-sm">
                        Import your first bank statement or paste an SMS alert above to get started.
                    </p>
                    <button
                        v-if="canImport"
                        @click="showIngestion = true"
                        class="mt-2 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 transition-colors"
                    >
                        <Upload :size="15" /> Import Transactions
                    </button>
                </div>

                <!-- ── Pagination ── -->
                <div v-if="transactions.last_page > 1" class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">
                        Showing {{ transactions.from }}–{{ transactions.to }} of {{ totalItems }} transactions
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            @click="goToPage(transactions.prev_page_url)"
                            :disabled="!transactions.prev_page_url"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            <ChevronLeft :size="15" /> Prev
                        </button>
                        <span class="text-sm text-gray-500 font-medium">{{ transactions.current_page }} / {{ transactions.last_page }}</span>
                        <button
                            @click="goToPage(transactions.next_page_url)"
                            :disabled="!transactions.next_page_url"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            Next <ChevronRight :size="15" />
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
