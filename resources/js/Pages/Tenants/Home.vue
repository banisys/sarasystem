<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

// Default home page of a tenant that has no "/" page of its own (ADR 0010).
const page = usePage();
</script>

<template>
    <Head :title="page.props.site.tenant.name" />

    <PublicLayout>
        <div class="rounded-2xl bg-white p-8 text-center shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">{{ page.props.site.tenant.name }}</h1>
            <p class="mt-2 text-gray-600">به {{ page.props.appName }} خوش آمدید.</p>
        </div>
    </PublicLayout>
</template>
