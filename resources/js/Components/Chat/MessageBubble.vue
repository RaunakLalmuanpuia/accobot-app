<template>
    <div class="flex flex-col" :class="[isOwn ? 'items-end' : 'items-start', grouped ? 'mt-0.5' : 'mt-3']">

        <!-- Sender name (received, first in group) -->
        <span v-if="showSender && !isOwn && !grouped" class="text-xs text-gray-500 font-medium mb-1 ml-9">
            {{ message.type === 'system' ? 'System' : (message.sender?.name ?? message.sender_name) }}
        </span>

        <div class="flex items-end gap-2" :class="isOwn ? 'flex-row-reverse' : 'flex-row'">

            <!-- Avatar (received messages) -->
            <div class="w-7 shrink-0" v-if="!isOwn">
                <div v-if="!grouped"
                    class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                    :class="message.type === 'system'
                        ? 'bg-gray-100 text-gray-500'
                        : 'bg-violet-100 text-violet-700'"
                >
                    <template v-if="message.type === 'system'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </template>
                    <template v-else>{{ senderInitials }}</template>
                </div>
            </div>

            <!-- Bubble + reply button -->
            <div class="group flex items-end gap-1.5" :class="isOwn ? 'flex-row-reverse' : 'flex-row'">

                <!-- Action buttons (hover) — hidden for system messages -->
                <div
                    v-if="!message.deleted_at && message.type !== 'system'"
                    class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5 mb-1"
                    :class="isOwn ? 'flex-row-reverse' : 'flex-row'"
                >
                    <!-- Reply -->
                    <button
                        @click="$emit('reply', message)"
                        class="p-1 text-gray-400 hover:text-violet-600 shrink-0"
                        title="Reply"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h10a4 4 0 010 8H9m-6-8l4-4M3 10l4 4"/>
                        </svg>
                    </button>

                    <!-- Delete (own messages or with permission) -->
                    <button
                        v-if="canDelete"
                        @click="$emit('delete', message)"
                        class="p-1 text-gray-400 hover:text-red-500 shrink-0"
                        title="Delete"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>

                <!-- Bubble -->
                <div
                    class="relative px-3 py-2 rounded-2xl text-sm break-words max-w-[75vw] sm:max-w-sm lg:max-w-md"
                    :class="isOwn
                        ? 'bg-violet-600 text-white rounded-br-sm'
                        : message.type === 'system'
                            ? 'bg-gray-50 text-gray-600 rounded-bl-sm border border-gray-200 italic'
                            : 'bg-white text-gray-800 rounded-bl-sm shadow-sm border border-gray-100'"
                >
                    <!-- Reply quote -->
                    <div
                        v-if="message.reply_to_message_id && message.reply_to"
                        class="mb-2 px-2 py-1.5 rounded-lg text-xs border-l-2 overflow-hidden"
                        :class="isOwn
                            ? 'bg-violet-500 border-violet-300 text-violet-100'
                            : 'bg-gray-50 border-violet-400 text-gray-600'"
                    >
                        <p class="font-semibold truncate mb-0.5">{{ message.reply_to.sender?.name ?? message.reply_to.sender_name }}</p>
                        <p class="truncate opacity-80">{{ message.reply_to.body }}</p>
                    </div>

                    <!-- Deleted -->
                    <template v-if="message.deleted_at">
                        <span class="italic text-xs opacity-60">This message was deleted.</span>
                    </template>

                    <!-- Body + attachments -->
                    <template v-else>
                        <p class="whitespace-pre-wrap leading-relaxed">{{ message.body }}</p>
                        <AttachmentPreview
                            v-for="att in message.attachments"
                            :key="att.id"
                            :attachment="att"
                            :download-url="att.download_url"
                        />
                        <!-- Invoice download button -->
                        <a
                            v-if="message.metadata?.download_url"
                            :href="message.metadata.download_url"
                            target="_blank"
                            class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg transition"
                            :class="isOwn
                                ? 'bg-violet-500 text-violet-100 hover:bg-violet-400'
                                : 'bg-violet-50 text-violet-700 hover:bg-violet-100 border border-violet-200'"
                        >
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            View Invoice
                        </a>
                    </template>

                    <!-- Timestamp + read receipt -->
                    <div class="flex items-center justify-end gap-1 mt-1">
                        <span class="text-[10px] opacity-50 leading-none">{{ formatTime(message.created_at) }}</span>
                        <span v-if="message.edited_at" class="text-[10px] opacity-40 leading-none">(edited)</span>
                        <ReadReceiptDisplay
                            v-if="isOwn"
                            :message-id="message.id"
                            :is-own="isOwn"
                            :reads="reads"
                            :members="members"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Reactions -->
        <ReactionPicker
            v-if="!message.deleted_at && message.type !== 'system'"
            :reactions="message.reaction_summary ?? []"
            :message-id="message.id"
            :current-user-id="currentUserId"
            :class="isOwn ? 'mr-1' : 'ml-9'"
            @toggle="$emit('react', $event)"
        />
    </div>
</template>

<script setup>
import AttachmentPreview from './AttachmentPreview.vue';
import ReactionPicker from './ReactionPicker.vue';
import ReadReceiptDisplay from './ReadReceiptDisplay.vue';
import { computed } from 'vue';

const props = defineProps({
    message:          { type: Object,  required: true },
    isOwn:            { type: Boolean, default: false },
    grouped:          { type: Boolean, default: false },
    showSender:       { type: Boolean, default: true },
    currentUserId:    { type: Number,  required: true },
    reads:            { type: Object,  default: () => ({}) },
    members:          { type: Array,   default: () => [] },
    canDeleteAny:     { type: Boolean, default: false },
});

defineEmits(['react', 'reply', 'delete']);

const canDelete = computed(() => props.isOwn || props.canDeleteAny);

const senderInitials = computed(() => {
    const name = props.message.sender?.name ?? props.message.sender_name ?? '?';
    return name.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
});

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>
