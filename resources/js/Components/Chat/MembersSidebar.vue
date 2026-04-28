<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Members ({{ members.length }})</h3>
            <div class="flex items-center gap-1">
                <button
                    v-if="canManage && addableUsers.length"
                    @click="showAdd = true"
                    class="p-1.5 text-gray-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition"
                    title="Add member"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
                <button
                    @click="$emit('close')"
                    class="xl:hidden p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-lg transition"
                    title="Close"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Member list -->
        <ul class="flex-1 overflow-y-auto py-1">
            <li
                v-for="member in members"
                :key="member.id"
                class="flex items-center gap-3 px-4 py-2.5 group hover:bg-gray-50 transition"
            >
                <div class="relative shrink-0">
                    <div class="w-8 h-8 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-semibold">
                        {{ initials(member.user?.name) }}
                    </div>
                    <span
                        v-if="isOnline(member.user?.id)"
                        class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-400 border-2 border-white rounded-full"
                    ></span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-800 truncate leading-tight">{{ member.user?.name }}</p>
                    <p class="text-xs leading-tight" :class="member.role === 'admin' ? 'text-violet-500' : 'text-gray-400'">
                        {{ member.role === 'admin' ? 'Admin' : (isOnline(member.user?.id) ? 'Online' : 'Offline') }}
                    </p>
                </div>
                <button
                    v-if="canManage && member.user?.id !== currentUserId"
                    @click="$emit('remove', member.user?.id)"
                    class="hidden group-hover:flex text-gray-300 hover:text-red-400 transition shrink-0 p-1 rounded"
                    title="Remove member"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </li>
        </ul>

        <!-- Add member modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showAdd = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xs">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-800">Add Member</h4>
                    <button @click="showAdd = false" class="p-1 text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search members…"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent mb-3"
                    />

                    <ul class="max-h-52 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-xl overflow-hidden">
                        <li
                            v-for="user in filteredAddable"
                            :key="user.id"
                            class="flex items-center gap-3 px-3 py-2.5 hover:bg-violet-50 cursor-pointer transition"
                            @click="add(user)"
                        >
                            <div class="w-7 h-7 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-semibold shrink-0">
                                {{ initials(user.name) }}
                            </div>
                            <span class="text-sm text-gray-700 truncate">{{ user.name }}</span>
                        </li>
                        <li v-if="filteredAddable.length === 0" class="px-3 py-3 text-sm text-gray-400 text-center">
                            No users found.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    members:       { type: Array,   default: () => [] },
    onlineUserIds: { type: Array,   default: () => [] },
    tenantUsers:   { type: Array,   default: () => [] },
    canManage:     { type: Boolean, default: false },
    currentUserId: { type: Number,  default: null },
});

const emit = defineEmits(['add', 'remove', 'close']);

const showAdd = ref(false);
const search  = ref('');

const memberUserIds   = computed(() => new Set(props.members.map(m => m.user?.id)));
const addableUsers    = computed(() => props.tenantUsers.filter(u => !memberUserIds.value.has(u.id)));
const filteredAddable = computed(() => {
    const q = search.value.toLowerCase();
    return q ? addableUsers.value.filter(u => u.name.toLowerCase().includes(q)) : addableUsers.value;
});

function add(user) {
    emit('add', user.id);
    showAdd.value = false;
    search.value  = '';
}

function initials(name) {
    return (name ?? '?').split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
}

function isOnline(userId) {
    return props.onlineUserIds.includes(userId);
}
</script>
