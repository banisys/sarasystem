<script setup>
import { ElCard } from 'element-plus';
import 'element-plus/es/components/card/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// «دفتر نمره» (ADR 0032): the subjects the teacher teaches in classes with a grade.
defineProps({
    assignments: { type: Array, required: true },
});
</script>

<template>
    <Head title="دفتر نمره" />

    <AppLayout>
        <div class="space-y-4">
            <UiPageHeader title="دفتر نمره" description="درس و دورهٔ نمره‌دهی را انتخاب کنید." icon="book" />

            <PanelNotice v-if="!assignments.length">درسی برای نمره‌دادن ندارید.</PanelNotice>

            <div class="grid gap-3 sm:grid-cols-2">
                <ElCard v-for="item in assignments" :key="`${item.classroom_id}-${item.subject_id}`" shadow="never">
                    <h2 class="font-semibold">{{ item.subject }}</h2>
                    <p class="mt-1 text-sm text-ui-muted">{{ item.classroom }}</p>
                    <p v-if="!item.periods.length" class="mt-2 text-sm text-gray-500">دوره‌ی نمره‌دهی‌ای تعریف نشده است.</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        <PanelButton
                            v-for="period in item.periods"
                            :key="period.id"
                            :href="route('school.scores.show', { classroom: item.classroom_id, subjectId: item.subject_id, period: period.id })"
                            :variant="period.is_open ? 'primary' : 'outline'"
                        >
                            {{ period.title }}<span v-if="!period.is_open"> (بسته)</span>
                        </PanelButton>
                    </div>
                </ElCard>
            </div>
        </div>
    </AppLayout>
</template>
