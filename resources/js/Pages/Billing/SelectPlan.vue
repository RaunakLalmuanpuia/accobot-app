<script setup>
import { computed, ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    tenant:       { type: Object, required: true },
    plans:        { type: Array,  required: true },
    subscription: { type: Object, default: null },
})

const page = usePage()
const selectedPlanId = ref(null)
const submitting = ref(false)
const errorMsg = ref(null)

const trialExpired = computed(() =>
    props.subscription?.status === 'trialing' &&
    props.subscription?.trial_ends_at &&
    new Date(props.subscription.trial_ends_at) < new Date()
)

const isTrialing = computed(() =>
    props.subscription?.status === 'trialing' && !trialExpired.value
)

const formatPrice = (paise) => '₹' + (paise / 100).toLocaleString('en-IN')

const featureLabels = {
    invoicing:     'Invoicing & Accounting',
    tally_sync:    'Tally Sync',
    group_chat:    'Live Group Chat',
    ai_assistant:  'AI Chat Assistant',
    ca_clients:    'CA Client Management',
}

onMounted(() => {
    if (!document.querySelector('script[src*="checkout.razorpay.com"]')) {
        const script = document.createElement('script')
        script.src = 'https://checkout.razorpay.com/v1/checkout.js'
        document.head.appendChild(script)
    }
})

async function subscribe() {
    if (!selectedPlanId.value || submitting.value) return
    submitting.value = true
    errorMsg.value = null

    try {
        const { data } = await axios.post(route('billing.subscribe', props.tenant), {
            plan_id: selectedPlanId.value,
        })

        const options = {
            key:             data.key_id,
            subscription_id: data.subscription_id,
            name:            'Accobot',
            description:     'Monthly Subscription',
            handler: function () {
                window.location.href = route('dashboard', props.tenant)
            },
            modal: {
                ondismiss: () => { submitting.value = false },
            },
            prefill: {
                name:  page.props.auth.user.name,
                email: page.props.auth.user.email,
            },
            theme: { color: '#7c3aed' },
        }

        const rzp = new window.Razorpay(options)
        rzp.on('payment.failed', () => {
            submitting.value = false
            errorMsg.value = 'Payment failed. Please try again.'
        })
        rzp.open()
    } catch (err) {
        submitting.value = false
        errorMsg.value = err.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}
</script>

<template>
    <Head title="Choose a Plan" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Choose a Plan</h1>
        </template>

        <div class="min-h-screen bg-gray-50 py-12 px-4">
            <div class="max-w-3xl mx-auto">

                <!-- Header -->
                <div class="text-center mb-10">
                    <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-600 mb-4">
                        <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Choose your plan</h1>

                    <p v-if="trialExpired" class="mt-2 text-sm text-red-600">
                        Your free trial has ended. Subscribe to continue using Accobot.
                    </p>
                    <p v-else-if="isTrialing" class="mt-2 text-sm text-violet-600">
                        You're on a free trial. Subscribe now to keep uninterrupted access.
                    </p>
                    <p v-else class="mt-2 text-sm text-gray-500">
                        Select a plan to get started with <span class="font-medium text-gray-700">{{ tenant.name }}</span>.
                    </p>
                </div>

                <!-- Error -->
                <div v-if="errorMsg" class="mb-6 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 text-center">
                    {{ errorMsg }}
                </div>

                <!-- Plan cards -->
                <div :class="['grid gap-4', plans.length === 1 ? 'grid-cols-1 max-w-sm mx-auto' : 'grid-cols-1 sm:grid-cols-2']">
                    <button
                        v-for="plan in plans"
                        :key="plan.id"
                        type="button"
                        @click="selectedPlanId = plan.id"
                        :class="[
                            'relative text-left rounded-2xl border-2 p-6 transition focus:outline-none',
                            selectedPlanId === plan.id
                                ? 'border-violet-600 bg-violet-50 shadow-md'
                                : 'border-gray-200 bg-white hover:border-violet-300 hover:shadow-sm',
                        ]"
                    >
                        <!-- Selected badge -->
                        <div
                            v-if="selectedPlanId === plan.id"
                            class="absolute top-3 right-3 h-5 w-5 rounded-full bg-violet-600 flex items-center justify-center"
                        >
                            <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>

                        <p class="text-sm font-semibold text-violet-700 uppercase tracking-wide">{{ plan.name }}</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900">
                            {{ formatPrice(plan.price) }}
                            <span class="text-base font-normal text-gray-400">/mo</span>
                        </p>

                        <ul class="mt-4 space-y-2">
                            <li
                                v-for="feat in plan.features"
                                :key="feat"
                                class="flex items-center gap-2 text-sm text-gray-600"
                            >
                                <svg class="h-4 w-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ featureLabels[feat] ?? feat }}
                            </li>
                        </ul>
                    </button>
                </div>

                <!-- Subscribe button -->
                <div class="mt-8 text-center">
                    <button
                        @click="subscribe"
                        :disabled="!selectedPlanId || submitting"
                        :class="[
                            'inline-flex items-center gap-2 rounded-xl px-8 py-3 text-sm font-semibold text-white transition',
                            selectedPlanId && !submitting
                                ? 'bg-violet-600 hover:bg-violet-700 shadow-sm'
                                : 'bg-violet-300 cursor-not-allowed',
                        ]"
                    >
                        <svg v-if="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        {{ submitting ? 'Opening payment…' : 'Continue to Payment' }}
                    </button>
                    <p class="mt-3 text-xs text-gray-400">
                        Secure payment via Razorpay. Cancel anytime.
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
