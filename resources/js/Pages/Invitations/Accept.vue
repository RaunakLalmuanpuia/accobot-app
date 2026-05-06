<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
    invitation: Object,
    error: String,
})

const isCaClientInvite = props.invitation?.invitation_type === 'ca_client'

const form = useForm({
    name:                  '',
    password:              '',
    password_confirmation: '',
    business_name:         props.invitation?.suggested_business_name ?? '',
})

function accept() {
    form.post(route('invitation.accept', props.invitation.token))
}
</script>

<template>
    <GuestLayout>
        <Head title="Accept Invitation" />

        <!-- Error state -->
        <div v-if="error" class="text-center py-4">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-2xl mb-3">
                🔒
            </div>
            <h1 class="text-lg font-semibold text-gray-900">Invitation unavailable</h1>
            <p class="mt-2 text-sm text-gray-500">{{ error }}</p>
        </div>

        <!-- Invitation content -->
        <template v-else>

            <!-- Icon + heading -->
            <div class="text-center mb-6">
                <div class="inline-flex h-14 w-14 items-center justify-center rounded-full mb-3" :class="isCaClientInvite ? 'bg-violet-100 text-2xl' : 'bg-violet-100 text-2xl'">
                    {{ isCaClientInvite ? '📊' : '🏢' }}
                </div>
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ isCaClientInvite ? 'Connect with your CA' : "You've been invited" }}
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    <template v-if="isCaClientInvite">
                        <strong>{{ invitation.tenant_name }}</strong> wants to manage your books
                    </template>
                    <template v-else>
                        {{ invitation.invited_by }} invited you to join
                    </template>
                </p>
            </div>

            <!-- Details card -->
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3 mb-6">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">{{ isCaClientInvite ? 'CA Firm' : 'Business' }}</span>
                    <span class="font-medium text-gray-900">{{ invitation.tenant_name }}</span>
                </div>
                <div v-if="!isCaClientInvite" class="flex justify-between text-sm">
                    <span class="text-gray-500">Role</span>
                    <span class="font-medium text-gray-900 capitalize">{{ invitation.role_name }}</span>
                </div>
                <div v-if="isCaClientInvite" class="flex justify-between text-sm">
                    <span class="text-gray-500">Invited by</span>
                    <span class="font-medium text-gray-900">{{ invitation.invited_by }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Email</span>
                    <span class="font-medium text-gray-900">{{ invitation.email }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Expires</span>
                    <span class="font-medium text-gray-900">{{ invitation.expires_at }}</span>
                </div>
            </div>

            <!-- CA client notice -->
            <div v-if="isCaClientInvite" class="rounded-lg bg-violet-50 border border-violet-100 px-4 py-3 mb-5 text-sm text-violet-700">
                Once connected, your CA will have read/write access to your business's books. You remain the owner of your data and can disconnect at any time.
            </div>

            <!-- New user: account setup -->
            <template v-if="invitation.requires_signup">
                <p class="text-sm text-gray-500 mb-4">
                    No account found for this email. Set up your account to continue.
                </p>

                <div class="space-y-4 mb-5">
                    <div>
                        <InputLabel for="name" value="Your Name" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <!-- Business name field for CA client invites only -->
                    <div v-if="isCaClientInvite">
                        <InputLabel for="business_name" value="Your Business Name" />
                        <TextInput
                            id="business_name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.business_name"
                            placeholder="e.g. Priya Textiles Pvt Ltd"
                            autocomplete="organization"
                        />
                        <InputError class="mt-1" :message="form.errors.business_name" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" />
                        <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required autocomplete="new-password" />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" required autocomplete="new-password" />
                        <InputError class="mt-1" :message="form.errors.password_confirmation" />
                    </div>
                </div>

                <button
                    @click="accept"
                    :disabled="form.processing"
                    :class="['w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 transition', { 'opacity-50 cursor-not-allowed': form.processing }]"
                >
                    {{ isCaClientInvite ? 'Create Account & Connect CA' : 'Create Account & Join' }}
                </button>
            </template>

            <!-- Existing user: just accept -->
            <template v-else>
                <button
                    @click="accept"
                    :disabled="form.processing"
                    :class="['w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 transition', { 'opacity-50 cursor-not-allowed': form.processing }]"
                >
                    {{ isCaClientInvite ? 'Accept & Connect ' + invitation.tenant_name : 'Accept & Join ' + invitation.tenant_name }}
                </button>
            </template>

        </template>
    </GuestLayout>
</template>
