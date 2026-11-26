<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';

const props = defineProps({
    course: { type: Object, required: true },
    lesson: { type: Object, required: true },
    attachments: { type: Array, required: true },
    video: { type: String, default: null },
    audio: { type: String, default: null },
    previous: { type: String, default: null },
    next: { type: String, default: null },
    examUrl: { type: String, default: null },
    completed: Boolean,
    canComplete: Boolean,
});

const completion = useForm({});

function markComplete() {
    completion.put(route('courses.lesson.complete', [props.course.id, props.lesson.id]), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="lesson.title" />
        <div class="grid gap-4">
            <UiPageHeader :title="lesson.title" icon="book" :back-href="route('courses.show', course.id)" :back-label="course.title" />

            <PanelCard v-if="video || audio || lesson.bodyHtml || lesson.body || attachments.length">
                <div class="grid gap-5">
                    <video v-if="video" :key="video" :src="video" controls playsinline preload="metadata" class="aspect-video w-full rounded-ui bg-black" />
                    <audio v-if="audio" :key="audio" :src="audio" controls preload="metadata" aria-label="فایل صوتی درس" class="w-full" />
                    <div v-if="lesson.bodyHtml" class="wrap-break-word text-sm/8 [&_a]:underline [&_h2]:text-lg [&_h2]:font-bold [&_h3]:font-bold [&_ol]:list-decimal [&_ol]:ps-6 [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:ps-6" v-html="lesson.bodyHtml" />
                    <p v-else-if="lesson.body" class="wrap-break-word whitespace-pre-wrap text-sm/8">{{ lesson.body }}</p>
                    <div v-if="attachments.length">
                        <h2 class="font-semibold">پیوست‌ها</h2>
                        <ul class="mt-2 grid gap-2">
                            <li v-for="file in attachments" :key="file.url">
                                <a :href="file.url" class="break-all text-sm font-medium text-brand-700 underline underline-offset-4">{{ file.name }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </PanelCard>
            <p v-else class="text-sm text-ui-muted">محتوایی برای این درس ثبت نشده است.</p>

            <div class="flex flex-wrap items-center gap-3">
                <PanelButton v-if="examUrl" :href="examUrl" variant="outline-brand">آزمون‌های درس</PanelButton>
                <form v-if="canComplete && !completed" @submit.prevent="markComplete">
                    <PanelButton type="submit" :loading="completion.processing">تکمیل درس</PanelButton>
                </form>
                <p v-if="completed" class="text-sm font-semibold text-brand-700">درس تکمیل شد</p>
                <div v-if="previous || next" class="ms-auto flex flex-wrap gap-2">
                    <PanelButton v-if="previous" :href="previous" variant="outline-brand">درس قبل</PanelButton>
                    <PanelButton v-if="next" :href="next" variant="outline-brand">درس بعد</PanelButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
