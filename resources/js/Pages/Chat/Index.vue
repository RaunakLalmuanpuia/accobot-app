<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, nextTick } from 'vue';
import axios from 'axios';
import { marked } from 'marked';

marked.use({
    breaks: true,
    renderer: {
        link({ href, text }) {
            return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="underline font-medium text-indigo-400 hover:text-indigo-200">${text}</a>`;
        },
    },
});

const renderMarkdown = (text) => marked.parse(text ?? '');

const page = usePage();
const currentTenantId = () => page.props.auth.current_tenant_id;

const messages = ref([
    {
        role: 'assistant',
        content: `Hello! I'm your accounting assistant. Here's what I can help you with:

**Invoices**
- Create, view, and update invoices
- Download invoice PDFs

**Clients & Inventory**
- Search, create, and update clients
- Manage your product/service inventory

**Bank Transaction Narration**
- View pending bank transactions
- Suggest and save narrations (head, sub-head, party name, note) for your CA

**Narration Heads & Sub-Heads**
- Create or edit narration heads (e.g. "add a Utilities head, debit")
- Add sub-heads under any head (e.g. "add Electricity under Utilities")

What would you like to do?`,
    },
]);

const input = ref('');
const loading = ref(false);
const lastInvoice = ref(null);
const invoiceList = ref([]);
const messagesContainer = ref(null);

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const send = async () => {
    const text = input.value.trim();
    if (!text || loading.value) return;

    messages.value.push({ role: 'user', content: text });
    input.value = '';
    loading.value = true;
    lastInvoice.value = null;
    invoiceList.value = [];
    await scrollToBottom();

    try {
        const history = messages.value.slice(0, -1).slice(-10);
        const { data } = await axios.post(route('chat.store', { tenant: currentTenantId() }), {
            message: text,
            history,
        });

        messages.value.push({ role: 'assistant', content: data.reply });

        if (data.invoice) {
            lastInvoice.value = data.invoice;
        }
        if (data.invoices && data.invoices.length) {
            invoiceList.value = data.invoices;
        }
    } catch (err) {
        const status = err.response?.status;
        const content = status === 403
            ? "You don't have permission to use the Accounting Assistant. Please contact your administrator."
            : status === 422
            ? 'Your message could not be processed. Please check your input and try again.'
            : 'Sorry, something went wrong. Please try again.';
        messages.value.push({ role: 'assistant', content });
    } finally {
        loading.value = false;
        await scrollToBottom();
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
};

const formatCurrency = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(value);
</script>

<template>
    <Head title="Accounting Assistant" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Accounting Assistant
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 flex flex-col gap-4">

                <!-- Chat Window -->
                <div class="bg-white shadow-sm rounded-lg flex flex-col" style="height: 65vh;">

                    <!-- Messages -->
                    <div
                        ref="messagesContainer"
                        class="flex-1 overflow-y-auto p-4 space-y-4"
                    >
                        <div
                            v-for="(msg, i) in messages"
                            :key="i"
                            class="flex"
                            :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                class="max-w-[80%] rounded-2xl px-4 py-2 text-sm prose prose-sm"
                                :class="msg.role === 'user'
                                    ? 'bg-indigo-600 text-white rounded-br-sm prose-invert'
                                    : 'bg-gray-100 text-gray-800 rounded-bl-sm'"
                                v-html="renderMarkdown(msg.content)"
                            ></div>
                        </div>

                        <!-- Typing indicator -->
                        <div v-if="loading" class="flex justify-start">
                            <div class="bg-gray-100 rounded-2xl rounded-bl-sm px-4 py-3 flex gap-1 items-center">
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Input Bar -->
                    <div class="border-t p-3 flex gap-2 items-end">
                        <textarea
                            v-model="input"
                            @keydown="handleKeydown"
                            :disabled="loading"
                            rows="2"
                            placeholder="Create an invoice, narrate transactions, search clients or inventory..."
                            class="flex-1 resize-none rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
                        ></textarea>
                        <button
                            @click="send"
                            :disabled="loading || !input.trim()"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            Send
                        </button>
                    </div>
                </div>

                <!-- Invoice Preview Card -->
                <div v-if="lastInvoice" class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ lastInvoice.invoice_number }}</h3>
                            <p class="text-sm text-gray-500">Client: {{ lastInvoice.client }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800 capitalize">
                            {{ lastInvoice.status }}
                        </span>
                    </div>

                    <div class="text-xs text-gray-500 mb-4 flex gap-4">
                        <span>Issue: {{ lastInvoice.issue_date }}</span>
                        <span>Due: {{ lastInvoice.due_date }}</span>
                    </div>

                    <!-- Line Items -->
                    <table class="w-full text-sm mb-4">
                        <thead>
                            <tr class="border-b text-left text-gray-500 text-xs uppercase">
                                <th class="pb-2">Description</th>
                                <th class="pb-2 text-right">Qty</th>
                                <th class="pb-2 text-right">Unit Price</th>
                                <th class="pb-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in lastInvoice.items" :key="idx" class="border-b last:border-0">
                                <td class="py-2">{{ item.description }}</td>
                                <td class="py-2 text-right">{{ item.quantity }}</td>
                                <td class="py-2 text-right">{{ formatCurrency(item.unit_price, lastInvoice.currency) }}</td>
                                <td class="py-2 text-right font-medium">{{ formatCurrency(item.total, lastInvoice.currency) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Totals -->
                    <div class="flex flex-col items-end gap-1 text-sm">
                        <div class="flex gap-8 text-gray-500">
                            <span>Subtotal</span>
                            <span>{{ formatCurrency(lastInvoice.subtotal, lastInvoice.currency) }}</span>
                        </div>
                        <div class="flex gap-8 text-gray-500">
                            <span>Tax</span>
                            <span>{{ formatCurrency(lastInvoice.tax_amount, lastInvoice.currency) }}</span>
                        </div>
                        <div class="flex gap-8 font-bold text-gray-900 text-base border-t pt-2 mt-1">
                            <span>Total</span>
                            <span>{{ formatCurrency(lastInvoice.total, lastInvoice.currency) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Invoice List -->
                <div v-if="invoiceList.length" class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3 border-b flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Invoices</h3>
                        <span class="text-xs text-gray-400">{{ invoiceList.length }} result(s)</span>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                                <th class="px-5 py-2.5 font-medium">Invoice</th>
                                <th class="px-5 py-2.5 font-medium">Client</th>
                                <th class="px-5 py-2.5 font-medium">Issue Date</th>
                                <th class="px-5 py-2.5 font-medium">Due Date</th>
                                <th class="px-5 py-2.5 font-medium text-right">Total</th>
                                <th class="px-5 py-2.5 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="inv in invoiceList" :key="inv.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 font-mono font-medium text-gray-800">{{ inv.invoice_number }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ inv.client }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ inv.issue_date }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ inv.due_date ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-medium text-gray-800">{{ formatCurrency(inv.total, inv.currency) }}</td>
                                <td class="px-5 py-3">
                                    <span :class="{
                                        'bg-gray-100 text-gray-600': inv.status === 'draft',
                                        'bg-blue-100 text-blue-700': inv.status === 'sent',
                                        'bg-green-100 text-green-700': inv.status === 'paid',
                                        'bg-red-100 text-red-700': inv.status === 'overdue',
                                        'bg-gray-100 text-gray-400': inv.status === 'cancelled',
                                    }" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                                        {{ inv.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
