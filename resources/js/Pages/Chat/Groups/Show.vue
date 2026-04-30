<template>
    <AuthenticatedLayout>
        <div class="flex h-[calc(100vh-4rem)] overflow-hidden relative">

            <!-- Backdrop (mobile/tablet) -->
            <div
                v-if="showRooms || showMembers"
                class="fixed inset-0 z-20 bg-black/40 xl:hidden"
                @click="showRooms = showMembers = false"
            />

            <!-- ── Left: Rooms sidebar ── -->
            <aside
                class="fixed top-16 bottom-0 left-0 z-30 w-72 bg-white border-r border-gray-200 flex flex-col
                       transform transition-transform duration-200 ease-in-out
                       lg:relative lg:top-auto lg:bottom-auto lg:z-auto lg:w-60 lg:transition-none"
                :class="showRooms ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Rooms</p>
                    <button class="lg:hidden p-1 text-gray-400 hover:text-gray-700 transition" @click="showRooms = false">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <ul class="flex-1 overflow-y-auto py-1">
                    <li v-for="r in sortedRooms" :key="r.id">
                        <Link
                            :href="route('chat.groups.show', { tenant: tenant.id, room: r.id })"
                            class="flex items-center gap-2.5 px-3 py-2.5 transition"
                            :class="r.id === room.id
                                ? 'bg-violet-50 text-violet-700'
                                : 'text-gray-700 hover:bg-gray-50'"
                            @click="showRooms = false"
                        >
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                :class="r.id === room.id ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-500'">
                                {{ r.type === 'notifications' ? '🔔' : r.name[0].toUpperCase() }}
                            </span>
                            <span class="flex-1 text-sm font-medium truncate">{{ r.name }}</span>
                            <span v-if="r.unread_count > 0"
                                class="w-5 h-5 bg-violet-600 text-white text-[10px] font-semibold rounded-full flex items-center justify-center shrink-0">
                                {{ r.unread_count > 9 ? '9+' : r.unread_count }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </aside>

            <!-- ── Center: Chat area ── -->
            <div class="flex-1 flex flex-col min-w-0 bg-white">

                <!-- Chat header bar -->
                <div class="h-14 shrink-0 flex items-center px-3 gap-2.5 border-b border-gray-200 bg-white">

                    <!-- Mobile: hamburger -->
                    <button
                        class="lg:hidden p-1.5 -ml-0.5 text-gray-500 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition"
                        @click="showRooms = true"
                        title="Rooms"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Room avatar -->
                    <div class="w-9 h-9 rounded-full shrink-0 flex items-center justify-center text-sm font-bold"
                        :class="room.is_system ? 'bg-violet-100 text-violet-600' : 'bg-violet-600 text-white'">
                        {{ room.type === 'notifications' ? '🔔' : room.name[0].toUpperCase() }}
                    </div>

                    <!-- Room name + status -->
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800 text-sm leading-tight truncate">{{ room.name }}</p>
                        <p class="text-xs text-gray-400 leading-tight flex items-center gap-1.5">
                            <span class="inline-flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                {{ onlineUsers.length }} online
                            </span>
                            <span class="text-gray-300">·</span>
                            <span>{{ currentRoom.members.length }} members</span>
                        </p>
                    </div>

                    <!-- Members toggle -->
                    <button
                        @click="showMembers = !showMembers"
                        class="p-2 rounded-lg transition text-gray-400 hover:text-violet-600 hover:bg-violet-50"
                        :class="showMembers ? 'bg-violet-50 text-violet-600' : ''"
                        title="Toggle members"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>

                <!-- Messages -->
                <div ref="messagesEl" class="flex-1 overflow-y-auto px-3 sm:px-5 py-4 bg-gray-50">

                    <div v-if="canLoadMore" class="text-center mb-4">
                        <button @click="loadMore" :disabled="loadingMore"
                            class="text-xs font-medium text-violet-600 hover:text-violet-800 px-4 py-1.5 rounded-full bg-white border border-violet-200 hover:border-violet-400 transition disabled:opacity-50">
                            {{ loadingMore ? 'Loading…' : '↑ Load earlier messages' }}
                        </button>
                    </div>

                    <template v-for="item in processedMessages" :key="item._key ?? item.id">

                        <!-- Date separator -->
                        <div v-if="item._sep" class="flex items-center gap-3 my-5">
                            <div class="flex-1 h-px bg-gray-200"></div>
                            <span class="text-xs font-medium text-gray-400 px-3 py-1 rounded-full bg-white border border-gray-200 shrink-0">
                                {{ formatDate(item.date) }}
                            </span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>

                        <!-- Message bubble -->
                        <MessageBubble
                            v-else
                            :message="item"
                            :is-own="item.user_id === authUserId"
                            :grouped="item._grouped"
                            :show-sender="!room.is_system"
                            :current-user-id="authUserId"
                            :reads="readMap"
                            :members="room.members"
                            :can-delete-any="canDeleteAnyMsg"
                            @react="toggleReaction"
                            @reply="replyTo = item"
                            @delete="deleteMessage"
                        />

                    </template>

                    <TypingIndicator :typing-users="typingUsers" />
                </div>

                <!-- Message input -->
                <MessageInput
                    :reply-to="replyTo"
                    @send="sendMessage"
                    @cancel-reply="replyTo = null"
                    @typing="sendTyping"
                />
            </div>

            <!-- ── Right: Members sidebar ── -->
            <aside
                class="fixed top-16 bottom-0 right-0 z-30 w-72 bg-white border-l border-gray-200 flex flex-col
                       transform transition-transform duration-200 ease-in-out
                       xl:relative xl:top-auto xl:bottom-auto xl:z-auto xl:w-64 xl:transition-none"
                :class="showMembers ? 'translate-x-0' : 'translate-x-full xl:translate-x-0'"
            >
                <MembersSidebar
                    :members="currentRoom.members"
                    :online-user-ids="onlineUsers.map(u => u.id)"
                    :tenant-users="tenantUsers"
                    :can-manage="canManage && (!room.is_system || room.type === 'group')"
                    :current-user-id="authUserId"
                    @add="addMember"
                    @remove="removeMember"
                    @close="showMembers = false"
                />
            </aside>

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
    tenant:      Object,
    room:        Object,
    messages:    Array,
    rooms:       Array,
    tenantUsers: Array,
});

