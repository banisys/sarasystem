<script setup>
import { Head } from '@inertiajs/vue3';
import PanelCard from '@/Components/Ui/PanelCard.vue';
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

            <PanelCard v-for="classroom in classes" :key="classroom.id" body-class="grid gap-3 [--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]">
                <h2 class="wrap-break-word font-bold text-ui-ink">{{ classroom.title }}</h2>
                <div v-if="classroom.term || classroom.location" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-ui-muted">
                    <span v-if="classroom.term">{{ classroom.term }}</span>
                    <span v-if="classroom.location">{{ classroom.location }}</span>
                </div>
                <p v-if="classroom.description" class="wrap-break-word text-sm whitespace-pre-line text-ui-ink">{{ classroom.description }}</p>

                <details class="border-t border-ui-line pt-3">
                    <summary class="cursor-pointer font-semibold text-ui-ink">اعضای کلاس</summary>
                    <ul class="mt-2 divide-y divide-ui-line text-sm">
                        <li v-for="member in classroom.members" :key="member.id" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                            <span class="min-w-0 wrap-break-word text-ui-ink">{{ member.name }}</span>
                            <span class="min-w-0 wrap-break-word text-ui-muted">{{ member.roles }}</span>
                        </li>
                    </ul>
                </details>
            </PanelCard>
        </div>
    </AppLayout>
</template>
