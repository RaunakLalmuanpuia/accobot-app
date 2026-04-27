<template>
    <div class="flex flex-col mb-1" :class="isOwn ? 'items-end' : 'items-start'">
        <span v-if="showSender && !isOwn" class="text-xs text-gray-500 mb-0.5 ml-1">{{ message.sender_name }}</span>

        <!-- Reply quote -->
        <div
            v-if="message.reply_to_message_id && message.replyTo"
            class="mb-1 px-2 py-1 rounded-lg border-l-2 border-violet-400 bg-gray-100 text-xs text-gray-500 max-w-xs truncate"
        >
            <span class="font-medium">{{ message.replyTo.sender_name }}:</span>
            {{ message.replyTo.body }}
        </div>

        <!-- Bubble -->
        <div
            class="group relative max-w-xs lg:max-w-md px-3 py-2 rounded-2xl text-sm break-words"
            :class="isOwn
                ? 'bg-violet-600 text-white rounded-br-sm'
                : 'bg-gray-100 text-gray-800 rounded-bl-sm'"
        >
            <!-- System message -->
            <template v-if="message.type === 'system'">
                <span class="italic opacity-75">{{ message.body }}</span>
            </template>

            <!-- Deleted message -->
            <template v-else-if="message.deleted_at">
                <span class="italic opacity-60">This message was deleted.</span>
            </template>

            <!-- Normal body -->
            <template v-else>
                <p class="whitespace-pre-wrap">{{ message.body }}</p>

                <!-- Attachments -->
                <AttachmentPreview
                    v-for="att in message.attachments"
                    :key="att.id"
                    :attachment="att"
                    :download-url="att.download_url"
                />
            </template>

            <!-- Timestamp + read receipt -->
            <div class="flex items-center justify-end gap-1 mt-0.5">
                <span class="text-[10px] opacity-60">{{ formatTime(message.created_at) }}</span>
                <span v-if="message.edited_at" class="text-[10px] opacity-50">(edited)</span>
                <ReadReceiptDisplay
                    v-if="isOwn"
                    :message-id="message.id"
                    :is-own="isOwn"
                    :reads="reads"
                    :members="members"
                />
            </div>
        </div>

        <!-- Reactions -->
        <ReactionPicker
            v-if="message.type !== 'system' && !message.deleted_at"
            :reactions="message.reaction_summary ?? []"
            :message-id="message.id"
            :current-user-id="currentUserId"
            @toggle="$emit('react', $event)"
        />
    </div>
</template>

<script setup>
import AttachmentPreview from './AttachmentPreview.vue';
import ReactionPicker from './ReactionPicker.vue';
import ReadReceiptDisplay from './ReadReceiptDisplay.vue';

defineProps({
    message:       { type: Object,  required: true },
    isOwn:         { type: Boolean, default: false },
    showSender:    { type: Boolean, default: true },
    currentUserId: { type: Number,  required: true },
    reads:         { type: Object,  default: () => ({}) },
    members:       { type: Array,   default: () => [] },
});

defineEmits(['react', 'reply']);

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>
