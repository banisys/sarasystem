<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    page: { type: Object, required: true },
});
</script>

<template>
    <Head :title="page.title" />

    <PublicLayout>
        <article class="rounded-2xl bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">{{ page.title }}</h1>
            <!-- Widgets come here (next step). -->
        </article>
    </PublicLayout>
</template>
