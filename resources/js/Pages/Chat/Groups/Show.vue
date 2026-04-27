<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('chat.groups.index', { tenant: tenant.id })"
                    class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ room.name }}</h2>
                    <p class="text-xs text-gray-500">{{ onlineUsers.length }} online</p>
                </div>
            </div>
        </template>

        <div class="flex h-[calc(100vh-4rem)] overflow-hidden">
            <!-- Room list sidebar -->
            <aside class="w-60 shrink-0 border-r border-gray-200 bg-white overflow-y-auto hidden lg:flex flex-col">
                <div class="px-3 py-2 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Rooms</p>
                </div>
                <ul class="flex-1">
                    <li v-for="r in rooms" :key="r.id">
                        <Link
                            :href="route('chat.groups.show', { tenant: tenant.id, room: r.id })"
                            class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-violet-50 transition"
                            :class="r.id === room.id ? 'bg-violet-50 text-violet-700 font-medium' : 'text-gray-700'"
                        >
                            <span>{{ r.is_system ? '🔔' : '#' }}</span>
                            <span class="truncate">{{ r.name }}</span>
                        </Link>
                    </li>
                </ul>
            </aside>

            <!-- Chat area -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Messages -->
                <div ref="messagesEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-1 bg-gray-50">
                    <div v-if="canLoadMore" class="text-center mb-4">
                        <button @click="loadMore" :disabled="loadingMore"
                            class="text-sm text-violet-600 hover:underline disabled:opacity-50">
                            {{ loadingMore ? 'Loading…' : 'Load earlier messages' }}
                        </button>
                    </div>

                    <MessageBubble
                        v-for="msg in messages"
                        :key="msg.id"
                        :message="msg"
                        :is-own="msg.user_id === authUserId"
                        :show-sender="!room.is_system"
                        :current-user-id="authUserId"
                        :reads="readMap"
                        :members="room.members"
                        @react="toggleReaction"
                        @reply="replyTo = msg"
                    />

                    <TypingIndicator :typing-users="typingUsers" />
                </div>

                <!-- Input -->
                <MessageInput
                    :reply-to="replyTo"
                    @send="sendMessage"
                    @cancel-reply="replyTo = null"
                    @typing="sendTyping"
                />
            </div>

            <!-- Members sidebar -->
            <MembersSidebar
                :members="room.members"
                :online-user-ids="onlineUsers.map(u => u.id)"
                class="hidden xl:flex"
            />
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MessageBubble from '@/Components/Chat/MessageBubble.vue';
import MessageInput from '@/Components/Chat/MessageInput.vue';
import MembersSidebar from '@/Components/Chat/MembersSidebar.vue';
import TypingIndicator from '@/Components/Chat/TypingIndicator.vue';

const props = defineProps({
    tenant:   Object,
    room:     Object,
    messages: Array,
    rooms:    Array,
});

const page         = usePage();
const authUserId   = computed(() => page.props.auth.user.id);
const messagesEl   = ref(null);
const messages     = ref([...props.messages]);
const onlineUsers  = ref([]);
const typingUsers  = ref([]);
const replyTo      = ref(null);
const readMap      = ref({});  // { user_id: last_read_message_id }
const canLoadMore  = ref(props.messages.length === 50);
const loadingMore  = ref(false);
let channel        = null;

// Scroll to bottom
function scrollBottom() {
    nextTick(() => {
        if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    });
}

// Mark latest message as read
function markRead() {
    const last = messages.value.at(-1);
    if (!last) return;
    window.axios.post(route('chat.read', { tenant: props.tenant.id, room: props.room.id }), {
        message_id: last.id,
    }).catch(() => {});
}

// Load older messages
async function loadMore() {
    const oldest = messages.value[0];
    if (!oldest) return;
    loadingMore.value = true;
    try {
        const { data } = await window.axios.get(
            route('chat.messages.index', { tenant: props.tenant.id, room: props.room.id }),
            { params: { before_id: oldest.id } }
        );
        messages.value = [...data.data, ...messages.value];
        canLoadMore.value = data.data.length === 50;
    } finally {
        loadingMore.value = false;
    }
}

