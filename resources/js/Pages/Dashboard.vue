<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant.current);
</script>

<template>
    <Head title="داشبورد" />

    <AppLayout>
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h1 class="text-xl font-bold text-gray-900">{{ user.name }} خوش آمدید</h1>
            <p class="mt-2 text-gray-600">{{ tenant.name }}</p>
        </div>
    </AppLayout>
</template>
