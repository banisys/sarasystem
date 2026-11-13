<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
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

            <UiNotice v-if="!assignments.length">درسی برای نمره‌دادن ندارید.</UiNotice>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="item in assignments" :key="`${item.classroom_id}-${item.subject_id}`" class="ui-surface p-4 sm:p-5">
                    <h2 class="font-semibold">{{ item.subject }}</h2>
                    <p class="mt-1 text-sm text-ui-muted">{{ item.classroom }}</p>
                    <p v-if="!item.periods.length" class="mt-2 text-sm text-gray-500">دوره‌ی نمره‌دهی‌ای تعریف نشده است.</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        <UiButton
                            v-for="period in item.periods"
                            :key="period.id"
                            :href="route('school.scores.show', { classroom: item.classroom_id, subjectId: item.subject_id, period: period.id })"
                            :variant="period.is_open ? 'primary' : 'outline'"
                        >
                            {{ period.title }}<span v-if="!period.is_open"> (بسته)</span>
                        </UiButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
