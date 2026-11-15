<script setup>
import PanelCardLink from '@/Components/Ui/PanelCardLink.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import { ElTag } from 'element-plus';
import 'element-plus/es/components/tag/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import { Head } from '@inertiajs/vue3';
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

            <PanelEmpty v-if="!exams.length" description="آزمونی برای شما نیست." />

            <PanelCardLink
                v-for="exam in exams"
                :key="exam.id"
                :href="route('exams.show', exam.id)"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="min-w-0 wrap-break-word font-semibold text-ui-ink">{{ exam.title }}</h2>
                    <ElTag :type="exam.is_open ? 'success' : 'info'" round>
                        {{ exam.is_open ? 'باز' : 'بسته' }}
                    </ElTag>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ui-muted">
                    <span>مدت: {{ exam.duration_minutes }} دقیقه</span>
                    <span v-if="exam.starts_at">از {{ formatDateTime(exam.starts_at) }}</span>
                    <span v-if="exam.ends_at">تا {{ formatDateTime(exam.ends_at) }}</span>
                    <span v-if="exam.allow_late_start">بدون مهلت پایانی برای شروع</span>
                    <span v-if="exam.last_status" class="text-brand-700">{{ exam.last_status }}</span>
                </div>
            </PanelCardLink>
        </div>
    </AppLayout>
</template>
