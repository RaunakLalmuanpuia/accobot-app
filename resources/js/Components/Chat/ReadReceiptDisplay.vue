<template>
    <span v-if="isOwn" class="text-xs ml-1 select-none" :title="readerNames">
        <span v-if="readCount > 0" class="text-violet-300">✓✓</span>
        <span v-else class="text-gray-400">✓</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    messageId:  { type: String, required: true },
    isOwn:      { type: Boolean, default: false },
    reads:      { type: Object, default: () => ({}) },  // { user_id: last_read_message_id }
    members:    { type: Array,  default: () => [] },
});

const readCount = computed(() =>
    Object.values(props.reads).filter(id => id === props.messageId).length
);

const readerNames = computed(() => {
    const readerIds = Object.entries(props.reads)
        .filter(([, id]) => id === props.messageId)
        .map(([uid]) => Number(uid));

    return props.members
        .filter(m => readerIds.includes(m.user?.id))
        .map(m => m.user?.name)
        .join(', ') || '';
});
</script>
