<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
    business:    Object,
    stats:       Object,
    permissions: Array,
})

// Group permissions by their domain for display
const grouped = (permissions) => {
    const groups = {}
    permissions.forEach(p => {
        const words = p.split(' ')
        const domain = words.length > 1 ? words.slice(1).join(' ') : p
        const key = domain.charAt(0).toUpperCase() + domain.slice(1)
        if (!groups[key]) groups[key] = []
        groups[key].push(p)
    })
    return groups
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-500 mb-0.5">Chartered Accountant</p>
                <h1 class="text-xl font-semibold text-gray-900">{{ business.name }}</h1>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-5">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Team Members</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.members }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                        <p class="text-sm text-gray-500 mb-1">Your Permissions</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.permissions }}</p>
                    </div>
                </div>

                <!-- Permissions list -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="font-semibold text-gray-800">Your Access in this Business</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Permissions assigned to your role</p>
                    </div>
                    <div class="p-6 grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div
                            v-for="perm in permissions"
                            :key="perm"
                            class="flex items-center gap-2 text-sm text-gray-700"
                        >
                            <svg class="h-4 w-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ perm }}</span>
                        </div>
                    </div>
                </div>



            </div>
        </div>
    </AuthenticatedLayout>
</template>
