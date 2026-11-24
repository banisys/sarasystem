<script setup>
import { Link } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';

defineProps({
    sections: { type: Array, required: true },
    showAccess: Boolean,
});
</script>

<template>
    <div class="grid gap-4">
        <p v-if="!sections.length" class="text-sm text-ui-muted">سرفصل دوره هنوز منتشر نشده است.</p>
        <section v-for="section in sections" :key="section.id" class="overflow-hidden rounded-ui border border-ui-line">
            <h3 class="border-b border-ui-line px-4 py-3 font-semibold">{{ section.title }}</h3>
            <p v-if="!section.lessons.length" class="px-4 py-3 text-sm text-ui-muted">درسی در این بخش وجود ندارد.</p>
            <ol class="divide-y divide-ui-line">
                <li v-for="lesson in section.lessons" :key="lesson.id">
                    <component
                        :is="lesson.url ? Link : 'div'"
                        :href="lesson.url ?? undefined"
                        class="flex items-center gap-3 px-4 py-3 text-sm"
                        :class="{ 'hover:bg-brand-50 focus-visible:bg-brand-50': lesson.url }"
                    >
                        <PanelIcon v-if="lesson.completed" name="check" class="size-4 shrink-0 text-brand-700" />
                        <PanelIcon v-else name="book" class="size-4 shrink-0 text-ui-muted" />
                        <span class="min-w-0 flex-1 wrap-break-word">{{ lesson.title }}</span>
                        <span v-if="lesson.duration_minutes" class="shrink-0 text-ui-muted">{{ lesson.duration_minutes.toLocaleString('fa-IR') }} دقیقه</span>
                        <span v-if="lesson.completed" class="sr-only">تکمیل‌شده</span>
                        <span v-else-if="lesson.is_preview" class="shrink-0 text-brand-700">پیش‌نمایش</span>
                        <span v-else-if="showAccess && !lesson.url" class="sr-only">ویژهٔ ثبت‌نام‌شدگان</span>
                    </component>
                </li>
            </ol>
        </section>
    </div>
</template>
