<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

// Default home page of a tenant that has no "/" page of its own (ADR 0010).
const page = usePage();
</script>

<template>
    <Head :title="page.props.site.tenant.name" />

    <PublicLayout>
        <div class="rounded-ui border border-ui-line bg-brand-50 px-6 py-16 text-center sm:py-24">
            <h1 class="text-3xl font-extrabold text-ui-ink sm:text-5xl">{{ page.props.site.tenant.name }}</h1>
            <p class="mt-4 text-ui-muted">به {{ page.props.appName }} خوش آمدید.</p>
        </div>
    </PublicLayout>
</template>
