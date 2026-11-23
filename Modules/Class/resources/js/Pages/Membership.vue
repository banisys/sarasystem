<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    classroom: { type: Object, required: true },
});

const number = new Intl.NumberFormat('fa-IR');
const facts = computed(() => {
    const item = props.classroom;

    return [
        { label: 'شیوهٔ پیش‌فرض', value: item.mode },
        { label: 'دورهٔ آموزشی', value: item.term },
        { label: 'کد کلاس', value: item.code },
        { label: 'محل کلاس', value: item.location },
        { label: 'شروع کلاس', value: item.starts_on },
        { label: 'پایان کلاس', value: item.ends_on },
        { label: 'ظرفیت فراگیران', value: item.capacity === null ? null : number.format(item.capacity) },
    ].filter(({ value }) => value !== null && value !== '');
});
</script>

<template>
    <Head :title="classroom.title" />

    <AppLayout>
        <div class="grid min-w-0 gap-3 sm:gap-4">
            <Link :href="route('my-classes.memberships')" class="inline-flex w-fit items-center gap-1.5 rounded text-sm font-semibold text-brand-700 hover:underline">
                <PanelIcon name="chevron-right" class="size-4 ltr:rotate-180" />
                کلاس‌های من
            </Link>

            <UiPageHeader :title="classroom.title" icon="academic" />

            <PanelCard body-class="grid gap-4 [--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-ui-ink">اطلاعات کلاس</h2>
                    <span class="rounded-full border border-ui-line px-2.5 py-1 text-xs font-semibold text-ui-muted">
                        {{ classroom.is_active ? 'فعال' : 'غیرفعال' }}
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-2.5 xl:grid-cols-3">
                    <div v-for="fact in facts" :key="fact.label" class="min-w-0 rounded-lg border border-ui-line bg-ui-canvas p-3">
                        <dt class="text-xs text-ui-muted">{{ fact.label }}</dt>
                        <dd class="mt-1 wrap-break-word text-sm font-semibold text-ui-ink"><bdi>{{ fact.value }}</bdi></dd>
                    </div>
                </dl>

                <div v-if="classroom.description" class="grid gap-2 border-t border-ui-line pt-4">
                    <h3 class="text-sm font-semibold text-ui-ink">توضیحات</h3>
                    <p class="wrap-break-word text-sm leading-relaxed whitespace-pre-line text-ui-ink">{{ classroom.description }}</p>
                </div>
            </PanelCard>

            <PanelCard body-class="grid gap-3 [--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-ui-ink">اعضای کلاس</h2>
                    <span class="text-sm text-ui-muted">{{ number.format(classroom.members.length) }} نفر</span>
                </div>
                <ul class="divide-y divide-ui-line border-t border-ui-line text-sm">
                    <li v-for="member in classroom.members" :key="member.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3 last:pb-0">
                        <span class="min-w-0 wrap-break-word font-medium text-ui-ink">{{ member.name }}</span>
                        <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1">
                            <span class="min-w-0 wrap-break-word text-ui-muted">{{ member.roles }}</span>
                            <Link v-if="member.performance_url" :href="member.performance_url" class="font-semibold text-brand-700 hover:underline">
                                گزارش عملکرد<span class="sr-only"> {{ member.name }}</span>
                            </Link>
                        </div>
                    </li>
                </ul>
            </PanelCard>
        </div>
    </AppLayout>
</template>
