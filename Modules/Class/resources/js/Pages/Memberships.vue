<script setup>
import { Head } from '@inertiajs/vue3';
import PanelCardLink from '@/Components/Ui/PanelCardLink.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    classes: { type: Array, required: true },
});
</script>

<template>
    <Head title="کلاس‌های من" />

    <AppLayout>
        <div class="grid min-w-0 gap-3 sm:gap-4">
            <UiPageHeader title="کلاس‌های من" icon="users" />

            <PanelEmpty v-if="!classes.length" description="در حال حاضر عضو هیچ کلاسی نیستید." />

            <PanelCardLink
                v-for="classroom in classes"
                :key="classroom.id"
                :href="route('my-classes.membership', classroom.id)"
                body-class="[--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]"
            >
                <span class="wrap-break-word font-semibold text-ui-ink">{{ classroom.title }}</span>
            </PanelCardLink>
        </div>
    </AppLayout>
</template>
