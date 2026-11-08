<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import AppLayout from '../Layouts/AppLayout.vue';

// Shown only when no module offers the panel's home (ADR 0069).
const page = usePage();
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant.current);
</script>

<template>
    <Head title="داشبورد" />

    <AppLayout>
        <div class="space-y-6">
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h1 class="text-xl font-bold text-gray-900">{{ user.name }} خوش آمدید</h1>
                <p class="mt-2 text-gray-600">{{ tenant.name }}</p>
            </div>

            <div v-if="page.props.navigation?.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="link in page.props.navigation"
                    :key="link.url"
                    :href="link.url"
                    class="flex items-center gap-3 rounded-2xl bg-white p-5 shadow-sm transition hover:shadow-md"
                >
                    <span class="rounded-xl bg-violet-50 p-2.5 text-violet-700">
                        <PanelIcon :name="link.icon" class="size-6" />
                    </span>
                    <span class="font-medium text-gray-900">{{ link.title }}</span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
