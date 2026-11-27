<script setup>
import { Search } from '@element-plus/icons-vue';
import { Head, router } from '@inertiajs/vue3';
import { ElInput, ElSwitch } from 'element-plus';
import 'element-plus/es/components/input/style/css';
import 'element-plus/es/components/switch/style/css';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelCardLink from '@/Components/Ui/PanelCardLink.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import PanelPagination from '@/Components/Ui/PanelPagination.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';

const props = defineProps({
    courses: { type: Object, required: true },
    canShowAll: { type: Boolean, required: true },
    showAll: { type: Boolean, required: true },
    search: { type: String, required: true },
});

const searchInput = ref(props.search);
const pending = ref(false);
const title = computed(() => props.showAll ? 'همهٔ دوره‌ها' : 'دوره‌های من');
const resultCount = computed(() => `${props.courses.total.toLocaleString('fa-IR')} دوره`);
const hasMixedCovers = computed(() => props.courses.data.some((course) => course.cover_url)
    && props.courses.data.some((course) => !course.cover_url));
const emptyMessage = computed(() => {
    if (props.search !== '') {
        return 'دوره‌ای با این عنوان پیدا نشد.';
    }

    return props.showAll ? 'دوره‌ای برای نمایش وجود ندارد.' : 'در هیچ دوره‌ای ثبت‌نام فعال ندارید.';
});

watch(() => props.search, (search) => { searchInput.value = search; });

function visitCourses({ showAll = props.showAll, search = props.search, page } = {}) {
    const query = {
        ...(showAll ? { all: 1 } : {}),
        ...(search !== '' ? { search } : {}),
        ...(page ? { page } : {}),
    };

    router.get(route('courses.index'), query, {
        preserveState: true,
        onStart: () => { pending.value = true; },
        onFinish: () => { pending.value = false; },
    });
}

function submitSearch() {
    searchInput.value = searchInput.value.trim();
    visitCourses({ search: searchInput.value });
}

function clearSearch() {
    searchInput.value = '';
    visitCourses({ search: '' });
}
</script>

<template>
    <AppLayout>
        <Head :title="title" />
        <div class="@container grid gap-4 sm:gap-5">
            <UiPageHeader :title="title" icon="academic" />

            <PanelCard body-class="grid gap-3 [--el-card-padding:0.75rem] @xl:flex @xl:items-center @xl:justify-between @xl:gap-5 @xl:[--el-card-padding:1rem]">
                <form role="search" class="flex min-w-0 items-center gap-2 @xl:w-full @xl:max-w-md" @submit.prevent="submitSearch">
                    <ElInput
                        v-model="searchInput"
                        maxlength="100"
                        clearable
                        placeholder="جست‌وجوی عنوان دوره"
                        aria-label="جست‌وجوی عنوان دوره"
                        @clear="clearSearch"
                    >
                        <template #prefix>
                            <Search class="me-2 size-4" aria-hidden="true" />
                        </template>
                    </ElInput>
                    <PanelButton type="submit" :disabled="pending">جست‌وجو</PanelButton>
                </form>
                <div
                    v-if="canShowAll"
                    class="flex items-center justify-between gap-3 border-t border-ui-line px-1 pt-2 @xl:shrink-0 @xl:border-s @xl:border-t-0 @xl:ps-4 @xl:pt-0"
                >
                    <label for="show-all-courses" class="cursor-pointer text-sm font-medium">نمایش همهٔ دوره‌ها</label>
                    <ElSwitch
                        id="show-all-courses"
                        :model-value="showAll"
                        :disabled="pending"
                        @change="visitCourses({ showAll: $event })"
                    />
                </div>
            </PanelCard>

            <PanelCard v-if="!courses.data.length">
                <PanelEmpty :description="emptyMessage" />
                <div v-if="search" class="flex justify-center pb-5">
                    <PanelButton variant="outline-brand" :disabled="pending" @click="clearSearch">پاک‌کردن جست‌وجو</PanelButton>
                </div>
            </PanelCard>

            <div v-else class="grid gap-3">
                <p role="status" class="px-1 text-sm font-medium text-ui-muted">{{ resultCount }}</p>
                <ul class="grid grid-cols-1 gap-3 @xl:grid-cols-2 @xl:gap-4 @4xl:grid-cols-3">
                    <li v-for="course in courses.data" :key="course.url" class="min-w-0">
                        <PanelCardLink
                            :href="course.url"
                            :class="{ 'h-full': !hasMixedCovers }"
                            :card-class="['[--panel-card-overflow:hidden]', { 'h-auto!': hasMixedCovers }]"
                            :body-class="hasMixedCovers ? 'flex flex-col' : 'flex h-full flex-col'"
                        >
                            <img
                                v-if="course.cover_url"
                                :src="course.cover_url"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="aspect-video w-full shrink-0 border-b border-ui-line bg-ui-canvas object-cover"
                            />
                            <div class="flex grow flex-col p-4">
                                <p v-if="course.category" class="mb-1 wrap-break-word text-xs/5 font-medium text-ui-muted">{{ course.category }}</p>
                                <h2 class="mb-3 grow wrap-break-word text-base/6 font-bold">{{ course.title }}</h2>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-ui-line pt-3 text-sm">
                                    <span class="inline-flex items-center gap-1.5 text-ui-muted">
                                        <PanelIcon name="book" class="size-4 shrink-0" />
                                        {{ course.lessons_count.toLocaleString('fa-IR') }} درس
                                    </span>
                                    <span v-if="showAll && course.enrolled" class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">
                                        ثبت‌نام‌شده
                                    </span>
                                    <span class="ms-auto inline-flex items-center gap-1.5 font-semibold text-brand-700 group-hover:underline">
                                        {{ course.enrolled ? 'ورود به دوره' : 'مشاهدهٔ دوره' }}
                                        <PanelIcon name="chevron-right" class="size-4 shrink-0 rtl:rotate-180" />
                                    </span>
                                </div>
                            </div>
                        </PanelCardLink>
                    </li>
                </ul>
            </div>

            <nav v-if="courses.last_page > 1" aria-label="صفحه‌بندی دوره‌ها" class="flex justify-center border-t border-ui-line pt-4">
                <PanelPagination size="small" :current-page="courses.current_page" :page-count="courses.last_page" :disabled="pending" @current-change="visitCourses({ page: $event })" />
            </nav>
        </div>
    </AppLayout>
</template>
