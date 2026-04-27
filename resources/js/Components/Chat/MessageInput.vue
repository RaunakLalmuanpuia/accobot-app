<template>
    <div class="border-t border-gray-200 bg-white px-4 py-3">
        <!-- Reply bar -->
        <div v-if="replyTo" class="flex items-center justify-between mb-2 px-3 py-1.5 rounded-lg bg-violet-50 border border-violet-200 text-sm">
            <div class="min-w-0">
                <span class="font-medium text-violet-700">{{ replyTo.sender?.name ?? replyTo.sender_name }}:</span>
                <span class="text-gray-600 ml-1 truncate">{{ replyTo.body }}</span>
            </div>
            <button @click="$emit('cancel-reply')" class="ml-2 text-gray-400 hover:text-gray-600 shrink-0">✕</button>
        </div>

        <!-- Pending attachments -->
        <div v-if="pendingFiles.length" class="flex flex-wrap gap-2 mb-2">
            <div
                v-for="(f, i) in pendingFiles"
                :key="i"
                class="flex items-center gap-1 px-2 py-1 bg-gray-100 rounded-lg text-xs text-gray-600"
            >
                <span class="truncate max-w-[120px]">{{ f.name }}</span>
                <button @click="removeFile(i)" class="text-gray-400 hover:text-red-500 ml-1">✕</button>
            </div>
        </div>

        <div class="flex items-end gap-2">
            <!-- Attachment button -->
            <button
                @click="fileInput.click()"
                class="shrink-0 text-gray-400 hover:text-violet-600 transition p-1"
                title="Attach file"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
            </button>
            <input ref="fileInput" type="file" class="hidden" multiple @change="onFileChange" />

            <!-- Textarea -->
            <textarea
                ref="textarea"
                v-model="body"
                rows="1"
                placeholder="Type a message…"
                class="flex-1 resize-none rounded-2xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent max-h-32 overflow-y-auto"
                @keydown.enter.exact.prevent="send"
                @keydown.shift.enter="null"
                @input="autoResize(); onTyping()"
            ></textarea>

            <!-- Send button -->
            <button
                @click="send"
                :disabled="!canSend"
                class="shrink-0 w-9 h-9 rounded-full bg-violet-600 text-white flex items-center justify-center hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';

const props = defineProps({
    replyTo: { type: Object, default: null },
});

const emit = defineEmits(['send', 'cancel-reply', 'typing']);

const body        = ref('');
const pendingFiles = ref([]);
const fileInput   = ref(null);
const textarea    = ref(null);

let typingTimeout = null;

const canSend = computed(() => body.value.trim() || pendingFiles.value.length > 0);

function send() {
    if (!canSend.value) return;
    emit('send', {
        body:                body.value.trim() || null,
        files:               pendingFiles.value,
        reply_to_message_id: props.replyTo?.id ?? null,
    });
    body.value        = '';
    pendingFiles.value = [];
    nextTick(() => autoResize());
    emit('typing', false);
    clearTimeout(typingTimeout);
}

function onTyping() {
    emit('typing', true);
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => emit('typing', false), 3000);
}

function autoResize() {
    const el = textarea.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 128) + 'px';
}

function onFileChange(e) {
    pendingFiles.value = [...pendingFiles.value, ...Array.from(e.target.files)];
    e.target.value = '';
}

function removeFile(index) {
    pendingFiles.value.splice(index, 1);
}
</script>
