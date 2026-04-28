<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Group Chat</h2>
                <button
                    v-if="$page.props.auth.permissions?.includes('chat.room.create')"
                    @click="showCreate = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Group
                </button>
            </div>
        </template>

        <div class="max-w-2xl mx-auto py-6 px-4">

            <!-- Search -->
            <div class="relative mb-4">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search rooms…"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent"
                />
            </div>

            <!-- Empty state -->
            <div v-if="rooms.length === 0" class="text-center py-20">
                <div class="w-16 h-16 rounded-2xl bg-violet-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                    </svg>
                </div>
                <p class="text-gray-700 font-medium">No groups yet</p>
                <p class="text-sm text-gray-400 mt-1">Create one to start chatting with your team.</p>
            </div>

            <!-- No search results -->
            <div v-else-if="filteredRooms.length === 0" class="text-center py-10 text-gray-400 text-sm">
                No rooms match "{{ search }}"
            </div>

            <!-- Room list -->
            <ul v-else class="space-y-1.5">
                <li v-for="room in filteredRooms" :key="room.id">
                    <Link
                        :href="route('chat.groups.show', { tenant: tenant.id, room: room.id })"
                        class="flex items-center gap-3 px-4 py-3 bg-white rounded-xl border border-gray-200 hover:border-violet-300 hover:shadow-sm transition"
                    >
                        <!-- Avatar -->
                        <div class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                            :class="room.is_system ? 'bg-violet-100 text-violet-600' : 'bg-violet-600 text-white'">
                            {{ room.is_system ? '🔔' : room.name[0].toUpperCase() }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-gray-800 truncate text-sm">{{ room.name }}</span>
                                <span v-if="room.latest_message" class="text-xs text-gray-400 shrink-0">
                                    {{ formatTime(room.latest_message?.created_at) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 truncate mt-0.5">
                                {{ room.latest_message?.body ?? room.description ?? 'No messages yet' }}
                            </p>
                        </div>

                        <!-- Unread badge -->
                        <span
                            v-if="room.unread_count > 0"
                            class="shrink-0 min-w-[1.25rem] h-5 px-1 bg-violet-600 text-white text-[10px] font-semibold rounded-full flex items-center justify-center"
                        >{{ room.unread_count > 99 ? '99+' : room.unread_count }}</span>
                    </Link>
                </li>
            </ul>
        </div>

        <!-- Create room modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                <!-- Modal header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">New Group</h3>
                    <button @click="showCreate = false" class="p-1 text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="createRoom" class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Name</label>
                            <input v-model="form.name" type="text" required maxlength="255"
                                class="w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-gray-400 font-normal">(optional)</span></label>
                            <input v-model="form.description" type="text" maxlength="1000"
                                class="w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Members <span class="text-gray-400 font-normal">(optional)</span></label>
                            <div class="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100 max-h-44 overflow-y-auto">
                                <label
                                    v-for="user in otherUsers"
                                    :key="user.id"
                                    class="flex items-center gap-3 px-3.5 py-2.5 hover:bg-violet-50 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :value="user.id"
                                        v-model="form.member_ids"
                                        class="accent-violet-600 w-4 h-4 shrink-0"
                                    />
                                    <div class="w-7 h-7 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-semibold shrink-0">
                                        {{ user.name[0].toUpperCase() }}
                                    </div>
                                    <span class="text-sm text-gray-700">{{ user.name }}</span>
                                </label>
                                <p v-if="otherUsers.length === 0" class="px-3.5 py-3 text-sm text-gray-400">No other members to add.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-6">
                        <button type="button" @click="showCreate = false"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 px-4 py-2.5 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 disabled:opacity-50 transition">
                            Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    tenant:      Object,
    rooms:       Array,
    tenantUsers: Array,
});

const page        = usePage();
const authUserId  = computed(() => page.props.auth.user.id);
const showCreate  = ref(false);
const search      = ref('');

const otherUsers = computed(() => props.tenantUsers.filter(u => u.id !== authUserId.value));

const sortedRooms = computed(() =>
    [...props.rooms].sort((a, b) => {
        if (a.is_system && !b.is_system) return -1;
        if (!a.is_system && b.is_system) return 1;
        return new Date(b.updated_at) - new Date(a.updated_at);
    })
);

const filteredRooms = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q ? sortedRooms.value.filter(r => r.name.toLowerCase().includes(q)) : sortedRooms.value;
});

const form = useForm({ name: '', description: '', member_ids: [] });

function createRoom() {
    form.post(route('chat.groups.store', { tenant: props.tenant.id }), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    const yesterday = new Date(now); yesterday.setDate(now.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { day: '2-digit', month: 'short' });
}
</script>
