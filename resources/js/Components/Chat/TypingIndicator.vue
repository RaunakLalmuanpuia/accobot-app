<template>
    <div v-if="typingUsers.length" class="flex items-center gap-2 px-4 py-1 text-sm text-gray-500">
        <span>{{ label }}</span>
        <span class="flex gap-0.5">
            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    typingUsers: { type: Array, default: () => [] },
});

const label = computed(() => {
    const names = props.typingUsers.map(u => u.name);
    if (names.length === 1) return `${names[0]} is typing...`;
    if (names.length === 2) return `${names[0]} and ${names[1]} are typing...`;
    return 'Several people are typing...';
});
</script>
