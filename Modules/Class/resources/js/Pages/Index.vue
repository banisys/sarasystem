<script setup>
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelCardLink from '@/Components/Ui/PanelCardLink.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import { ElOption, ElSelect, ElTag } from 'element-plus';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/tag/style/css';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// «تقویم آموزشی» (ADR 0026): the person's sessions as a day, a week
// (Saturday to Friday) or a Jalali month, and their classes. A guardian
// sees a ward's the same way (`ward`, ADR 0027).
const props = defineProps({
    calendar: { type: Object, required: true },
    classes: { type: Array, required: true },
    ward: { type: Object, default: null },
});

usePoll(60_000, { only: ['calendar', 'classes'] });

const title = computed(() => (props.ward ? `تقویم آموزشی ${props.ward.name}` : 'تقویم آموزشی'));
const forWard = computed(() => (props.ward ? { for: props.ward.id } : {}));
const filtering = ref(false);

const views = [
    { value: 'day', label: 'روز' },
    { value: 'week', label: 'هفته' },
    { value: 'month', label: 'ماه' },
];
const weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

const sessionToneClass = {
    scheduled: 'border-s-blue-500! bg-blue-50! text-blue-950! hover:bg-blue-100! panel-dark:bg-blue-500/15! panel-dark:text-blue-100! panel-dark:hover:bg-blue-500/25!',
    live: 'border-s-violet-600! bg-violet-100! text-violet-950! ring-2 ring-violet-400/60 hover:bg-violet-200! panel-dark:bg-violet-500/25! panel-dark:text-violet-50!',
    pending: 'border-s-amber-500! bg-amber-50! text-amber-950! hover:bg-amber-100! panel-dark:bg-amber-500/15! panel-dark:text-amber-100! panel-dark:hover:bg-amber-500/25!',
    held: 'border-s-emerald-500! bg-emerald-50! text-emerald-950! hover:bg-emerald-100! panel-dark:bg-emerald-500/15! panel-dark:text-emerald-100! panel-dark:hover:bg-emerald-500/25!',
    cancelled: 'border-s-rose-400! bg-rose-50/60! text-rose-800! opacity-75 hover:opacity-100 panel-dark:bg-rose-500/10! panel-dark:text-rose-200!',
};

const liveTagClass = '[--el-tag-bg-color:var(--color-violet-50)]! [--el-tag-border-color:var(--color-violet-300)]! [--el-tag-text-color:var(--color-violet-800)]! panel-dark:[--el-tag-bg-color:var(--color-violet-950)]! panel-dark:[--el-tag-border-color:var(--color-violet-700)]! panel-dark:[--el-tag-text-color:var(--color-violet-200)]!';
const examTagClass = '[--el-tag-bg-color:var(--color-fuchsia-700)]! [--el-tag-border-color:transparent]! [--el-tag-text-color:white]! panel-dark:[--el-tag-bg-color:var(--color-fuchsia-300)]! panel-dark:[--el-tag-text-color:var(--color-fuchsia-950)]!';

const visibleDays = computed(() => (props.calendar.view === 'week' ? props.calendar.days : props.calendar.days.filter((day) => day.sessions.length || day.holiday || day.is_today)));

function at(date, view = props.calendar.view, { classroom, subject, mode } = props.calendar.filters) {
    return route('my-classes.index', { view, date, classroom, subject, mode, ...forWard.value });
}

function sessionUrl(session) {
    return route('my-classes.session', { session: session.id, ...forWard.value });
}

