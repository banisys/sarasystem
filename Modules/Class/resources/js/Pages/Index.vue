<script setup>
import { ElCard, ElTag } from 'element-plus';
import 'element-plus/es/components/card/style/css';
import 'element-plus/es/components/tag/style/css';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// «کلاس‌های من» (ADR 0026): the person's sessions as a day, a week
// (Saturday to Friday) or a Jalali month, and their classes. A guardian
// sees a ward's the same way (`ward`, ADR 0027).
const props = defineProps({
    calendar: { type: Object, required: true },
    classes: { type: Array, required: true },
    ward: { type: Object, default: null },
});

const title = computed(() => (props.ward ? `کلاس‌های ${props.ward.name}` : 'کلاس‌های من'));
const forWard = computed(() => (props.ward ? { for: props.ward.id } : {}));

const views = [
    { value: 'day', label: 'روز' },
    { value: 'week', label: 'هفته' },
    { value: 'month', label: 'ماه' },
];
const weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

const statusClass = {
    scheduled: 'border-ui-line bg-brand-50/70',
    held: 'border-emerald-200 bg-emerald-50',
    cancelled: 'border-rose-200 bg-rose-50',
};

const visibleDays = computed(() => (props.calendar.view === 'week' ? props.calendar.days : props.calendar.days.filter((day) => day.sessions.length || day.holiday || day.is_today)));

function at(date, view = props.calendar.view) {
    return route('my-classes.index', { view, date, ...forWard.value });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-5 rounded-ui bg-ui-plum px-5 py-6 text-white sm:px-8 sm:py-8">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-ui bg-white/12">
                        <PanelIcon name="calendar" class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <h1 class="wrap-break-word text-2xl font-extrabold sm:text-3xl">{{ title }}</h1>
                        <h2 class="mt-1 text-sm text-white/85">{{ calendar.label }}</h2>
                    </div>
                </div>
                <PanelButton :href="at(calendar.today)" variant="outline">امروز</PanelButton>
            </header>

            <nav aria-label="نمای تقویم و جابه‌جایی تاریخ" class="ui-surface flex flex-wrap items-center justify-between gap-3 p-2.5">
                <div class="flex flex-wrap gap-1 rounded-ui bg-ui-canvas p-1">
                    <PanelButton
                        v-for="item in views"
                        :key="item.value"
                        :href="at(calendar.date, item.value)"
                        :variant="item.value === calendar.view ? 'primary' : 'ghost'"
                        :aria-current="item.value === calendar.view ? 'true' : undefined"
                        class="px-3 sm:px-4"
                    >
                        {{ item.label }}
                    </PanelButton>
                </div>
                <div class="flex flex-wrap gap-2">
                    <PanelButton :href="at(calendar.previous)" variant="outline">قبلی</PanelButton>
                    <PanelButton :href="at(calendar.next)" variant="outline">بعدی</PanelButton>
                </div>
            </nav>

            <!-- Month: a grid of the Jalali month, Saturday first. -->
            <div v-if="calendar.view === 'month'" class="hidden grid-cols-7 gap-2 md:grid">
                <div v-for="name in weekdays" :key="name" class="pb-1 text-center text-xs text-gray-500">{{ name }}</div>
                <div v-for="blank in calendar.leading_blanks" :key="`blank-${blank}`" />
                <Link
                    v-for="day in calendar.days"
                    :key="day.date"
                    :href="at(day.date, 'day')"
                    :aria-current="day.is_today ? 'date' : undefined"
                    :aria-label="day.label"
                    class="ui-card-link min-h-24 min-w-0 p-2 text-xs sm:p-2"
                    :class="[day.is_today ? 'border-brand-500' : '', day.holiday ? 'bg-red-50/50' : '']"
                >
                    <div class="font-semibold" :class="day.holiday ? 'text-red-600' : 'text-gray-700'">{{ day.day_number }}</div>
                    <div v-if="day.holiday" class="truncate text-red-600">{{ day.holiday }}</div>
                    <div
                        v-for="session in day.sessions"
                        :key="session.id"
                        class="mt-1 truncate rounded border px-1"
                        :class="statusClass[session.status]"
                    >
                        {{ session.time.split('–')[0] }} {{ session.class }}
                    </div>
                </Link>
            </div>

            <!-- Day and week (and month on small screens): one block per day. -->
            <div class="space-y-3" :class="calendar.view === 'month' ? 'md:hidden' : ''">
                <PanelNotice v-if="!visibleDays.length">جلسه‌ای در این بازه نیست.</PanelNotice>

                <section
                    v-for="day in visibleDays"
                    :key="day.date"
                    class="ui-surface p-4 sm:p-5"
                    :class="day.is_today ? 'border-brand-400' : ''"
                >
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-ui-ink">{{ day.label }}</h3>
                        <ElTag v-if="day.is_today" size="small" round>امروز</ElTag>
                        <ElTag v-if="day.holiday" type="danger" size="small" round>تعطیل: {{ day.holiday }}</ElTag>
                    </div>

                    <p v-if="!day.sessions.length" class="text-sm text-gray-500">جلسه‌ای نیست.</p>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <Link
                            v-for="session in day.sessions"
                            :key="session.id"
                            :href="route('my-classes.session', { session: session.id, ...forWard })"
                            class="block min-w-0 rounded-ui border border-s-4 p-3.5 text-sm transition-colors hover:border-brand-500 focus-visible:outline-2 focus-visible:outline-brand-600"
                            :class="statusClass[session.status]"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="min-w-0 wrap-break-word font-semibold text-ui-ink">{{ session.class }}</span>
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-white px-2 py-1 font-semibold tabular-nums text-ui-muted"><PanelIcon name="clock" class="size-4" /><bdi dir="ltr">{{ session.time }}</bdi></span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-ui-muted">
                                <span v-if="session.subject">{{ session.subject }}</span>
                                <span v-if="session.teacher">مدرس: {{ session.teacher }}</span>
                                <span v-if="session.location">{{ session.location }}</span>
                                <span v-if="session.status !== 'scheduled'">{{ session.status_label }}</span>
                                <span v-if="session.kind_label !== 'عادی'">{{ session.kind_label }}</span>
                                <span v-if="session.my_attendance" class="text-brand-700">حضور{{ ward ? '' : ' شما' }}: {{ session.my_attendance }}</span>
                                <span v-if="session.my_homework" class="text-emerald-700">{{ session.my_homework }}</span>
                                <span v-else-if="session.has_homework" class="text-amber-700">تکلیف دارد</span>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>

            <section v-if="classes.length" class="space-y-2">
                <h2 class="text-xl font-bold text-ui-ink">کلاس‌ها</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <ElCard v-for="item in classes" :key="item.id" shadow="never" class="text-sm">
                        <div class="font-semibold text-ui-ink">{{ item.title }}</div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-ui-muted">
                            <span>نقش: {{ item.roles }}</span>
                            <span>{{ item.mode }}</span>
                            <span v-if="item.online_platform">کلاس آنلاین: {{ item.online_platform }}</span>
                        </div>
                    </ElCard>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
