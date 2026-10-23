<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);
const impersonator = computed(() => page.props.auth.impersonator);

function logout() {
    router.post(route('logout'));
}

function leaveImpersonation() {
    router.post(route('impersonation.leave'));
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div
            v-if="impersonator"
            class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 bg-amber-500 px-4 py-2 text-sm text-white"
        >
            <span>
                شما ({{ impersonator.name }}) با حساب <strong>{{ user?.name }}</strong> وارد شده‌اید.
            </span>
            <button type="button" class="rounded-md bg-white/20 px-3 py-1 font-medium hover:bg-white/30" @click="leaveImpersonation">
                بازگشت به حساب خودم
            </button>
        </div>

        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('dashboard')" class="font-bold text-gray-900">{{ page.props.appName }}</Link>
                    <span v-if="tenant.current" class="rounded-full bg-violet-50 px-3 py-1 text-sm text-violet-700">
                        {{ tenant.current.name }}
                    </span>
                    <Link
                        v-if="tenant.available.length > 1"
                        :href="route('tenants.select')"
                        class="text-sm text-gray-500 hover:text-gray-800"
                    >
                        تغییر {{ tenant.label }}
                    </Link>
                    <nav v-if="page.props.navigation?.length" class="flex items-center gap-4 text-sm">
                        <Link
                            v-for="link in page.props.navigation"
                            :key="link.url"
                            :href="link.url"
                            class="text-gray-700 hover:text-violet-700"
                        >
                            {{ link.title }}
                        </Link>
                    </nav>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <a v-if="page.props.auth.canAccessAdmin" href="/cpadmin" class="text-violet-600 hover:text-violet-800">
                        پنل مدیریت
                    </a>
                    <Link :href="route('profile.edit')" class="text-gray-700 hover:text-gray-900">{{ user?.name }}</Link>
                    <button type="button" class="text-gray-500 hover:text-gray-800" @click="logout">خروج</button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