function setFilter(key, value) {
    const { date, view, filters } = props.calendar;
    router.visit(at(date, view, { ...filters, [key]: value === '' ? null : value }), {
        only: ['calendar', 'classes'],
        preserveScroll: true,
        preserveState: true,
        onStart: () => { filtering.value = true; },
        onFinish: () => { filtering.value = false; },
    });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout>
        <div class="grid gap-4 sm:gap-5">
            <UiPageHeader :title="title" icon="calendar" />

            <section aria-label="برنامهٔ جلسات" class="grid gap-7 rounded-ui border border-ui-line bg-ui-surface p-3 sm:gap-9 sm:p-4">
                <div class="grid gap-3">
                    <PanelCard role="navigation" aria-label="نمای تقویم و جابه‌جایی تاریخ" body-class="grid gap-2.5 [--el-card-padding:0.75rem] sm:flex sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="calendar-views grid grid-cols-3 gap-1 rounded-ui bg-ui-canvas p-1 sm:flex">
                            <PanelButton
                                v-for="item in views"
                                :key="item.value"
                                :href="at(calendar.date, item.value)"
                                :variant="item.value === calendar.view ? 'primary' : 'ghost'"
                                :aria-current="item.value === calendar.view ? 'true' : undefined"
                                size="default"
                            >
                                {{ item.label }}
                            </PanelButton>
                        </div>
                        <h2 class="text-center text-base font-semibold text-ui-ink sm:me-auto sm:ms-4 sm:text-start">{{ calendar.label }}</h2>
                        <div class="flex justify-center gap-2">
                            <PanelButton :href="at(calendar.previous)" aria-label="قبلی" variant="outline" size="default" class="size-8! p-0!">
                                <PanelIcon name="chevron-right" class="size-4 shrink-0" />
                            </PanelButton>
                            <PanelButton :href="at(calendar.today)" variant="outline" size="default"><PanelIcon name="calendar" class="size-4 shrink-0" />امروز</PanelButton>
                            <PanelButton :href="at(calendar.next)" aria-label="بعدی" variant="outline" size="default" class="size-8! p-0!">
                                <PanelIcon name="chevron-right" class="size-4 shrink-0 rotate-180" />
                            </PanelButton>
                        </div>
                    </PanelCard>
                    <div
                        class="calendar-filters grid grid-cols-1 gap-2 min-[360px]:grid-cols-2 sm:flex sm:flex-wrap sm:justify-end"
                        role="group"
                        aria-label="فیلترهای جلسات"
                    >
                        <ElSelect
                            :model-value="calendar.filters.classroom ?? ''"
                            :empty-values="[null, undefined]"
                            :disabled="filtering || !classes.length"
                            aria-label="فیلتر بر اساس کلاس"
                            :teleported="false"
                            class="min-w-0 min-[360px]:col-span-2 sm:w-48!"
                            @change="setFilter('classroom', $event)"
                        >
                            <ElOption value="" label="همهٔ کلاس‌ها" />
                            <ElOption v-for="item in classes" :key="item.id" :value="item.id" :label="item.title" />
                        </ElSelect>
                        <ElSelect
                            :model-value="calendar.filters.subject ?? ''"
                            :empty-values="[null, undefined]"
                            :disabled="filtering || !calendar.filters.subjects.length"
                            aria-label="فیلتر بر اساس درس"
                            :teleported="false"
                            class="min-w-0 sm:w-48!"
                            @change="setFilter('subject', $event)"
                        >
                            <ElOption value="" label="همهٔ درس‌ها" />
                            <ElOption v-for="option in calendar.filters.subjects" :key="option.value" v-bind="option" />
                        </ElSelect>
                        <ElSelect
                            :model-value="calendar.filters.mode ?? ''"
                            :empty-values="[null, undefined]"
                            :disabled="filtering"
                            aria-label="فیلتر بر اساس روش برگزاری"
                            :teleported="false"
                            class="min-w-0 sm:w-48!"
                            @change="setFilter('mode', $event)"
                        >
                            <ElOption value="" label="همهٔ روش‌های برگزاری" />
                            <ElOption v-for="option in calendar.filters.modes" :key="option.value" v-bind="option" />
                        </ElSelect>
                    </div>
                </div>

                <!-- Month: a grid of the Jalali month, Saturday first. -->
                <div v-if="calendar.view === 'month'" class="hidden grid-cols-7 gap-1.5 md:grid xl:gap-2">
                    <div v-for="name in weekdays" :key="name" class="truncate px-1 py-1.5 text-center text-xs font-semibold text-gray-500 panel-dark:text-ui-muted">{{ name }}</div>
                    <div v-for="blank in calendar.leading_blanks" :key="`blank-${blank}`" />
                    <PanelCard
                        v-for="day in calendar.days"
                        :key="day.date"
                        body-class="grid min-h-24 content-start gap-1 text-xs [--el-card-padding:0.5rem]"
                        :class="[day.is_today ? '[--panel-card-border:var(--color-brand-500)]' : '', day.holiday ? '[--panel-card-bg:var(--color-red-50)] panel-dark:[--panel-card-bg:var(--color-red-950)]' : '']"
                    >
                        <Link
                            :href="at(day.date, 'day')"
                            :aria-current="day.is_today ? 'date' : undefined"
                            :aria-label="day.label"
                            class="justify-self-start rounded font-semibold hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                            :class="day.holiday ? 'text-red-600 panel-dark:text-red-300' : 'text-gray-700 panel-dark:text-ui-ink'"
                        >{{ day.day_number }}</Link>
                        <div v-if="day.holiday" class="truncate text-red-600 panel-dark:text-red-300">{{ day.holiday }}</div>
                        <Link
                            v-for="session in day.sessions"
                            :key="session.id"
                            :href="sessionUrl(session)"
                            class="min-w-0 overflow-hidden rounded-md border border-ui-line border-s-4 px-1.5 py-1 leading-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                            :class="sessionToneClass[session.tone]"
                            :title="session.tone_label"
                        >
                            <ElTag v-if="session.is_live" size="small" round :class="liveTagClass">
                                <span class="flex items-center gap-1">
                                    <span aria-hidden="true" class="relative size-2 shrink-0">
                                        <span class="absolute inset-0 rounded-full bg-current motion-safe:animate-ping" />
                                        <span class="absolute inset-0 rounded-full bg-current" />
                                    </span>
                                    {{ session.tone_label }}
                                </span>
                            </ElTag>
                            <ElTag v-if="session.is_exam" size="small" round class="mb-1 max-w-full" :class="examTagClass">
                                <span class="flex items-center gap-1"><PanelIcon name="clipboard" class="size-3 shrink-0" />{{ session.kind_label }}</span>
                            </ElTag>
                            <div :class="{ 'line-through': session.tone === 'cancelled' }">
                                <div class="truncate"><bdi dir="ltr">{{ session.time.split('–')[0] }}</bdi> {{ session.title ?? session.class }}</div>
                                <div v-if="session.subject" class="wrap-break-word font-medium">{{ session.subject }}</div>
                            </div>
                        </Link>
                    </PanelCard>
                </div>

                <!-- Day and week (and month on small screens): one block per day. -->
                <div class="grid gap-3" :class="calendar.view === 'month' ? 'md:hidden' : ''">
                    <PanelEmpty v-if="!visibleDays.length" description="جلسه‌ای در این بازه نیست." />

                    <PanelCard
                        v-for="day in visibleDays"
                        :key="day.date"
                        body-class="grid gap-2.5 [--el-card-padding:0.875rem] sm:[--el-card-padding:1rem]"
                        :class="day.is_today ? '[--panel-card-border:var(--color-brand-400)]' : ''"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-ui-ink">{{ day.label }}</h3>
                            <ElTag v-if="day.is_today" size="small" round>امروز</ElTag>
                            <ElTag v-if="day.holiday" type="danger" size="small" round>تعطیل: {{ day.holiday }}</ElTag>
                        </div>

                        <p v-if="!day.sessions.length" class="text-sm text-gray-500 panel-dark:text-ui-muted">جلسه‌ای نیست.</p>

                        <div v-else class="grid gap-2.5 xl:grid-cols-2">
                            <PanelCardLink
                                v-for="session in day.sessions"
                                :key="session.id"
                                :href="sessionUrl(session)"
                                body-class="grid gap-2 text-sm [--el-card-padding:0.875rem]"
                                :card-class="['border-s-4!', sessionToneClass[session.tone]]"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="min-w-0 wrap-break-word font-semibold" :class="{ 'line-through': session.tone === 'cancelled' }">{{ session.title ?? session.class }}</span>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-ui-surface px-2 py-1 font-semibold tabular-nums text-ui-muted"><PanelIcon name="clock" class="size-4" /><bdi dir="ltr">{{ session.time }}</bdi></span>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-ui-muted">
                                    <span v-if="session.title">{{ session.class }}</span>
                                    <span v-if="session.subject">{{ session.subject }}</span>
                                    <span v-if="session.teacher">مدرس: {{ session.teacher }}</span>
                                    <span>{{ session.mode_label }}</span>
                                    <span v-if="session.location">{{ session.location }}</span>
                                    <ElTag v-if="session.is_live" size="small" round :class="liveTagClass">
                                        <span class="flex items-center gap-1">
                                            <span aria-hidden="true" class="relative size-2 shrink-0">
                                                <span class="absolute inset-0 rounded-full bg-current motion-safe:animate-ping" />
                                                <span class="absolute inset-0 rounded-full bg-current" />
                                            </span>
                                            {{ session.tone_label }}
                                        </span>
                                    </ElTag>
                                    <span v-else>{{ session.tone_label }}</span>
                                    <ElTag v-if="session.is_exam" size="small" round :class="examTagClass">
                                        <span class="flex items-center gap-1"><PanelIcon name="clipboard" class="size-3 shrink-0" />{{ session.kind_label }}</span>
                                    </ElTag>
                                    <span v-else-if="session.kind_label !== 'عادی'">{{ session.kind_label }}</span>
                                    <span v-if="session.my_attendance" class="text-brand-700 panel-dark:text-brand-300">حضور{{ ward ? '' : ' شما' }}: {{ session.my_attendance }}</span>
                                    <span v-if="session.my_homework" class="text-emerald-700 panel-dark:text-emerald-300">{{ session.my_homework }}</span>
                                    <span v-else-if="session.has_homework" class="text-amber-700 panel-dark:text-amber-300">تکلیف دارد</span>
                                </div>
                            </PanelCardLink>
                        </div>
                    </PanelCard>
                </div>
            </section>

            <section v-if="classes.length" class="grid gap-2.5">
                <h2 class="text-xl font-bold text-ui-ink">کلاس‌ها</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <PanelCard v-for="item in classes" :key="item.id" body-class="grid gap-1.5 text-sm [--el-card-padding:0.875rem] sm:[--el-card-padding:1rem]">
                        <div class="font-semibold text-ui-ink">{{ item.title }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-ui-muted">
                            <span>نقش: {{ item.roles }}</span>
                            <span v-if="item.online_platform">کلاس آنلاین: {{ item.online_platform }}</span>
                        </div>
                    </PanelCard>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.calendar-filters :deep(.el-select__wrapper),
.calendar-filters :deep(.el-select-dropdown__item) {
    text-align: right;
}

.calendar-views :deep(.el-button[aria-current="true"]) {
    --el-button-text-color: white;
    --el-button-hover-text-color: var(--el-button-text-color);
    --el-button-active-text-color: var(--el-button-text-color);
    --el-button-bg-color: #00b894;
    --el-button-border-color: var(--el-button-bg-color);
    --el-button-hover-bg-color: #00ac8a;
    --el-button-hover-border-color: var(--el-button-hover-bg-color);
    --el-button-active-bg-color: #00a080;
    --el-button-active-border-color: var(--el-button-active-bg-color);
    --el-button-outline-color: #008269;
}
</style>
