<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// Why «ورود به کلاس آنلاین» didn't go into the room (ADR 0073).
defineProps({
    message: { type: String, required: true },
    class: { type: String, default: null },
    time: { type: String, default: null },
});
</script>

<template>
    <Head title="ورود به کلاس آنلاین" />

    <AppLayout>
        <div class="mx-auto max-w-lg">
            <section class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <PanelIcon name="video" class="size-6" />
                </div>
                <h1 class="mt-4 text-lg font-semibold text-gray-900">ورود به کلاس آنلاین ممکن نشد</h1>
                <p v-if="$props.class" class="mt-1 text-sm text-gray-500">{{ $props.class }}<span v-if="time"> · {{ time }}</span></p>
                <p class="mt-4 text-sm leading-7 text-gray-800">{{ message }}</p>
                <Link :href="route('my-classes.index')" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-violet-700">
                    کلاس‌های من
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
