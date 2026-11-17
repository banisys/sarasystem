<script setup>
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelCardLink from '@/Components/Ui/PanelCardLink.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import { ElTag } from 'element-plus';
import 'element-plus/es/components/tag/style/css';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const title = computed(() => (props.ward ? `تقویم آموزشی ${props.ward.name}` : 'تقویم آموزشی'));
const forWard = computed(() => (props.ward ? { for: props.ward.id } : {}));

const views = [
    { value: 'day', label: 'روز' },
    { value: 'week', label: 'هفته' },
    { value: 'month', label: 'ماه' },
];
const weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

const sessionCardClass = {
    scheduled: '[--panel-card-bg:var(--color-brand-50)]',
    held: '[--panel-card-bg:var(--color-emerald-50)] panel-dark:[--panel-card-bg:var(--color-emerald-950)]',
    cancelled: '[--panel-card-bg:var(--color-rose-50)] panel-dark:[--panel-card-bg:var(--color-rose-950)]',
};

const statusClass = {
    scheduled: 'border-ui-line bg-brand-50/70',
    held: 'border-emerald-200 panel-dark:border-emerald-800/70 bg-emerald-50 panel-dark:bg-emerald-950/50',
    cancelled: 'border-rose-200 panel-dark:border-rose-800/70 bg-rose-50 panel-dark:bg-rose-950/50',
};

const visibleDays = computed(() => (props.calendar.view === 'week' ? props.calendar.days : props.calendar.days.filter((day) => day.sessions.length || day.holiday || day.is_today)));

function at(date, view = props.calendar.view) {
    return route('my-classes.index', { view, date, ...forWard.value });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout>
        <div class="my-classes grid gap-4 sm:gap-5">
            <UiPageHeader :title="title" :description="calendar.label" icon="calendar">
                <template #actions>
                    <PanelButton :href="at(calendar.today)" variant="outline" class="calendar-button">امروز</PanelButton>
                </template>
            </UiPageHeader>

            <PanelCard role="navigation" aria-label="نمای تقویم و جابه‌جایی تاریخ" body-class="grid gap-2.5 [--el-card-padding:0.75rem] sm:flex sm:flex-wrap sm:items-center sm:justify-between">
                <div class="grid grid-cols-3 gap-1 rounded-ui bg-ui-canvas p-1 sm:flex">
                    <PanelButton
                        v-for="item in views"
                        :key="item.value"
                        :href="at(calendar.date, item.value)"
                        :variant="item.value === calendar.view ? 'primary' : 'ghost'"
                        :aria-current="item.value === calendar.view ? 'true' : undefined"
                        class="calendar-button"
                    >
                        {{ item.label }}
                    </PanelButton>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex">
                    <PanelButton :href="at(calendar.previous)" variant="outline" class="calendar-button">قبلی</PanelButton>
                    <PanelButton :href="at(calendar.next)" variant="outline" class="calendar-button">بعدی</PanelButton>
                </div>
            </PanelCard>

            <!-- Month: a grid of the Jalali month, Saturday first. -->
            <div v-if="calendar.view === 'month'" class="hidden grid-cols-7 gap-1.5 md:grid xl:gap-2">
                <div v-for="name in weekdays" :key="name" class="truncate px-1 py-1.5 text-center text-xs font-semibold text-gray-500 panel-dark:text-ui-muted">{{ name }}</div>
                <div v-for="blank in calendar.leading_blanks" :key="`blank-${blank}`" />
                <PanelCardLink
                    v-for="day in calendar.days"
                    :key="day.date"
                    :href="at(day.date, 'day')"
                    :aria-current="day.is_today ? 'date' : undefined"
                    :aria-label="day.label"
                    body-class="grid min-h-24 content-start gap-1 text-xs [--el-card-padding:0.5rem]"
                    :card-class="[day.is_today ? '[--panel-card-border:var(--color-brand-500)]' : '', day.holiday ? '[--panel-card-bg:var(--color-red-50)] panel-dark:[--panel-card-bg:var(--color-red-950)]' : '']"
                >
                    <div class="font-semibold" :class="day.holiday ? 'text-red-600 panel-dark:text-red-300' : 'text-gray-700 panel-dark:text-ui-ink'">{{ day.day_number }}</div>
                    <div v-if="day.holiday" class="truncate text-red-600 panel-dark:text-red-300">{{ day.holiday }}</div>
                    <div
                        v-for="session in day.sessions"
                        :key="session.id"
                        class="truncate rounded-md border px-1.5 py-1 leading-4"
                        :class="statusClass[session.status]"
                    >
                        <div v-if="session.is_exam" class="font-semibold text-amber-800 panel-dark:text-amber-300">{{ session.kind_label }}</div>
                        <div class="truncate"><bdi dir="ltr">{{ session.time.split('–')[0] }}</bdi> {{ session.class }}</div>
                    </div>
                </PanelCardLink>
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
                            :href="route('my-classes.session', { session: session.id, ...forWard })"
                            body-class="grid gap-2 text-sm [--el-card-padding:0.875rem]"
                            :card-class="sessionCardClass[session.status]"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="min-w-0 wrap-break-word font-semibold text-ui-ink">{{ session.class }}</span>
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-ui-surface px-2 py-1 font-semibold tabular-nums text-ui-muted"><PanelIcon name="clock" class="size-4" /><bdi dir="ltr">{{ session.time }}</bdi></span>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-ui-muted">
                                <span v-if="session.subject">{{ session.subject }}</span>
                                <span v-if="session.teacher">مدرس: {{ session.teacher }}</span>
                                <span v-if="session.location">{{ session.location }}</span>
                                <span v-if="session.status !== 'scheduled'">{{ session.status_label }}</span>
                                <span v-if="session.kind_label !== 'عادی'">{{ session.kind_label }}</span>
                                <span v-if="session.my_attendance" class="text-brand-700">حضور{{ ward ? '' : ' شما' }}: {{ session.my_attendance }}</span>
                                <span v-if="session.my_homework" class="text-emerald-700 panel-dark:text-emerald-300">{{ session.my_homework }}</span>
                                <span v-else-if="session.has_homework" class="text-amber-700 panel-dark:text-amber-300">تکلیف دارد</span>
                            </div>
                        </PanelCardLink>
                    </div>
                </PanelCard>
            </div>

            <section v-if="classes.length" class="grid gap-2.5">
                <h2 class="text-xl font-bold text-ui-ink">کلاس‌ها</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <PanelCard v-for="item in classes" :key="item.id" body-class="grid gap-1.5 text-sm [--el-card-padding:0.875rem] sm:[--el-card-padding:1rem]">
                        <div class="font-semibold text-ui-ink">{{ item.title }}</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-ui-muted">
                            <span>نقش: {{ item.roles }}</span>
                            <span>{{ item.mode }}</span>
                            <span v-if="item.online_platform">کلاس آنلاین: {{ item.online_platform }}</span>
                        </div>
                    </PanelCard>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.my-classes :deep(.el-button.calendar-button) {
    min-height: 2.75rem;
    padding-inline: 0.875rem;
}
</style>
