<script setup>
import { computed, ref, useTemplateRef, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import Syllabus from '../Components/Syllabus.vue';

const props = defineProps({
    course: { type: Object, required: true },
    sections: { type: Array, required: true },
    enrolled: Boolean,
    canEnroll: Boolean,
    hasInactiveEnrollment: Boolean,
});

const enrollment = useForm({});
const lessons = computed(() => props.sections.flatMap(section => section.lessons));
const completed = computed(() => lessons.value.filter(lesson => lesson.completed).length);
const nextLesson = computed(() => lessons.value.find(lesson => !lesson.completed) ?? lessons.value[0]);
const description = useTemplateRef('description');
const descriptionExpanded = ref(false);
const descriptionOverflows = ref(false);
const descriptionLineLimit = 4;

watch(() => props.course.id, () => {
    descriptionExpanded.value = false;
});

watch([description, () => props.course.description], ([element], _, onCleanup) => {
    if (!element) {
        descriptionOverflows.value = false;
        return;
    }

    function measureDescription() {
        const lineHeight = Number.parseFloat(getComputedStyle(element).lineHeight);
        descriptionOverflows.value = element.scrollHeight > Math.ceil(lineHeight * descriptionLineLimit);
    }

    measureDescription();
    const observer = new ResizeObserver(measureDescription);
    observer.observe(element);
    onCleanup(() => observer.disconnect());
}, { flush: 'post' });
</script>

<template>
    <AppLayout>
        <Head :title="course.title" />
        <div class="grid gap-5">
            <UiPageHeader
                :title="course.title"
                :description="course.category"
                icon="academic"
                :back-href="route('courses.index')"
                back-label="دوره‌های من"
                class="[&_h1]:text-xl sm:[&_h1]:text-2xl"
            />

            <div class="flex flex-col items-stretch gap-5 lg:flex-row-reverse lg:items-start">
                <aside aria-labelledby="course-summary" class="lg:w-60 lg:shrink-0 xl:w-68">
                    <PanelCard body-class="grid gap-4 [--el-card-padding:1rem] sm:[--el-card-padding:1.25rem]">
                        <h2 id="course-summary" class="text-base font-bold">اطلاعات دوره</h2>
                        <dl class="grid gap-3 text-sm">
                            <div class="flex items-center gap-2">
                                <PanelIcon name="book" class="size-4 shrink-0 text-ui-muted" />
                                <dt class="text-ui-muted">تعداد درس‌ها</dt>
                                <dd class="ms-auto font-semibold">{{ lessons.length.toLocaleString('fa-IR') }}</dd>
                            </div>
                            <div v-if="course.instructors.length" class="flex items-start gap-2">
                                <PanelIcon name="users" class="mt-0.5 size-4 shrink-0 text-ui-muted" />
                                <dt class="shrink-0 text-ui-muted">مدرسان</dt>
                                <dd class="ms-auto min-w-0 wrap-break-word text-end font-semibold">{{ course.instructors.join('، ') }}</dd>
                            </div>
                        </dl>

                        <div v-if="enrolled && lessons.length" class="grid gap-2 border-t border-ui-line pt-4">
                            <p class="text-sm text-ui-muted">{{ completed.toLocaleString('fa-IR') }} از {{ lessons.length.toLocaleString('fa-IR') }} درس تکمیل شده است.</p>
                            <progress :max="lessons.length" :value="completed" aria-label="پیشرفت دوره" class="h-2 w-full overflow-hidden rounded-full accent-brand-600" />
                        </div>
                        <p v-else-if="hasInactiveEnrollment || !enrolled && !canEnroll" class="rounded-ui bg-ui-canvas px-3 py-2.5 text-sm/6 text-ui-muted">
                            {{ hasInactiveEnrollment ? 'دسترسی ثبت‌نام شما فعال نیست.' : 'ثبت‌نام این دوره به‌صورت دستی انجام می‌شود.' }}
                        </p>

                        <form v-if="canEnroll" @submit.prevent="enrollment.post(route('courses.enroll', course.id))">
                            <PanelButton type="submit" :loading="enrollment.processing" class="w-full">ثبت‌نام رایگان</PanelButton>
                        </form>
                        <PanelButton v-if="enrolled && nextLesson" :href="nextLesson.url" class="w-full">
                            {{ completed === 0 ? 'شروع دوره' : completed === lessons.length ? 'مرور دوره' : 'ادامهٔ دوره' }}
                        </PanelButton>
                    </PanelCard>
                </aside>

                <div class="grid min-w-0 grow gap-5">
                    <PanelCard v-if="course.description" body-class="grid gap-3 [--el-card-padding:1rem] sm:[--el-card-padding:1.25rem]">
                        <h2 class="text-lg font-bold">دربارهٔ دوره</h2>
                        <p id="course-description" ref="description"
                            class="max-w-prose wrap-break-word whitespace-pre-line text-sm/8 text-ui-muted"
                            :class="{ 'line-clamp-(--description-line-limit)': !descriptionExpanded }"
                            :style="{ '--description-line-limit': descriptionLineLimit }"
                        >{{ course.description }}</p>
                        <PanelButton v-if="descriptionOverflows" variant="link" class="justify-self-start"
                            :aria-expanded="descriptionExpanded" aria-controls="course-description"
                            @click="descriptionExpanded = !descriptionExpanded">
                            {{ descriptionExpanded ? 'کمتر' : 'بیشتر' }}
                        </PanelButton>
                    </PanelCard>
                    <PanelCard body-class="grid gap-4 [--el-card-padding:1rem] sm:[--el-card-padding:1.25rem]">
                        <h2 class="text-lg font-bold">سرفصل‌ها</h2>
                        <Syllabus :sections="sections" show-access accordion />
                    </PanelCard>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
