<script setup>
import { Head } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';
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

            <ul v-else class="grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
                <li v-for="classroom in classes" :key="classroom.id" class="min-w-0">
                    <PanelCardLink
                        :href="route('my-classes.membership', classroom.id)"
                        class="h-full"
                        body-class="flex h-full min-h-20 items-center gap-3 [--el-card-padding:0.875rem] sm:[--el-card-padding:1rem]"
                    >
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-100 text-brand-700 panel-dark:bg-brand-950 panel-dark:text-brand-300">
                            <PanelIcon name="academic" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1 wrap-break-word font-semibold text-ui-ink">{{ classroom.title }}</span>
                        <PanelIcon name="chevron-right" class="size-4 shrink-0 text-ui-muted group-hover:text-brand-700 rtl:rotate-180" />
                    </PanelCardLink>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
