<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <Head title="خانه" />

    <main class="flex min-h-screen items-center justify-center bg-gray-50 p-6">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">سارا سیستم</h1>
            <p class="mt-2 text-gray-600">سامانه یکپارچه آموزش (LMS، TMS، SIS)</p>

            <p v-if="user" class="mt-6 text-sm text-gray-700">{{ user.name }} خوش آمدید</p>

            <a
                href="/cpadmin"
                class="mt-6 inline-block rounded-lg bg-violet-600 px-5 py-2 text-sm font-medium text-white hover:bg-violet-700"
            >
                ورود به پنل مدیریت
            </a>
        </div>
    </main>
</template>
