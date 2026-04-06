<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePage } from '@inertiajs/vue3'

defineProps({
    business:    Object,
    permissions: Array,
})

const user = usePage().props.auth.user
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-0.5">Member</p>
                <h1 class="text-xl font-semibold text-gray-900">{{ business.name }}</h1>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Welcome -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
                    <h2 class="text-lg font-semibold text-gray-900 mb-0.5">Welcome back, {{ user.name.split(' ')[0] }}</h2>
                    <p class="text-sm text-gray-500">You are a member of <span class="font-medium text-gray-700">{{ business.name }}</span>.</p>
                </div>

                <!-- What you can do -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="font-semibold text-gray-800">Your Access</h2>
                        <p class="text-xs text-gray-400 mt-0.5">What you can do in this business</p>
                    </div>
                    <div class="p-6">
                        <div v-if="permissions.length" class="grid grid-cols-2 gap-3">
                            <div
                                v-for="perm in permissions"
                                :key="perm"
                                class="flex items-center gap-2 text-sm text-gray-700"
                            >
                                <svg class="h-4 w-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span class="capitalize">{{ perm }}</span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400">No permissions assigned yet. Contact your business owner.</p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
