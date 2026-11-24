<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import Syllabus from '../Components/Syllabus.vue';

defineProps({
    course: { type: Object, required: true },
    sections: { type: Array, required: true },
    catalogUrl: { type: String, required: true },
    studyUrl: { type: String, default: null },
});
</script>

<template>
    <PublicLayout>
        <Head :title="course.title" />
        <div class="grid max-w-4xl gap-6">
            <Link :href="catalogUrl" class="justify-self-start text-sm font-semibold text-brand-700 hover:underline">دوره‌های آموزشی</Link>
            <header class="grid gap-3">
                <p v-if="course.category" class="text-sm text-ui-muted">{{ course.category }}</p>
                <h1 class="wrap-break-word text-3xl font-extrabold sm:text-4xl">{{ course.title }}</h1>
                <p v-if="course.instructors.length" class="text-sm text-ui-muted">مدرسان: {{ course.instructors.join('، ') }}</p>
            </header>
            <p v-if="course.description" class="whitespace-pre-line text-sm/8">{{ course.description }}</p>

            <div v-if="studyUrl || course.allows_self_enrollment" class="flex flex-wrap items-center gap-3">
                <UiButton v-if="studyUrl" :href="studyUrl">{{ $page.props.auth.user ? 'مشاهده در پنل کاربری' : 'ورود برای مشاهدهٔ درس‌ها' }}</UiButton>
                <span v-if="course.allows_self_enrollment" class="text-sm text-ui-muted">ثبت‌نام رایگان</span>
            </div>

            <section class="grid gap-3">
                <h2 class="text-xl font-bold">سرفصل‌ها</h2>
                <Syllabus :sections="sections" />
            </section>
        </div>
    </PublicLayout>
</template>