const page        = usePage();
const authUserId  = computed(() => page.props.auth.user.id);
const canManage       = computed(() => page.props.auth.permissions?.includes('chat.room.manage'));
const canDeleteAnyMsg = computed(() => page.props.auth.permissions?.includes('chat.message.delete'));

const messagesEl   = ref(null);
const messages     = ref([...props.messages]);
const currentRoom  = ref({ ...props.room, members: [...props.room.members] });
const onlineUsers  = ref([]);
const typingUsers  = ref([]);
const replyTo      = ref(null);
const readMap      = ref({});
const canLoadMore  = ref(props.messages.length === 50);
const loadingMore  = ref(false);
const showRooms    = ref(false);
const showMembers  = ref(false);
let channel = null;

// Sort rooms: system first, then by latest activity
const sortedRooms = computed(() =>
    [...props.rooms].sort((a, b) => {
        if (a.is_system && !b.is_system) return -1;
        if (!a.is_system && b.is_system) return 1;
        return new Date(b.updated_at) - new Date(a.updated_at);
    })
);

// Messages with date separators + consecutive grouping metadata
const processedMessages = computed(() => {
    const result = [];
    let lastDateStr = '';
    let lastUserId = null;
    let lastTime = 0;

    for (const msg of messages.value) {
        const d = new Date(msg.created_at);
        const dateStr = d.toDateString();

        if (dateStr !== lastDateStr) {
            result.push({ _sep: true, date: msg.created_at, _key: 'sep-' + dateStr });
            lastDateStr = dateStr;
            lastUserId = null;
            lastTime = 0;
        }

        const grouped = msg.type !== 'system'
            && lastUserId === msg.user_id
            && (d.getTime() - lastTime) < 5 * 60 * 1000;

        result.push({ ...msg, _grouped: grouped });

        if (msg.type !== 'system') {
            lastUserId = msg.user_id;
            lastTime = d.getTime();
        }
    }
    return result;
});

