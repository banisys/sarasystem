<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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
</script>

<template>
    <AppLayout>
        <Head :title="course.title" />
        <div class="grid gap-4">
            <UiPageHeader :title="course.title" :description="course.category" icon="academic" :back-href="route('courses.index')" back-label="دوره‌های آموزشی">
                <template #actions>
                    <form v-if="canEnroll" @submit.prevent="enrollment.post(route('courses.enroll', course.id))">
                        <PanelButton type="submit" :loading="enrollment.processing">ثبت‌نام رایگان</PanelButton>
                    </form>
                    <PanelButton v-if="enrolled && nextLesson" :href="nextLesson.url">
                        {{ completed === 0 ? 'شروع دوره' : completed === lessons.length ? 'مرور دوره' : 'ادامهٔ دوره' }}
                    </PanelButton>
                </template>
            </UiPageHeader>
            <PanelCard v-if="course.description || course.instructors.length || (enrolled && lessons.length) || !enrolled && !canEnroll">
                <div class="grid gap-3">
                    <p v-if="course.description" class="whitespace-pre-line text-sm/7">{{ course.description }}</p>
                    <p v-if="course.instructors.length" class="text-sm text-ui-muted">مدرسان: {{ course.instructors.join('، ') }}</p>
                    <div v-if="enrolled && lessons.length" class="grid gap-2 text-sm">
                        <p>{{ completed.toLocaleString('fa-IR') }} از {{ lessons.length.toLocaleString('fa-IR') }} درس تکمیل شده است.</p>
                        <progress :max="lessons.length" :value="completed" aria-label="پیشرفت دوره" class="h-2 w-full accent-brand-600" />
                    </div>
                    <p v-else-if="hasInactiveEnrollment" class="text-sm text-ui-muted">دسترسی ثبت‌نام شما فعال نیست.</p>
                    <p v-else-if="!enrolled && !canEnroll" class="text-sm text-ui-muted">ثبت‌نام این دوره به‌صورت دستی انجام می‌شود.</p>
                </div>
            </PanelCard>
            <PanelCard>
                <h2 class="mb-4 text-lg font-bold">سرفصل‌ها</h2>
                <Syllabus :sections="sections" show-access />
            </PanelCard>
        </div>
    </AppLayout>
</template>
