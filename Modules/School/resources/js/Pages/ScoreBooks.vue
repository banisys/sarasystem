<script setup>
import { Head, Link } from '@inertiajs/vue3';
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
            <h1 class="text-xl font-bold text-gray-900">دفتر نمره</h1>

            <p v-if="!assignments.length" class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">درسی برای نمره‌دادن ندارید.</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="item in assignments" :key="`${item.classroom_id}-${item.subject_id}`" class="rounded-2xl bg-white p-4 shadow-sm">
                    <div class="font-semibold text-gray-900">{{ item.classroom }} · {{ item.subject }}</div>
                    <p v-if="!item.periods.length" class="mt-2 text-sm text-gray-500">دوره‌ی نمره‌دهی‌ای تعریف نشده است.</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        <Link
                            v-for="period in item.periods"
                            :key="period.id"
                            :href="route('school.scores.show', { classroom: item.classroom_id, subjectId: item.subject_id, period: period.id })"
                            class="rounded-lg border px-3 py-1"
                            :class="period.is_open ? 'border-violet-300 bg-violet-50 text-violet-800 hover:bg-violet-100' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                        >
                            {{ period.title }}<span v-if="!period.is_open"> (بسته)</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
