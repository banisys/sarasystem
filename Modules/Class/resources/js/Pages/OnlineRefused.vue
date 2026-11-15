<script setup>
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import { Head } from '@inertiajs/vue3';
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
        <div class="mx-auto max-w-lg space-y-4">
            <UiPageHeader
                title="ورود به کلاس آنلاین ممکن نشد"
                :description="$props.class && (time ? `${$props.class} · ${time}` : $props.class)"
                icon="video"
            />
            <PanelCard body-class="p-5 text-center sm:p-7">
                <PanelNotice class="text-start" tone="error">{{ message }}</PanelNotice>
                <PanelButton :href="route('my-classes.index')" class="mt-6">
                    کلاس‌های من
                </PanelButton>
            </PanelCard>
        </div>
    </AppLayout>
</template>