function scrollBottom(smooth = false) {
    nextTick(() => {
        if (!messagesEl.value) return;
        messagesEl.value.scrollTo({ top: messagesEl.value.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
    });
}

function markRead() {
    const last = messages.value.at(-1);
    if (!last) return;
    window.axios.post(route('chat.read', { tenant: props.tenant.id, room: props.room.id }), {
        message_id: last.id,
    }).catch(() => {});
}

async function loadMore() {
    const oldest = messages.value[0];
    if (!oldest) return;
    loadingMore.value = true;
    const prevScrollHeight = messagesEl.value?.scrollHeight ?? 0;
    try {
        const { data } = await window.axios.get(
            route('chat.messages.index', { tenant: props.tenant.id, room: props.room.id }),
            { params: { before_id: oldest.id } }
        );
        messages.value = [...data.data, ...messages.value];
        canLoadMore.value = data.data.length === 50;
        nextTick(() => {
            if (messagesEl.value) {
                messagesEl.value.scrollTop = messagesEl.value.scrollHeight - prevScrollHeight;
            }
        });
    } finally {
        loadingMore.value = false;
    }
}

async function sendMessage({ body, files, reply_to_message_id }) {
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
    if (!messages.value.find(m => m.id === data.data.id)) {
        messages.value.push(data.data);
    }
    replyTo.value = null;
    scrollBottom(true);
    markRead();
}

async function addMember(userId) {
    await window.axios.post(
        route('chat.groups.members.store', { tenant: props.tenant.id, room: currentRoom.value.id }),
        { user_id: userId }
    );
    const user = props.tenantUsers.find(u => u.id === userId);
    if (user) currentRoom.value.members.push({ user, role: 'member' });
}

async function removeMember(userId) {
    await window.axios.delete(
        route('chat.groups.members.destroy', { tenant: props.tenant.id, room: currentRoom.value.id, user: userId })
    );
    currentRoom.value.members = currentRoom.value.members.filter(m => m.user?.id !== userId);
}

async function deleteMessage(message) {
    if (!confirm('Delete this message?')) return;
    await window.axios.delete(
        route('chat.messages.destroy', { tenant: props.tenant.id, room: props.room.id, message: message.id })
    );
    const idx = messages.value.findIndex(m => m.id === message.id);
    if (idx >= 0) messages.value[idx] = { ...messages.value[idx], body: null, deleted_at: new Date().toISOString() };
}

async function toggleReaction({ message_id, emoji }) {
    const { data } = await window.axios.post(
        route('chat.reactions.toggle', { tenant: props.tenant.id, room: props.room.id, message: message_id }),
        { emoji }
    );
    const msg = messages.value.find(m => m.id === message_id);
    if (msg) msg.reaction_summary = data.reaction_summary;
}

let typingTimer = null;
function sendTyping(isTyping) {
    window.axios.post(
        route('chat.typing', { tenant: props.tenant.id, room: props.room.id }),
        { typing: isTyping }
    ).catch(() => {});
}

function handleNewMessage(e) {
    const existing = messages.value.findIndex(m => m.id === e.id);
    if (existing >= 0) {
        messages.value[existing] = { ...messages.value[existing], ...e };
    } else {
        messages.value.push(e);
        scrollBottom(true);
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
    if (e.action === 'added') {
        const existing = msg.reaction_summary?.find(r => r.emoji === e.emoji);
        if (existing) {
            if (!existing.users.includes(e.user_id)) { existing.count++; existing.users.push(e.user_id); }
        } else {
            msg.reaction_summary = [...(msg.reaction_summary ?? []), { emoji: e.emoji, count: 1, users: [e.user_id] }];
        }
    } else {
        const existing = msg.reaction_summary?.find(r => r.emoji === e.emoji);
        if (existing) {
            existing.count = Math.max(0, existing.count - 1);
            existing.users = existing.users.filter(id => id !== e.user_id);
            if (existing.count === 0) msg.reaction_summary = msg.reaction_summary.filter(r => r.emoji !== e.emoji);
        }
    }
}

function handleRead(e) {
    readMap.value = { ...readMap.value, [e.user_id]: e.last_read_message_id };
}

function formatDate(iso) {
    const d = new Date(iso);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
    const msgDay = new Date(d); msgDay.setHours(0, 0, 0, 0);
    if (msgDay.getTime() === today.getTime()) return 'Today';
    if (msgDay.getTime() === yesterday.getTime()) return 'Yesterday';
    return d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });
}

onMounted(() => {
    scrollBottom();
    markRead();
    channel = window.Echo.join(`presence-room.${props.tenant.id}.${currentRoom.value.id}`)
        .here((users) => { onlineUsers.value = users; })
        .joining((user) => { onlineUsers.value.push(user); })
        .leaving((user) => { onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id); })
        .listen('.chat.message', handleNewMessage)
        .listen('.chat.typing',  handleTyping)
        .listen('.chat.reaction', handleReaction)
        .listen('.chat.read',    handleRead);
});

onUnmounted(() => {
    window.Echo.leave(`presence-room.${props.tenant.id}.${currentRoom.value.id}`);
});
</script>
