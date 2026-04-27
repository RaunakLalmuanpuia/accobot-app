<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Group Chat</h2>
                <button
                    v-if="$page.props.auth.permissions?.includes('chat.room.create')"
                    @click="showCreate = true"
                    class="px-3 py-1.5 bg-violet-600 text-white text-sm rounded-lg hover:bg-violet-700 transition"
                >+ New Group</button>
            </div>
        </template>

        <div class="max-w-2xl mx-auto py-6 px-4">
            <div v-if="rooms.length === 0" class="text-center text-gray-400 py-16">
                <p class="text-lg">No groups yet.</p>
                <p class="text-sm mt-1">Create one to start chatting with your team.</p>
            </div>

            <ul class="space-y-2">
                <li
                    v-for="room in sortedRooms"
                    :key="room.id"
                >
                    <Link
                        :href="route('chat.groups.show', { tenant: tenant.id, room: room.id })"
                        class="flex items-center gap-3 px-4 py-3 bg-white rounded-xl border border-gray-200 hover:border-violet-300 hover:shadow-sm transition group"
                    >
                        <!-- Avatar -->
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                            :class="room.is_system ? 'bg-violet-100 text-violet-600' : 'bg-gray-100 text-gray-600'">
                            {{ room.is_system ? '🔔' : room.name[0].toUpperCase() }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-800 truncate">{{ room.name }}</span>
                                <span v-if="room.latest_message" class="text-xs text-gray-400 shrink-0 ml-2">
                                    {{ formatTime(room.latest_message?.created_at) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 truncate">
                                {{ room.latest_message?.body ?? 'No messages yet' }}
                            </p>
                        </div>

                        <!-- Unread badge -->
                        <span
                            v-if="room.unread_count > 0"
                            class="shrink-0 w-5 h-5 bg-violet-600 text-white text-xs rounded-full flex items-center justify-center"
                        >{{ room.unread_count > 9 ? '9+' : room.unread_count }}</span>
                    </Link>
                </li>
            </ul>
        </div>

        <!-- Create room modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold mb-4">New Group</h3>

                <form @submit.prevent="createRoom">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="form.name" type="text" required maxlength="255"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                            <input v-model="form.description" type="text" maxlength="1000"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Members (optional)</label>
                            <div class="border border-gray-300 rounded-lg max-h-40 overflow-y-auto divide-y divide-gray-100">
                                <label
                                    v-for="user in otherUsers"
                                    :key="user.id"
                                    class="flex items-center gap-3 px-3 py-2 hover:bg-violet-50 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :value="user.id"
                                        v-model="form.member_ids"
                                        class="accent-violet-600"
                                    />
                                    <span class="text-sm text-gray-700">{{ user.name }}</span>
                                </label>
                                <p v-if="otherUsers.length === 0" class="px-3 py-2 text-sm text-gray-400">No other members to add.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-5">
                        <button type="button" @click="showCreate = false"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                        <button type="submit" :disabled="form.processing"
                            class="px-4 py-2 bg-violet-600 text-white text-sm rounded-lg hover:bg-violet-700 disabled:opacity-50">
                            Create
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

// Exclude the current user from the member picker (they're added as creator automatically)
const otherUsers = computed(() => props.tenantUsers.filter(u => u.id !== authUserId.value));

const sortedRooms = computed(() => {
    return [...props.rooms].sort((a, b) => {
        if (a.is_system && !b.is_system) return -1;
        if (!a.is_system && b.is_system) return 1;
        return new Date(b.updated_at) - new Date(a.updated_at);
    });
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
    return d.toLocaleDateString([], { day: '2-digit', month: 'short' });
}
</script>
