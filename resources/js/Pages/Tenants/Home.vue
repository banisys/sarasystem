<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    home: { type: Object, required: true },
    otherTenants: { type: Array, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="home.name" />

    <main class="flex min-h-screen items-center justify-center bg-gray-50 p-6">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm">
            <p class="text-sm text-gray-500">{{ page.props.appName }}</p>
            <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ home.name }}</h1>

            <Link
                :href="user ? route('dashboard') : route('login')"
                class="mt-6 inline-block rounded-lg bg-violet-600 px-5 py-2 text-sm font-medium text-white hover:bg-violet-700"
            >
                {{ user ? 'ورود به داشبورد' : 'ورود' }}
            </Link>

            <nav v-if="otherTenants.length" class="mt-8 border-t border-gray-100 pt-4">
                <p class="text-xs text-gray-400">سایر {{ page.props.tenant.label }}‌ها</p>
                <ul class="mt-2 flex flex-wrap justify-center gap-2">
                    <li v-for="other in otherTenants" :key="other.url">
                        <Link :href="other.url" class="inline-block rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 hover:bg-violet-50 hover:text-violet-700">
                            {{ other.name }}
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    </main>
</template>
