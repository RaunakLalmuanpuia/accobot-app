<template>
    <div class="flex items-center gap-2 mt-1">
        <template v-if="isImage">
            <img
                :src="downloadUrl"
                :alt="attachment.original_filename"
                class="max-w-[200px] max-h-[200px] rounded-lg object-cover cursor-pointer"
                @click="download"
            />
        </template>
        <template v-else>
            <a
                :href="downloadUrl"
                :download="attachment.original_filename"
                class="flex items-center gap-2 px-3 py-2 bg-white/20 rounded-lg hover:bg-white/30 transition text-sm"
            >
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                <span class="truncate max-w-[160px]">{{ attachment.original_filename }}</span>
                <span class="shrink-0 text-xs opacity-70">{{ formatSize(attachment.size_bytes) }}</span>
            </a>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    attachment:  { type: Object, required: true },
    downloadUrl: { type: String, default: '#' },
});

const isImage = computed(() =>
    props.attachment.mime_type?.startsWith('image/')
);

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function download() {
    window.open(props.downloadUrl, '_blank');
}
</script>
