<template>
    <div class="flex flex-wrap items-center gap-1 mt-1">
        <button
            v-for="r in reactions"
            :key="r.emoji"
            @click="$emit('toggle', { message_id: messageId, emoji: r.emoji })"
            class="flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs border transition"
            :class="r.users.includes(currentUserId)
                ? 'bg-violet-100 border-violet-400 text-violet-700'
                : 'bg-white border-gray-200 text-gray-600 hover:border-violet-300'"
        >
            <span>{{ r.emoji }}</span>
            <span>{{ r.count }}</span>
        </button>

        <div class="relative" v-if="showPicker || true">
            <button
                @click.stop="open = !open"
                class="px-1.5 py-0.5 rounded-full text-xs border border-gray-200 text-gray-400 hover:border-violet-300 transition"
            >+</button>
            <div
                v-if="open"
                v-click-outside="() => open = false"
                class="absolute bottom-full left-0 mb-1 z-10 bg-white border border-gray-200 rounded-xl shadow-lg p-2 grid grid-cols-5 gap-1 w-44"
            >
                <button
                    v-for="emoji in EMOJIS"
                    :key="emoji"
                    @click="pick(emoji)"
                    class="text-lg hover:bg-gray-100 rounded p-0.5 transition"
                >{{ emoji }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const EMOJIS = ['👍','👎','❤️','😂','😮','😢','😡','🎉','🙌','🔥','👏','💯','🤔','😊','😍','🙏','💪','😎','🤝','✅'];

const props = defineProps({
    reactions:     { type: Array,  default: () => [] },
    messageId:     { type: String, required: true },
    currentUserId: { type: Number, required: true },
    showPicker:    { type: Boolean, default: true },
});

const emit = defineEmits(['toggle']);
const open = ref(false);

function pick(emoji) {
    open.value = false;
    emit('toggle', { message_id: props.messageId, emoji });
}

const vClickOutside = {
    mounted(el, binding) {
        el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value(e); };
        document.addEventListener('click', el._clickOutside);
    },
    unmounted(el) { document.removeEventListener('click', el._clickOutside); },
};
</script>