// Send a message
async function sendMessage({ body, files, reply_to_message_id }) {
    // Upload attachments first
    const attachmentIds = [];
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        const { data } = await window.axios.post(
            route('chat.attachments.store', { tenant: props.tenant.id, room: props.room.id }),
            fd
        );
        attachmentIds.push(data.data.id);
    }

    const { data } = await window.axios.post(
        route('chat.messages.store', { tenant: props.tenant.id, room: props.room.id }),
        { body, reply_to_message_id, attachment_ids: attachmentIds }
    );

    // Optimistically add if not already present (broadcast may arrive first)
    if (!messages.value.find(m => m.id === data.data.id)) {
        messages.value.push(data.data);
    }
    replyTo.value = null;
    scrollBottom();
    markRead();
}

// Toggle reaction
async function toggleReaction({ message_id, emoji }) {
    const { data } = await window.axios.post(
        route('chat.reactions.toggle', { tenant: props.tenant.id, room: props.room.id, message: message_id }),
        { emoji }
    );
    const msg = messages.value.find(m => m.id === message_id);
    if (msg) msg.reaction_summary = data.reaction_summary;
}

// Typing
let typingTimer = null;
function sendTyping(isTyping) {
    window.axios.post(
        route('chat.typing', { tenant: props.tenant.id, room: props.room.id }),
        { typing: isTyping }
    ).catch(() => {});
}

// Echo handlers
function handleNewMessage(e) {
    const existing = messages.value.findIndex(m => m.id === e.id);
    if (existing >= 0) {
        messages.value[existing] = { ...messages.value[existing], ...e };
    } else {
        messages.value.push(e);
        scrollBottom();
    }
    markRead();
}

function handleTyping(e) {
    if (e.user_id === authUserId.value) return;
    if (e.typing) {
        if (!typingUsers.value.find(u => u.id === e.user_id)) {
            typingUsers.value.push({ id: e.user_id, name: e.user_name });
        }
    } else {
        typingUsers.value = typingUsers.value.filter(u => u.id !== e.user_id);
    }
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => typingUsers.value = [], 5000);
}

function handleReaction(e) {
    const msg = messages.value.find(m => m.id === e.message_id);
    if (!msg) return;
    // Reload reaction summary from the event
    if (e.action === 'added') {
        const existing = msg.reaction_summary?.find(r => r.emoji === e.emoji);
        if (existing) {
            if (!existing.users.includes(e.user_id)) {
                existing.count++;
                existing.users.push(e.user_id);
            }
        } else {
            msg.reaction_summary = [...(msg.reaction_summary ?? []), { emoji: e.emoji, count: 1, users: [e.user_id] }];
        }
    } else {
        const existing = msg.reaction_summary?.find(r => r.emoji === e.emoji);
        if (existing) {
            existing.count = Math.max(0, existing.count - 1);
            existing.users = existing.users.filter(id => id !== e.user_id);
            if (existing.count === 0) {
                msg.reaction_summary = msg.reaction_summary.filter(r => r.emoji !== e.emoji);
            }
        }
    }
}

function handleRead(e) {
    readMap.value = { ...readMap.value, [e.user_id]: e.last_read_message_id };
}

onMounted(() => {
    scrollBottom();
    markRead();

    channel = window.Echo.join(`presence-room.${props.tenant.id}.${props.room.id}`)
        .here((users) => { onlineUsers.value = users; })
        .joining((user) => { onlineUsers.value.push(user); })
        .leaving((user) => { onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id); })
        .listen('.chat.message', handleNewMessage)
        .listen('.chat.typing',  handleTyping)
        .listen('.chat.reaction', handleReaction)
        .listen('.chat.read',    handleRead);
});

onUnmounted(() => {
    window.Echo.leave(`presence-room.${props.tenant.id}.${props.room.id}`);
});
</script>
