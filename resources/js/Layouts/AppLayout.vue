<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
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
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <a v-if="page.props.auth.canAccessAdmin" href="/cpadmin" class="text-violet-600 hover:text-violet-800">
                        پنل مدیریت
                    </a>
                    <span class="text-gray-700">{{ user?.name }}</span>
                    <button type="button" class="text-gray-500 hover:text-gray-800" @click="logout">خروج</button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
