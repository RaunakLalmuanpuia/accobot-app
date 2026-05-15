<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({
    href: { type: String, required: true },
    active: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="href"
        :class="[
            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors duration-150',
            active
                ? 'bg-violet-50 text-violet-700 font-medium'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
        ]"
    >
        <slot />
    </Link>
</template>
