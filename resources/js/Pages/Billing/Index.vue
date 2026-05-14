<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    tenant:         { type: Object, required: true },
    subscription:   { type: Object, default: null },
    availablePlans: { type: Array,  default: () => [] },
    addonPlan:      { type: Object, default: null },
})

const page       = usePage()
const cancelling = ref(false)
const addingAddon = ref(false)
const showCancelConfirm = ref(false)

const formatPrice = (paise) => '₹' + (paise / 100).toLocaleString('en-IN')

const statusLabel = computed(() => {
    const map = {
        active:   { text: 'Active',    classes: 'bg-green-100 text-green-700' },
        trialing: { text: 'Free Trial', classes: 'bg-violet-100 text-violet-700' },
        halted:   { text: 'Payment Failed', classes: 'bg-red-100 text-red-700' },
        cancelled:{ text: 'Cancelled', classes: 'bg-amber-100 text-amber-700' },
        expired:  { text: 'Expired',   classes: 'bg-gray-100 text-gray-500' },
        pending:  { text: 'Pending',   classes: 'bg-blue-100 text-blue-700' },
    }
    return map[props.subscription?.status] ?? { text: props.subscription?.status, classes: 'bg-gray-100 text-gray-500' }
})

const featureLabels = {
    invoicing:    'Invoicing & Accounting',
    tally_sync:   'Tally Sync',
    group_chat:   'Live Group Chat',
    ai_assistant: 'AI Chat Assistant',
    ca_clients:   'CA Client Management',
}

const canCancel = computed(() =>
    props.subscription?.status === 'active' && !props.subscription?.cancelled_at
)

const otherPlans = computed(() =>
    props.availablePlans.filter(p => p.slug !== props.subscription?.plan_slug)
)

const showAddon = computed(() =>
    props.subscription?.plan_slug === 'personal' &&
    !props.subscription?.has_ai_addon &&
    props.addonPlan
)

function confirmCancel() {
    cancelling.value = true
    router.post(route('billing.cancel', props.tenant), {}, {
        preserveScroll: true,
        onFinish: () => { cancelling.value = false; showCancelConfirm.value = false },
    })
}

function changePlan(planId) {
    router.visit(route('billing.select-plan', props.tenant))
}

onMounted(() => {
    if (!document.querySelector('script[src*="checkout.razorpay.com"]')) {
        const script = document.createElement('script')
        script.src = 'https://checkout.razorpay.com/v1/checkout.js'
        document.head.appendChild(script)
    }
})

async function addAddon() {
    addingAddon.value = true

    try {
        const { data } = await axios.post(route('billing.addon', props.tenant), {})

        const options = {
            key:             data.key_id,
            subscription_id: data.subscription_id,
            name:            'Accobot',
            description:     'AI Assistance Addon',
            handler: function () {
                setTimeout(() => router.reload({ preserveScroll: true }), 4000)
            },
            modal: {
                ondismiss: () => { addingAddon.value = false },
            },
            prefill: {
                name:  page.props.auth.user.name,
                email: page.props.auth.user.email,
            },
            theme: { color: '#7c3aed' },
        }

        const rzp = new window.Razorpay(options)
        rzp.on('payment.failed', () => { addingAddon.value = false })
        rzp.open()
    } catch (err) {
        addingAddon.value = false
    }
}
</script>

