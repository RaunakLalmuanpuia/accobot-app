<script setup>
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { hasPermission } from '@/utils/permissions'

const props = defineProps({
    members: Array,
    pendingInvitations: Array,
    roles: Array,
})

const page = usePage()
const business = page.props.auth.current_business_id
const currentUserId = page.props.auth.user.id

// ── Initials avatar ────────────────────────────────────────────
function initials(name) {
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}

const avatarColors = [
    'bg-violet-500', 'bg-indigo-500', 'bg-sky-500',
    'bg-teal-500',   'bg-emerald-500','bg-amber-500',
    'bg-rose-500',   'bg-pink-500',
]

function avatarColor(id) {
    return avatarColors[id % avatarColors.length]
}

// ── Role badge colour ──────────────────────────────────────────
const roleBadgeClass = {
    admin:  'bg-red-100 text-red-700',
    owner:  'bg-amber-100 text-amber-700',
    ca:     'bg-indigo-100 text-indigo-700',
    user:   'bg-gray-100 text-gray-600',
}

function badgeClass(roleName) {
    return roleBadgeClass[roleName] ?? 'bg-gray-100 text-gray-600'
}

// ── Change role ────────────────────────────────────────────────
function changeRole(member, roleId) {
    router.put(route('team.update', { business, user: member.id }), { role_id: roleId })
}

// ── Remove member ──────────────────────────────────────────────
const confirmRemove = ref(null)

function removeMember(member) {
    router.delete(route('team.destroy', { business, user: member.id }), {
        onSuccess: () => (confirmRemove.value = null),
    })
}

// ── Invite ─────────────────────────────────────────────────────
const showInviteModal = ref(false)

const inviteForm = useForm({
    email: '',
    role_id: '',
})

function sendInvite() {
    inviteForm.post(route('invitation.store', { business }), {
        onSuccess: () => {
            showInviteModal.value = false
            inviteForm.reset()
        },
    })
}

// ── Revoke pending invite ──────────────────────────────────────
function revokeInvitation(invitation) {
    router.delete(route('invitation.destroy', { business, invitation: invitation.id }))
}

const canManage = hasPermission('assign roles')
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Team Members</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ members.length }} member{{ members.length !== 1 ? 's' : '' }} in this business
                    </p>
                </div>

                <button
                    v-if="canManage"
                    @click="showInviteModal = true"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Invite Member
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Members table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="grid grid-cols-12 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <div class="col-span-5">Member</div>
                        <div class="col-span-4">Role</div>
                        <div class="col-span-3 text-right">Actions</div>
                    </div>

                    <div
                        v-for="member in members"
                        :key="member.id"
                        class="grid grid-cols-12 items-center px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition"
                    >
                        <div class="col-span-5 flex items-center gap-3 min-w-0">
                            <div :class="['h-9 w-9 rounded-full flex items-center justify-center text-white text-sm font-semibold shrink-0', avatarColor(member.id)]">
                                {{ initials(member.name) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ member.name }}
                                    <span v-if="member.id === currentUserId" class="ml-1 text-xs text-gray-400">(you)</span>
                                </p>
                                <p class="text-xs text-gray-400 truncate">{{ member.email }}</p>
                            </div>
                        </div>

                        <div class="col-span-4">
                            <template v-if="canManage && member.id !== currentUserId">
                                <select
                                    :value="member.role?.id"
                                    @change="changeRole(member, $event.target.value)"
                                    class="text-sm rounded-lg border border-gray-200 bg-white px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 capitalize"
                                >
                                    <option value="" disabled>No role</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">
                                        {{ role.name }}
                                    </option>
                                </select>
                            </template>
                            <template v-else>
                                <span
                                    v-if="member.role"
                                    :class="['inline-block text-xs font-medium px-2.5 py-1 rounded-full capitalize', badgeClass(member.role.name)]"
                                >{{ member.role.name }}</span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </template>
                        </div>

                        <div class="col-span-3 flex justify-end">
                            <button
                                v-if="canManage && member.id !== currentUserId"
                                @click="confirmRemove = member"
                                class="text-sm text-red-500 hover:text-red-700 font-medium transition"
                            >Remove</button>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </div>
                    </div>

                    <p v-if="!members.length" class="text-center text-gray-400 py-12 text-sm">
                        No members yet.
                    </p>
                </div>

                <!-- Pending invitations -->
                <div v-if="pendingInvitations.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-700">Pending Invitations</h2>
                    </div>

                    <div
                        v-for="inv in pendingInvitations"
                        :key="inv.id"
                        class="flex items-center justify-between px-6 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ inv.email }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Invited as <span class="capitalize font-medium">{{ inv.role_name }}</span>
                                &middot; expires {{ inv.expires_at }}
                            </p>
                        </div>
                        <button
                            v-if="canManage"
                            @click="revokeInvitation(inv)"
                            class="text-xs text-red-500 hover:text-red-700 font-medium transition"
                        >Revoke</button>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Invite modal ────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showInviteModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-5">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Invite a member</h3>
                        <p class="text-sm text-gray-500 mt-0.5">They'll receive an email with a link to join.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                            <input
                                v-model="inviteForm.email"
                                type="email"
                                placeholder="colleague@example.com"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p v-if="inviteForm.errors.email" class="mt-1 text-xs text-red-500">{{ inviteForm.errors.email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select
                                v-model="inviteForm.role_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 capitalize"
                            >
                                <option value="" disabled>Select a role</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </select>
                            <p v-if="inviteForm.errors.role_id" class="mt-1 text-xs text-red-500">{{ inviteForm.errors.role_id }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button
                            @click="showInviteModal = false; inviteForm.reset()"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50"
                        >Cancel</button>
                        <button
                            @click="sendInvite"
                            :disabled="inviteForm.processing"
                            :class="['rounded-lg px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition', { 'opacity-50 cursor-not-allowed': inviteForm.processing }]"
                        >Send Invite</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Remove confirm dialog ───────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="confirmRemove"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                            <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Remove {{ confirmRemove.name }}?</p>
                            <p class="text-sm text-gray-500 mt-0.5">They will lose access to this business.</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button
                            @click="confirmRemove = null"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50"
                        >Cancel</button>
                        <button
                            @click="removeMember(confirmRemove)"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                        >Remove</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
