<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import UiButton from '@/Components/Ui/UiButton.vue';

const props = defineProps({
    courses: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
    catalogUrl: { type: String, required: true },
});

const filters = reactive({ ...props.filters });

function search() {
    router.get(props.catalogUrl, Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')));
}
</script>

<template>
    <PublicLayout>
        <Head title="دوره‌های آموزشی" />
        <div class="grid gap-6">
            <h1 class="text-3xl font-extrabold sm:text-4xl">دوره‌های آموزشی</h1>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="search">
                <label class="grid min-w-0 grow gap-1.5 text-sm">
                    جستجوی عنوان
                    <input v-model="filters.search" type="search" maxlength="100" class="rounded-ui border border-ui-line bg-white px-3 py-2.5 text-sm" />
                </label>
                <label v-if="categories.length" class="grid min-w-40 gap-1.5 text-sm">
                    دسته‌بندی
                    <select v-model="filters.category" class="rounded-ui border border-ui-line bg-white px-3 py-2.5 text-sm">
                        <option value="">همهٔ دسته‌ها</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.title }}</option>
                    </select>
                </label>
                <UiButton type="submit">جستجو</UiButton>
            </form>

            <p v-if="!courses.data.length" class="rounded-ui border border-ui-line px-4 py-6 text-ui-muted">دوره‌ای پیدا نشد.</p>
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="course in courses.data"
                    :key="course.id"
                    :href="course.url"
                    class="grid content-start gap-2 rounded-ui border border-ui-line bg-white p-5 transition-colors hover:border-brand-600 focus-visible:outline-2 focus-visible:outline-brand-600"
                >
                    <span v-if="course.category" class="text-xs font-semibold text-ui-muted">{{ course.category }}</span>
                    <h2 class="wrap-break-word text-lg font-bold">{{ course.title }}</h2>
                    <p class="text-sm text-ui-muted">{{ course.lessons_count.toLocaleString('fa-IR') }} درس</p>
                    <span v-if="course.allows_self_enrollment" class="text-sm font-medium text-brand-700">ثبت‌نام رایگان</span>
                </Link>
            </div>
            <nav v-if="courses.last_page > 1" aria-label="صفحات دوره‌ها" class="flex items-center gap-3">
                <UiButton v-if="courses.prev_page_url" :href="courses.prev_page_url" variant="outline">قبلی</UiButton>
                <span class="text-sm">{{ courses.current_page.toLocaleString('fa-IR') }} از {{ courses.last_page.toLocaleString('fa-IR') }}</span>
                <UiButton v-if="courses.next_page_url" :href="courses.next_page_url" variant="outline">بعدی</UiButton>
            </nav>
        </div>
    </PublicLayout>
</template>
