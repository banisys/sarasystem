<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelCardLink from '@/Components/Ui/PanelCardLink.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import PanelPagination from '@/Components/Ui/PanelPagination.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';

const props = defineProps({
    courses: { type: Object, required: true },
    onlyMine: Boolean,
});

function goToPage(page) {
    router.get(route('courses.index'), { ...(props.onlyMine ? { mine: 1 } : {}), page });
}
</script>

<template>
    <AppLayout>
        <Head :title="onlyMine ? 'دوره‌های من' : 'دوره‌های آموزشی'" />
        <div class="grid gap-4">
            <UiPageHeader :title="onlyMine ? 'دوره‌های من' : 'دوره‌های آموزشی'" icon="academic">
                <template #actions>
                    <PanelButton v-if="onlyMine" :href="route('courses.index')" variant="outline-brand">همهٔ دوره‌ها</PanelButton>
                    <PanelButton v-else :href="route('courses.index', { mine: 1 })" variant="outline-brand">دوره‌های من</PanelButton>
                </template>
            </UiPageHeader>

            <PanelEmpty v-if="!courses.data.length" :description="onlyMine ? 'در هیچ دوره‌ای ثبت‌نام فعال ندارید.' : 'دوره‌ای منتشر نشده است.'" />

            <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <PanelCardLink v-for="course in courses.data" :key="course.url" :href="course.url">
                    <div class="grid gap-2">
                        <span v-if="course.category" class="text-xs font-medium text-ui-muted">{{ course.category }}</span>
                        <h2 class="wrap-break-word text-lg font-bold">{{ course.title }}</h2>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-ui-muted">
                            <span>{{ course.lessons_count.toLocaleString('fa-IR') }} درس</span>
                            <span v-if="course.enrolled" class="font-medium text-brand-700">ثبت‌نام‌شده</span>
                            <span v-else-if="course.allows_self_enrollment" class="font-medium text-brand-700">ثبت‌نام رایگان</span>
                        </div>
                    </div>
                </PanelCardLink>
            </div>

            <PanelPagination v-if="courses.last_page > 1" :current-page="courses.current_page" :page-count="courses.last_page" @current-change="goToPage" />
        </div>
    </AppLayout>
</template>
