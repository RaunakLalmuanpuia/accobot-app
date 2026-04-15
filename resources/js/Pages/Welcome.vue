<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Welcome" />
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <div
            class="relative flex min-h-screen flex-col items-center justify-center selection:bg-violet-500 selection:text-white"
        >
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header
                    class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3"
                >
                    <div class="flex lg:col-start-2 lg:justify-center">
                        <ApplicationLogo class="h-16 w-auto fill-current text-gray-700 dark:text-gray-200" />
                    </div>
                    <nav v-if="canLogin" class="-mx-3 flex flex-1 justify-end">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="$page.props.auth.is_admin
                                ? route('admin.dashboard')
                                : $page.props.auth.current_tenant_id
                                    ? route('dashboard', { tenant: $page.props.auth.current_tenant_id })
                                    : route('profile.edit')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-violet-500 dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-violet-500 dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-violet-500 dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Register
                            </Link>
                        </template>
                    </nav>
                </header>

                <main class="mt-6">
                    <div class="text-center py-16">
                        <h1 class="text-4xl font-bold text-gray-900 dark:text-white sm:text-5xl">
                            Welcome to Accobot
                        </h1>
                        <p class="mt-6 text-lg text-gray-600 dark:text-gray-400">
                            Manage your business, clients, and team — all in one place.
                        </p>
                        <div v-if="canLogin && !$page.props.auth.user" class="mt-10 flex items-center justify-center gap-4">
                            <Link
                                :href="route('login')"
                                class="rounded-md bg-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-violet-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                            >
                                Log in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md px-6 py-3 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 dark:text-white dark:ring-gray-700 dark:hover:bg-gray-800"
                            >
                                Register
                            </Link>
                        </div>
                    </div>
                </main>

                <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                    &copy; {{ new Date().getFullYear() }} Accobot. All rights reserved.
                </footer>
            </div>
        </div>
    </div>
</template>
