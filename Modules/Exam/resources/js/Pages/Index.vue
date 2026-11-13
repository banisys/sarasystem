<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '../format.js';

defineProps({
    exams: { type: Array, required: true },
});
</script>

<template>
    <Head title="آزمون‌ها" />

    <AppLayout>
        <div class="space-y-4">
            <UiPageHeader title="آزمون‌ها" icon="clipboard" />

            <UiNotice v-if="!exams.length">آزمونی برای شما نیست.</UiNotice>

            <Link
                v-for="exam in exams"
                :key="exam.id"
                :href="route('exams.show', exam.id)"
                class="ui-card-link"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="min-w-0 wrap-break-word font-semibold text-ui-ink">{{ exam.title }}</h2>
                    <span
                        class="ui-badge"
                        :class="exam.is_open ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ exam.is_open ? 'باز' : 'بسته' }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ui-muted">
                    <span>مدت: {{ exam.duration_minutes }} دقیقه</span>
                    <span v-if="exam.starts_at">از {{ formatDateTime(exam.starts_at) }}</span>
                    <span v-if="exam.ends_at">تا {{ formatDateTime(exam.ends_at) }}</span>
                    <span v-if="exam.last_status" class="text-violet-700">{{ exam.last_status }}</span>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
