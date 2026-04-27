<template>
    <aside class="w-56 shrink-0 border-l border-gray-200 bg-white flex flex-col">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Members ({{ members.length }})</h3>
        </div>
        <ul class="flex-1 overflow-y-auto divide-y divide-gray-50">
            <li
                v-for="member in members"
                :key="member.id"
                class="flex items-center gap-3 px-4 py-2.5"
            >
                <div class="relative shrink-0">
                    <div class="w-7 h-7 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-semibold">
                        {{ initials(member.user?.name) }}
                    </div>
                    <span
                        v-if="isOnline(member.user?.id)"
                        class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-400 border-2 border-white rounded-full"
                    ></span>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ member.user?.name }}</p>
                    <p v-if="member.role === 'admin'" class="text-xs text-violet-500">Admin</p>
                </div>
            </li>
        </ul>
    </aside>
</template>

<script setup>
const props = defineProps({
    members:       { type: Array, default: () => [] },
    onlineUserIds: { type: Array, default: () => [] },
});

function initials(name) {
    return (name ?? '?').split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
}

function isOnline(userId) {
    return props.onlineUserIds.includes(userId);
}
</script>
