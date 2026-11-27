<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
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
const hasText = computed(() => Boolean(props.lesson.bodyHtml || props.lesson.body));

function markComplete() {
    completion.put(route('courses.lesson.complete', [props.course.id, props.lesson.id]), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="lesson.title" />
        <div class="grid grid-cols-1 gap-5">
            <UiPageHeader
                :title="lesson.title"
                :description="course.title"
                icon="book"
                :back-href="route('courses.show', course.id)"
                back-label="بازگشت به دوره"
            >
                <template v-if="lesson.duration_minutes" #actions>
                    <span class="inline-flex items-center gap-1.5 text-sm text-ui-muted">
                        <PanelIcon name="clock" class="size-4 shrink-0" />
                        {{ lesson.duration_minutes.toLocaleString('fa-IR') }} دقیقه
                    </span>
                </template>
            </UiPageHeader>

            <PanelCard v-if="video || audio" body-class="grid gap-4 [--el-card-padding:1rem] sm:[--el-card-padding:1.25rem]">
                <video v-if="video" :key="video" :src="video" controls playsinline preload="metadata"
                    :aria-label="lesson.title" class="aspect-video max-h-[65svh] w-full rounded-ui bg-black object-contain" />
                <div v-if="audio" class="grid gap-2">
                    <h2 id="lesson-audio" class="text-sm font-semibold">فایل صوتی درس</h2>
                    <audio :key="audio" :src="audio" controls preload="metadata" aria-labelledby="lesson-audio" class="w-full min-w-0" />
                </div>
            </PanelCard>

            <PanelCard v-if="hasText" body-class="grid gap-3 [--el-card-padding:1rem] sm:[--el-card-padding:1.5rem]">
                <h2 class="text-lg font-bold">متن درس</h2>
                <div v-if="lesson.bodyHtml"
                    class="min-w-0 overflow-x-auto wrap-break-word text-sm/8 [&_a]:text-brand-700 [&_a]:underline [&_h2]:text-lg [&_h2]:font-bold [&_h3]:font-bold [&_ol]:list-decimal [&_ol]:ps-6 [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:ps-6"
                    v-html="lesson.bodyHtml" />
                <p v-else class="wrap-break-word whitespace-pre-wrap text-sm/8">{{ lesson.body }}</p>
            </PanelCard>

            <PanelCard v-if="!video && !audio && !hasText && !attachments.length"
                body-class="[--el-card-padding:1rem] sm:[--el-card-padding:1.25rem]">
                <p class="text-sm text-ui-muted">محتوایی برای این درس ثبت نشده است.</p>
            </PanelCard>

            <PanelCard v-if="attachments.length" body-class="grid gap-3 [--el-card-padding:1rem]">
                <h2 class="text-base font-bold">پیوست‌ها</h2>
                <ul class="grid gap-2">
                    <li v-for="file in attachments" :key="file.url">
                        <a :href="file.url"
                            class="flex items-center gap-2 rounded-md border border-ui-line px-3 py-2.5 text-sm font-medium text-brand-700 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-brand-700">
                            <PanelIcon name="paperclip" class="size-4 shrink-0" />
                            <bdi class="min-w-0 wrap-break-word">{{ file.name }}</bdi>
                        </a>
                    </li>
                </ul>
            </PanelCard>
            <PanelCard v-if="examUrl" body-class="[--el-card-padding:1rem]">
                <PanelButton :href="examUrl" variant="outline-brand" class="w-full">
                    <PanelIcon name="clipboard" class="size-4 shrink-0" />
                    آزمون‌های درس
                </PanelButton>
            </PanelCard>

            <PanelCard v-if="canComplete || completed || previous || next"
                body-class="flex flex-col gap-3 [--el-card-padding:1rem] sm:flex-row sm:flex-wrap sm:items-center">
                <form v-if="canComplete && !completed" @submit.prevent="markComplete">
                    <PanelButton type="submit" :loading="completion.processing" class="w-full sm:w-auto">
                        <PanelIcon name="check" class="size-4 shrink-0" />
                        تکمیل درس
                    </PanelButton>
                </form>
                <p v-if="completed" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-700">
                    <PanelIcon name="check" class="size-4 shrink-0" />
                    درس تکمیل شد
                </p>
                <nav v-if="previous || next" aria-label="پیمایش درس‌ها" class="flex flex-wrap gap-2 sm:ms-auto">
                    <PanelButton v-if="previous" :href="previous" variant="outline-brand" class="grow sm:grow-0">
                        <PanelIcon name="chevron-right" class="size-4 shrink-0 ltr:rotate-180" />
                        درس قبل
                    </PanelButton>
                    <PanelButton v-if="next" :href="next" variant="outline-brand" class="grow sm:grow-0">
                        درس بعد
                        <PanelIcon name="chevron-right" class="size-4 shrink-0 rtl:rotate-180" />
                    </PanelButton>
                </nav>
            </PanelCard>
        </div>
    </AppLayout>
</template>
