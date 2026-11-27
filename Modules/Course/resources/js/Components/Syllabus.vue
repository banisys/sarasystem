<script setup>
import { CopyDocument } from '@element-plus/icons-vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, useId } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';

defineProps({
    sections: { type: Array, required: true },
    showAccess: Boolean,
    accordion: Boolean,
});

const accordionName = useId();
const failedCopy = ref(null);

async function copyLessonCode(lesson) {
    failedCopy.value = null;

    try {
        await navigator.clipboard.writeText(lesson.code);
    } catch {
        failedCopy.value = lesson.id;
        return;
    }

    router.flash('status', 'کد درس کپی شد.');
}
</script>

<template>
    <div class="grid gap-4">
        <p v-if="!sections.length" class="text-sm text-ui-muted">سرفصل دوره هنوز منتشر نشده است.</p>
        <component v-for="(section, index) in sections" :key="section.id" :is="accordion ? 'details' : 'section'"
            :name="accordion ? accordionName : undefined" :open="accordion && index === 0 ? true : undefined"
            class="group overflow-hidden rounded-ui border border-ui-line">
            <summary v-if="accordion"
                class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-semibold hover:bg-ui-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-700 group-open:border-b group-open:border-ui-line [&::-webkit-details-marker]:hidden">
                <span class="min-w-0 wrap-break-word">{{ section.title }}</span>
                <PanelIcon name="chevron-right" class="size-4 shrink-0 text-ui-muted rtl:rotate-180 group-open:rotate-90" />
            </summary>
            <h3 v-else class="border-b border-ui-line px-4 py-3 font-semibold">{{ section.title }}</h3>
            <p v-if="!section.lessons.length" class="px-4 py-3 text-sm text-ui-muted">درسی در این بخش وجود ندارد.</p>
            <ol class="divide-y divide-ui-line">
                <li
                    v-for="lesson in section.lessons"
                    :key="lesson.id"
                    class="relative flex items-center gap-3 px-4 py-3 text-sm"
                    :class="{ 'hover:bg-brand-50 focus-within:bg-brand-50': lesson.url }"
                >
                    <PanelIcon v-if="lesson.completed" name="check" class="size-4 shrink-0 text-brand-700" />
                    <PanelIcon v-else-if="showAccess && !lesson.url" name="lock" class="size-4 shrink-0 text-ui-muted" />
                    <PanelIcon v-else name="book" class="size-4 shrink-0 text-ui-muted" />
                    <span class="min-w-0 flex-1 wrap-break-word">
                        <Link
                            v-if="lesson.url"
                            :href="lesson.url"
                            class="after:absolute after:inset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                        >
                            {{ lesson.title }}
                            <span v-if="lesson.completed" class="sr-only">تکمیل‌شده</span>
                        </Link>
                        <template v-else>
                            {{ lesson.title }}
                            <span v-if="lesson.completed" class="sr-only">تکمیل‌شده</span>
                        </template>
                        <button
                            v-if="lesson.code"
                            type="button"
                            :aria-label="`کپی کد درس ${lesson.code}`"
                            title="کپی کد درس"
                            class="relative z-10 ms-1 inline-flex min-h-6 max-w-full cursor-pointer items-center gap-1 rounded border border-ui-line bg-ui-canvas px-1.5 py-0.5 align-middle text-xs text-ui-ink hover:border-brand-500 hover:text-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                            @click="copyLessonCode(lesson)"
                        >
                            <bdi class="min-w-0 wrap-break-word font-mono">{{ lesson.code }}</bdi>
                            <CopyDocument class="size-3.5 shrink-0" aria-hidden="true" />
                        </button>
                        <span v-if="failedCopy === lesson.id" role="alert" class="mt-1 block text-xs text-ui-muted">کپی کد انجام نشد.</span>
                    </span>
                    <span v-if="lesson.duration_minutes" class="shrink-0 text-ui-muted">{{ lesson.duration_minutes.toLocaleString('fa-IR') }} دقیقه</span>
                    <span v-if="!lesson.completed && lesson.is_preview" class="shrink-0 text-brand-700">پیش‌نمایش</span>
                    <span v-else-if="!lesson.completed && showAccess && !lesson.url" class="sr-only">ویژهٔ ثبت‌نام‌شدگان</span>
                </li>
            </ol>
        </component>
    </div>
</template>