<template>
    <Head title="Billing" />
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success" class="rounded-xl bg-violet-50 border border-violet-200 px-4 py-3 text-sm text-violet-700">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Page header -->
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Billing &amp; Subscription</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage your plan for <span class="font-medium text-gray-700">{{ tenant.name }}</span>.</p>
                </div>

                <!-- No subscription -->
                <div v-if="!subscription" class="bg-white rounded-2xl border border-gray-200 p-6 text-center">
                    <p class="text-sm text-gray-500 mb-4">You don't have an active subscription.</p>
                    <a :href="route('billing.select-plan', tenant)" class="inline-flex items-center rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 transition">
                        Choose a Plan
                    </a>
                </div>

                <template v-else>
                    <!-- Current plan card -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 bg-violet-50 border-b border-violet-100">
                            <div>
                                <p class="text-xs font-semibold text-violet-600 uppercase tracking-wide">Current Plan</p>
                                <p class="text-lg font-bold text-gray-900 mt-0.5">{{ subscription.plan_name }}</p>
                            </div>
                            <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold', statusLabel.classes]">
                                {{ statusLabel.text }}
                            </span>
                        </div>

                        <div class="px-6 py-5 space-y-4">
                            <!-- Price -->
                            <div class="flex items-baseline gap-1">
                                <span class="text-2xl font-bold text-gray-900">{{ formatPrice(subscription.plan_price) }}</span>
                                <span class="text-sm text-gray-400">/month</span>
                            </div>

                            <!-- Dates -->
                            <div class="text-sm text-gray-500 space-y-1">
                                <p v-if="subscription.trial_ends_at">
                                    Free trial ends: <span class="font-medium text-gray-700">{{ subscription.trial_ends_at }}</span>
                                </p>
                                <p v-else-if="subscription.cancelled_at">
                                    Access until: <span class="font-medium text-amber-600">{{ subscription.current_period_end }}</span>
                                    <span class="ml-2 text-xs text-amber-500">(Cancellation pending)</span>
                                </p>
                                <p v-else-if="subscription.current_period_end">
                                    Renews on: <span class="font-medium text-gray-700">{{ subscription.current_period_end }}</span>
                                </p>
                            </div>

                            <!-- Features -->
                            <ul class="space-y-1.5 pt-1">
                                <li v-for="feat in subscription.features" :key="feat" class="flex items-center gap-2 text-sm text-gray-600">
                                    <svg class="h-4 w-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ featureLabels[feat] ?? feat }}
                                </li>
                            </ul>

                            <!-- Cancel -->
                            <div v-if="canCancel" class="pt-3 border-t border-gray-100">
                                <div v-if="!showCancelConfirm">
                                    <button @click="showCancelConfirm = true" class="text-sm text-red-500 hover:text-red-700 transition">
                                        Cancel subscription
                                    </button>
                                </div>
                                <div v-else class="flex items-center gap-3">
                                    <p class="text-sm text-gray-600">Cancel at end of billing period?</p>
                                    <button
                                        @click="confirmCancel"
                                        :disabled="cancelling"
                                        class="text-sm font-medium text-red-600 hover:text-red-800 transition disabled:opacity-50"
                                    >{{ cancelling ? 'Cancelling…' : 'Yes, cancel' }}</button>
                                    <button @click="showCancelConfirm = false" class="text-sm text-gray-400 hover:text-gray-600 transition">
                                        Never mind
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Addon active (Personal plan only) -->
                    <div v-if="subscription?.plan_slug === 'personal' && subscription?.has_ai_addon && addonPlan" class="bg-white rounded-2xl border border-violet-200 shadow-sm p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-900">{{ addonPlan.name }}</p>
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Active</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">AI-powered accounting assistance is enabled on your plan.</p>
                                <p class="mt-2 text-lg font-bold text-gray-900">
                                    {{ formatPrice(addonPlan.price) }}<span class="text-sm font-normal text-gray-400">/mo</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- AI Addon (Personal plan only) -->
                    <div v-if="showAddon" class="bg-white rounded-2xl border border-violet-200 shadow-sm p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ addonPlan.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">Add AI-powered accounting assistance to your Personal plan.</p>
                                <p class="mt-2 text-lg font-bold text-gray-900">
                                    {{ formatPrice(addonPlan.price) }}<span class="text-sm font-normal text-gray-400">/mo</span>
                                </p>
                            </div>
                            <button
                                @click="addAddon"
                                :disabled="addingAddon"
                                class="shrink-0 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 transition disabled:opacity-50"
                            >{{ addingAddon ? 'Redirecting…' : 'Add Addon' }}</button>
                        </div>
                    </div>

                    <!-- Change plan -->
                    <div v-if="otherPlans.length > 0 && subscription.status !== 'cancelled'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900">Switch Plan</p>
                            <p class="mt-0.5 text-xs text-gray-500">Your current subscription will be cancelled and a new one started.</p>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <div v-for="plan in otherPlans" :key="plan.id" class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ plan.name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatPrice(plan.price) }}/mo</p>
                                </div>
                                <button
                                    @click="changePlan(plan.id)"
                                    class="rounded-lg border border-violet-300 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-50 transition"
                                >Switch</button>
                            </div>
                        </div>
                    </div>

                    <!-- Halted — payment failed banner -->
                    <div v-if="subscription.status === 'halted'" class="rounded-xl bg-red-50 border border-red-200 p-5">
                        <p class="text-sm font-semibold text-red-800">Payment failed</p>
                        <p class="mt-1 text-sm text-red-700">Razorpay will retry your payment automatically. Update your payment method to avoid losing access.</p>
                        <div class="mt-3 flex flex-wrap gap-3">
                            <a
                                v-if="subscription.razorpay_short_url"
                                :href="subscription.razorpay_short_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition"
                            >
                                Update Payment Method
                            </a>
                            <a :href="route('billing.select-plan', tenant)" class="inline-flex items-center rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
                                Choose New Plan
                            </a>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
