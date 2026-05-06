<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    checklist:  { type: Array,  required: true },
    dismissUrl: { type: String, required: true },
    tenantType: { type: String, default: 'business' },
})

const dismissing = ref(false)

const doneCount = props.checklist.filter(i => i.done).length
const total     = props.checklist.length

function dismiss() {
    dismissing.value = true
    router.post(props.dismissUrl, {}, {
        preserveScroll: true,
        onFinish: () => { dismissing.value = false },
    })
}

function navigate(item) {
    if (item.done || !item.href) return
    router.visit(item.href)
}
</script>

<template>
    <div class="bg-white rounded-2xl border border-violet-200 shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 bg-violet-50 border-b border-violet-100">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-xl bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Get started with Accobot</p>
                    <p class="text-xs text-gray-500">{{ doneCount }} of {{ total }} done</p>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="flex items-center gap-3">
                <div class="w-24 h-1.5 bg-violet-100 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-violet-600 rounded-full transition-all duration-500"
                        :style="{ width: (doneCount / total * 100) + '%' }"
                    />
                </div>
                <button
                    @click="dismiss"
                    :disabled="dismissing"
                    class="text-xs text-gray-400 hover:text-gray-600 transition"
                    title="Dismiss"
                >
                    Dismiss
                </button>
            </div>
        </div>

        <!-- Checklist items -->
        <ul class="divide-y divide-gray-50">
            <li
                v-for="item in checklist"
                :key="item.key"
                :class="[
                    'flex items-center gap-4 px-6 py-3.5 transition',
                    !item.done && item.href ? 'cursor-pointer hover:bg-gray-50 group' : '',
                ]"
                @click="navigate(item)"
            >
                <!-- Tick / circle -->
                <div :class="[
                    'h-5 w-5 rounded-full flex items-center justify-center shrink-0 transition',
                    item.done ? 'bg-violet-600' : 'border-2 border-gray-300 group-hover:border-violet-400',
                ]">
                    <svg v-if="item.done" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>

                <span :class="[
                    'text-sm flex-1',
                    item.done ? 'text-gray-400 line-through' : 'text-gray-700 group-hover:text-violet-700 font-medium',
                ]">{{ item.label }}</span>

                <svg
                    v-if="!item.done && item.href"
                    class="h-4 w-4 text-gray-300 group-hover:text-violet-400 shrink-0 transition"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </li>
        </ul>
    </div>
</template>
