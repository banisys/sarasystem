<script setup>
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
            <h1 class="text-xl font-bold text-gray-900">آزمون‌ها</h1>

            <p v-if="!exams.length" class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">آزمونی برای شما نیست.</p>

            <Link
                v-for="exam in exams"
                :key="exam.id"
                :href="route('exams.show', exam.id)"
                class="block rounded-2xl bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">{{ exam.title }}</h2>
                    <span
                        class="rounded-full px-3 py-1 text-xs"
                        :class="exam.is_open ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ exam.is_open ? 'باز' : 'بسته' }}
                    </span>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span>مدت: {{ exam.duration_minutes }} دقیقه</span>
                    <span v-if="exam.starts_at">از {{ formatDateTime(exam.starts_at) }}</span>
                    <span v-if="exam.ends_at">تا {{ formatDateTime(exam.ends_at) }}</span>
                    <span v-if="exam.last_status" class="text-violet-700">{{ exam.last_status }}</span>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
