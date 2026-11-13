<script setup>
import UiButton from '@/Components/Ui/UiButton.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import { Head } from '@inertiajs/vue3';
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
            <section class="ui-surface p-5 text-center sm:p-7">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <PanelIcon name="video" class="size-6" />
                </div>
                <h1 class="mt-4 text-lg font-semibold text-gray-900">ورود به کلاس آنلاین ممکن نشد</h1>
                <p v-if="$props.class" class="mt-1 text-sm text-gray-500">{{ $props.class }}<span v-if="time"> · {{ time }}</span></p>
                <UiNotice class="mt-4 text-start" tone="error">{{ message }}</UiNotice>
                <UiButton :href="route('my-classes.index')" class="mt-6">
                    کلاس‌های من
                </UiButton>
            </section>
        </div>
    </AppLayout>
</template>
