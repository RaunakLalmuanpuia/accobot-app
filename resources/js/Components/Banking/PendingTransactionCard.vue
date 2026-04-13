<script setup>
import { ref, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { hasPermission } from '@/utils/permissions';
import {
    Check, Wand2, TrendingUp, TrendingDown, AlertTriangle, Edit3,
    Link2, Link2Off, FileText, ChevronDown, ChevronUp, Sparkles, X, Zap,
} from 'lucide-vue-next';

const props = defineProps({
    transaction: { type: Object, required: true },
    heads:       { type: Array,  default: () => [] },
});

const page = usePage();
const currentTenantId = () => page.props.auth.current_tenant_id;

const canReview = hasPermission('transactions.review');
const canEdit   = hasPermission('transactions.edit');

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(amount);

const isCredit   = computed(() => props.transaction.type === 'credit');
const isReviewed = computed(() => props.transaction.review_status !== 'pending');

const relevantHeads = computed(() =>
    props.heads.filter(h => h.type === props.transaction.type || h.type === 'both')
);

const hasInvoiceSection = computed(() =>
    (props.transaction.invoice_suggestions?.length > 0) || props.transaction.is_reconciled
);

// ── Initial head/sub-head from transaction data ────────────────────────────
const getInitData = () => {
    if (props.transaction.narration_sub_head_id) {
        for (const h of relevantHeads.value) {
            const subs = h.active_sub_heads || h.sub_heads || [];
            const s = subs.find(sub => sub.id === props.transaction.narration_sub_head_id);
            if (s) return { head: h, subHead: s };
        }
    }
    if (props.transaction.narration_head_id) {
        const h = relevantHeads.value.find(h => h.id === props.transaction.narration_head_id);
        if (h) return { head: h, subHead: null };
    }
    return { head: null, subHead: null };
};

const init = computed(() => getInitData());

const isExpanded       = ref(false);
const selectedHead     = ref(init.value?.head ?? null);
const selectedSub      = ref(init.value?.subHead ?? null);
const saveRule         = ref(false);
const selectedInvoiceId = ref(props.transaction.is_reconciled ? props.transaction.reconciled_invoice_id : null);

// Invoice picker state
const showAllSuggestions = ref(false);
const manualRef          = ref('');

const form = useForm({
    narration_head_id:     init.value?.head?.id ?? '',
    narration_sub_head_id: init.value?.subHead?.id ?? '',
    party_name:            props.transaction.party_name ?? '',
    narration_note:        props.transaction.narration_note ?? '',
    save_as_rule:          false,
    invoice_id:            props.transaction.is_reconciled ? props.transaction.reconciled_invoice_id : null,
    invoice_number:        null,
    unreconcile:           false,
});

const activeSubHeads = computed(() =>
    selectedHead.value?.active_sub_heads || selectedHead.value?.sub_heads || []
);

const isRuleBased   = computed(() => props.transaction.narration_source === 'rule_based');
const isAiSuggested = computed(() => props.transaction.narration_source === 'ai_suggested');
const aiConfidence  = computed(() => {
    const c = props.transaction.ai_confidence;
    return c ? Math.round(c * 100) : null;
});

const vendorStepLabel = computed(() => hasInvoiceSection.value ? '4. Vendor Name' : '3. Vendor Name');
const noteStepLabel   = computed(() => hasInvoiceSection.value ? '5. Additional Note' : '4. Additional Note');

// ── Handlers ───────────────────────────────────────────────────────────────

const handlePickHead = (head) => {
    selectedHead.value = head;
    selectedSub.value  = null;
    form.narration_head_id     = head.id;
    form.narration_sub_head_id = '';
};

const handlePickSub = (sub) => {
    if (selectedSub.value?.id === sub.id) {
        selectedSub.value = null;
        form.narration_sub_head_id = '';
    } else {
        selectedSub.value = sub;
        form.narration_sub_head_id = sub.id;
    }
};

const handleInvoiceSelect = (value) => {
    selectedInvoiceId.value = value;
    if (value === null) {
        form.invoice_id     = null;
        form.invoice_number = null;
        form.unreconcile    = props.transaction.is_reconciled;
    } else if (typeof value === 'number') {
        form.invoice_id     = value;
        form.invoice_number = null;
        form.unreconcile    = false;
    } else {
        form.invoice_id     = null;
        form.invoice_number = value;
        form.unreconcile    = false;
    }
};

const handleManualRefSubmit = () => {
    if (!manualRef.value.trim()) return;
    const suggestions = props.transaction.invoice_suggestions || [];
    const found = suggestions.find(
        s => s.invoice_number.toLowerCase() === manualRef.value.toLowerCase().trim()
    );
    handleInvoiceSelect(found ? found.id : manualRef.value.trim());
    manualRef.value = '';
};

const handleQuickApprove = () => {
    if (!form.narration_head_id) { isExpanded.value = true; return; }
    submitForm();
};

const submitForm = (e) => {
    if (e) e.preventDefault();
    form.post(route('banking.transactions.review', {
        tenant:      currentTenantId(),
        transaction: props.transaction.id,
        action:      'correct',
    }), {
        preserveScroll: true,
        onSuccess: () => { isExpanded.value = false; },
    });
};

const handleCancel = () => {
    isExpanded.value        = false;
    selectedHead.value      = init.value?.head ?? null;
    selectedSub.value       = init.value?.subHead ?? null;
    saveRule.value          = false;
    selectedInvoiceId.value = props.transaction.is_reconciled ? props.transaction.reconciled_invoice_id : null;
    form.reset();
};
</script>

<template>
    <div :class="[
        'rounded-2xl shadow-sm border overflow-hidden transition-all relative',
        isReviewed ? 'bg-gray-50 border-gray-200' : 'bg-white border-gray-100'
    ]">
        <!-- Status banner -->
        <div :class="[
            'text-white text-[10px] font-bold px-4 py-1.5 text-center tracking-widest uppercase',
            isReviewed ? 'bg-emerald-500' : 'bg-indigo-600'
        ]">
            {{ isReviewed ? 'Reviewed' : 'Pending' }}
        </div>

        <div class="p-5 sm:p-6">
            <div class="flex gap-4">
                <!-- Icon -->
                <div :class="[
                    'flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center',
                    isCredit ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'
                ]">
                    <TrendingUp v-if="isCredit" :size="24" />
                    <TrendingDown v-else :size="24" />
                </div>

                <div class="flex-1 w-full min-w-0">
                    <h3 :class="['text-lg font-semibold mb-1', isReviewed ? 'text-gray-600' : 'text-gray-800']">
                        {{ formatCurrency(transaction.amount) }} {{ isCredit ? 'received' : 'debit for' }}
                        {{ form.party_name ? `'${form.party_name}'` : '' }}
                    </h3>
                    <p class="text-sm text-gray-500 font-mono mb-4">{{ transaction.raw_narration }}</p>

                    <!-- ── Collapsed ── -->
                    <template v-if="!isExpanded">
                        <!-- Rule-based suggestion box -->
                        <div v-if="!isReviewed && isRuleBased && init?.head"
                            class="bg-amber-50 border border-amber-100 rounded-xl p-3 mb-3 flex gap-3 text-sm">
                            <Zap :size="16" class="text-amber-500 flex-shrink-0 mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                    <span class="not-italic font-semibold text-amber-800">
                                        {{ init.head.name }}{{ init.subHead ? ` → ${init.subHead.name}` : '' }}
                                    </span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-200 text-amber-800 uppercase tracking-wide">
                                        Rule matched
                                    </span>
                                </div>
                                <p v-if="transaction.narration_note" class="text-amber-700/80 italic text-xs truncate">
                                    {{ transaction.narration_note }}
                                </p>
                            </div>
                        </div>

                        <!-- AI suggestion box -->
                        <div v-else-if="!isReviewed && isAiSuggested && init?.head"
                            class="bg-[#F8FAFC] border border-indigo-50 rounded-xl p-3 mb-3 flex gap-3 text-sm">
                            <Sparkles :size="16" class="text-indigo-400 flex-shrink-0 mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                    <span class="not-italic font-semibold text-indigo-700">
                                        {{ init.head.name }}{{ init.subHead ? ` → ${init.subHead.name}` : '' }}
                                    </span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 uppercase tracking-wide">
                                        AI suggested
                                    </span>
                                    <span v-if="aiConfidence" class="text-[10px] font-semibold text-gray-400">
                                        {{ aiConfidence }}% confidence
                                    </span>
                                </div>
                                <p v-if="transaction.narration_note" class="text-gray-500 italic text-xs truncate">
                                    {{ transaction.narration_note }}
                                </p>
                            </div>
                        </div>

                        <!-- Pending but no suggestion yet -->
                        <div v-else-if="!isReviewed && !init?.head"
                            class="bg-gray-50 border border-gray-100 rounded-xl p-3 mb-3 flex gap-3 text-sm text-gray-400">
                            <AlertTriangle :size="16" class="flex-shrink-0 mt-0.5 text-gray-300" />
                            <p class="italic text-xs">No suggestion yet — categorize manually.</p>
                        </div>

                        <!-- Reviewed categorisation box -->
                        <div v-if="isReviewed && init?.head"
                            class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 mb-3 flex gap-3 text-sm">
                            <Check :size="18" class="text-emerald-500 flex-shrink-0 mt-0.5" />
                            <div>
                                <span class="block font-semibold text-emerald-800 mb-0.5">
                                    Categorized as: {{ init.head.name }}{{ init.subHead ? ` → ${init.subHead.name}` : '' }}
                                </span>
                                <p v-if="transaction.narration_note" class="text-emerald-600/80 italic text-xs">
                                    Note: {{ transaction.narration_note }}
                                </p>
                            </div>
                        </div>

                        <!-- Reconciliation strip -->
                        <div v-if="transaction.is_reconciled && transaction.reconciled_invoice"
                            class="mt-3 rounded-xl border border-teal-100 bg-teal-50 px-3 py-2 flex items-center gap-2">
                            <Link2 :size="13" class="text-teal-600 flex-shrink-0" />
                            <p class="text-xs font-semibold text-teal-800 truncate">
                                Reconciled · {{ transaction.reconciled_invoice.invoice_number }}
                                <span class="font-normal text-teal-600"> · {{ transaction.reconciled_invoice.client_name }}</span>
                            </p>
                        </div>
                        <div v-else-if="transaction.invoice_suggestions?.length"
                            class="mt-3 rounded-xl border border-indigo-100 bg-indigo-50/50 px-3 py-2 flex items-center gap-2">
                            <Sparkles :size="13" class="text-indigo-500 flex-shrink-0" />
                            <p class="text-xs text-indigo-700 font-medium">
                                {{ transaction.invoice_suggestions.length }} possible invoice match{{ transaction.invoice_suggestions.length > 1 ? 'es' : '' }} — open edit to link
                            </p>
                        </div>

                        <!-- Action buttons -->
                        <div v-if="canReview || canEdit" class="flex gap-3 mt-4">
                            <template v-if="!isReviewed">
                                <button
                                    v-if="canReview"
                                    @click="handleQuickApprove"
                                    :disabled="form.processing"
                                    class="flex-1 bg-[#10B981] hover:bg-[#059669] disabled:opacity-50 text-white font-semibold py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 transition-colors"
                                >
                                    <Check :size="20" /> Yes, Confirm
                                </button>
                                <button
                                    v-if="canEdit"
                                    @click="isExpanded = true"
                                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-colors"
                                >
                                    {{ init?.head ? 'Enter Narration' : 'Categorize' }}
                                </button>
                            </template>
                            <button
                                v-else-if="canEdit"
                                @click="isExpanded = true"
                                class="w-full bg-white border-2 border-gray-200 hover:border-gray-300 text-gray-600 font-semibold py-2 px-4 rounded-xl flex items-center justify-center gap-2 transition-colors"
                            >
                                <Edit3 :size="18" /> Edit
                            </button>
                        </div>
                    </template>

                    <!-- ── Expanded form (requires transactions.edit) ── -->
                    <form v-else-if="canEdit" @submit.prevent="submitForm" class="mt-4 border-t border-gray-200 pt-4 space-y-6">

                        <!-- 1. Head -->
                        <div>
                            <p class="mb-2 text-gray-500 uppercase text-[10px] tracking-widest font-bold">1. Select Head *</p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button
                                    v-for="h in relevantHeads"
                                    :key="h.id"
                                    type="button"
                                    @click="handlePickHead(h)"
                                    :class="[
                                        'flex items-center justify-center rounded-xl border-2 py-2 px-2 text-xs font-bold transition-all',
                                        selectedHead?.id === h.id
                                            ? 'border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm'
                                            : 'border-gray-100 bg-white text-gray-500 hover:border-gray-300'
                                    ]"
                                >{{ h.name }}</button>
                            </div>
                            <p v-if="form.errors.narration_head_id" class="mt-1 text-xs text-red-500">{{ form.errors.narration_head_id }}</p>
                        </div>

                        <!-- 2. Sub-Head -->
                        <div v-if="selectedHead && activeSubHeads.length">
                            <p class="mb-2 text-gray-500 uppercase text-[10px] tracking-widest font-bold">2. Sub-Head (Optional)</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <button
                                    v-for="s in activeSubHeads"
                                    :key="s.id"
                                    type="button"
                                    @click="handlePickSub(s)"
                                    :class="[
                                        'flex items-center justify-between rounded-xl border-2 px-4 py-2.5 text-left text-sm font-medium transition-all',
                                        selectedSub?.id === s.id
                                            ? 'border-gray-900 bg-gray-900 text-white shadow-md'
                                            : 'border-gray-100 bg-white text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    {{ s.name }}
                                    <span v-if="selectedSub?.id === s.id" class="text-indigo-400">●</span>
                                </button>
                            </div>
                            <p v-if="form.errors.narration_sub_head_id" class="mt-1 text-xs text-red-500">{{ form.errors.narration_sub_head_id }}</p>
                        </div>

                        <!-- 3. Invoice link -->
                        <div v-if="hasInvoiceSection">
                            <p class="mb-2 text-gray-500 uppercase text-[10px] tracking-widest font-bold">3. Link to Invoice (Optional)</p>

                            <!-- Already reconciled and unchanged -->
                            <template v-if="transaction.is_reconciled && transaction.reconciled_invoice && selectedInvoiceId === transaction.reconciled_invoice_id">
                                <div class="rounded-xl border border-teal-100 bg-teal-50 px-3 py-2.5 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <Link2 :size="14" class="text-teal-600 flex-shrink-0" />
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-teal-800 truncate">Reconciled · {{ transaction.reconciled_invoice.invoice_number }}</p>
                                            <p class="text-xs text-teal-600 truncate">{{ transaction.reconciled_invoice.client_name }} · {{ formatCurrency(transaction.reconciled_invoice.total_amount) }}</p>
                                        </div>
                                    </div>
                                    <button type="button" @click="handleInvoiceSelect(null)" class="text-xs text-teal-600 hover:text-red-600 flex items-center gap-1 font-medium transition-colors flex-shrink-0">
                                        <Link2Off :size="12" /> Unlink
                                    </button>
                                </div>
                            </template>

                            <!-- User picked a new invoice -->
                            <template v-else-if="selectedInvoiceId !== null && selectedInvoiceId !== transaction.reconciled_invoice_id">
                                <div class="rounded-xl border-2 border-indigo-500 bg-indigo-50 px-3 py-2.5 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <Link2 :size="14" class="text-indigo-600 flex-shrink-0" />
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-indigo-800 truncate">
                                                {{ (transaction.invoice_suggestions || []).find(s => s.id === selectedInvoiceId)?.invoice_number || selectedInvoiceId }}
                                            </p>
                                        </div>
                                    </div>
                                    <button type="button" @click="handleInvoiceSelect(null)" class="text-indigo-400 hover:text-indigo-700 transition-colors flex-shrink-0">
                                        <X :size="15" />
                                    </button>
                                </div>
                            </template>

                            <!-- Suggestions picker -->
                            <template v-else-if="(transaction.invoice_suggestions || []).length">
                                <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 overflow-hidden">
                                    <div class="flex items-center justify-between px-3 py-2 border-b border-indigo-100">
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-indigo-700">
                                            <Sparkles :size="13" />
                                            {{ transaction.invoice_suggestions.length === 1 ? 'Matched Invoice' : `${transaction.invoice_suggestions.length} Possible Invoices` }}
                                        </div>
                                        <button v-if="transaction.invoice_suggestions.length > 1" type="button" @click="showAllSuggestions = !showAllSuggestions"
                                            class="flex items-center gap-1 text-xs font-medium text-indigo-500 hover:text-indigo-700 transition-colors">
                                            <template v-if="showAllSuggestions"><ChevronUp :size="13" /> Less</template>
                                            <template v-else><ChevronDown :size="13" /> +{{ transaction.invoice_suggestions.length - 1 }} more</template>
                                        </button>
                                    </div>
                                    <div v-for="s in (showAllSuggestions ? transaction.invoice_suggestions : transaction.invoice_suggestions.slice(0,1))"
                                        :key="s.id"
                                        class="flex items-center justify-between gap-3 px-3 py-2.5 border-b border-indigo-100/60 last:border-b-0">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-xs font-bold text-gray-800">{{ s.invoice_number }}</span>
                                                <span :class="s.match_score >= 70 ? 'text-emerald-700 bg-emerald-100' : s.match_score >= 40 ? 'text-amber-700 bg-amber-100' : 'text-gray-500 bg-gray-100'"
                                                    class="text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                                    {{ s.match_score >= 70 ? 'Strong' : s.match_score >= 40 ? 'Possible' : 'Weak' }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ s.client_name }} · {{ formatCurrency(s.amount_due) }} due · {{ s.invoice_date }}</p>
                                            <p v-if="s.match_reasons?.length" class="text-[10px] text-indigo-500 mt-0.5 truncate">{{ s.match_reasons[0] }}{{ s.match_reasons.length > 1 ? ` +${s.match_reasons.length - 1} more` : '' }}</p>
                                        </div>
                                        <button type="button" @click="handleInvoiceSelect(s.id)"
                                            class="flex-shrink-0 text-xs bg-white border-2 border-indigo-200 hover:border-indigo-500 hover:bg-indigo-600 hover:text-white text-indigo-700 font-semibold px-2.5 py-1 rounded-lg transition-all flex items-center gap-1">
                                            <Link2 :size="12" /> Select
                                        </button>
                                    </div>
                                    <!-- Manual entry -->
                                    <div class="px-3 py-2.5 border-t border-indigo-100 flex items-center gap-2">
                                        <FileText :size="13" class="text-indigo-400 flex-shrink-0" />
                                        <input v-model="manualRef" type="text" placeholder="Or type invoice # manually…"
                                            @keydown.enter.prevent="handleManualRefSubmit"
                                            class="flex-1 bg-white border border-indigo-200 rounded-lg px-2.5 py-1 text-xs outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                                        <button type="button" :disabled="!manualRef.trim()" @click="handleManualRefSubmit"
                                            class="text-xs bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-semibold px-2.5 py-1 rounded-lg transition-colors flex-shrink-0">
                                            Select
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <p v-if="form.errors.invoice_id || form.errors.invoice_number" class="mt-1 text-xs text-red-500">
                                {{ form.errors.invoice_id || form.errors.invoice_number }}
                            </p>
                        </div>

                        <!-- Vendor + Note -->
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label :for="`party_${transaction.id}`" class="block mb-1 text-sm font-medium text-gray-700">
                                    {{ vendorStepLabel }}{{ selectedSub?.requires_party ? ' *' : ' (Optional)' }}
                                </label>
                                <input
                                    :id="`party_${transaction.id}`"
                                    v-model="form.party_name"
                                    type="text"
                                    placeholder="Vendor/Person name"
                                    :required="selectedSub?.requires_party"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <p v-if="form.errors.party_name" class="mt-1 text-xs text-red-500">{{ form.errors.party_name }}</p>
                            </div>
                            <div>
                                <label :for="`note_${transaction.id}`" class="block mb-1 text-sm font-medium text-gray-700">
                                    {{ noteStepLabel }} (Optional)
                                </label>
                                <input
                                    :id="`note_${transaction.id}`"
                                    v-model="form.narration_note"
                                    type="text"
                                    placeholder="Add specific details..."
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <p v-if="form.errors.narration_note" class="mt-1 text-xs text-red-500">{{ form.errors.narration_note }}</p>
                            </div>
                        </div>

                        <!-- Auto-Rule toggle -->
                        <div :class="[
                            'rounded-xl border-2 p-3.5 transition-colors',
                            saveRule ? 'border-indigo-100 bg-indigo-50/30' : 'border-gray-50 bg-gray-50/50'
                        ]">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input
                                    type="checkbox"
                                    v-model="saveRule"
                                    @change="form.save_as_rule = saveRule"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-gray-800">Auto-categorize in future?</span>
                                    <span class="text-[10px] text-gray-500 leading-tight">Remember this choice for similar narrations.</span>
                                </div>
                            </label>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="handleCancel"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!selectedHead || form.processing"
                                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl shadow-sm transition-colors">
                                {{ form.processing ? 'Saving...' : (isReviewed ? 'Update Details' : 'Confirm Details') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
