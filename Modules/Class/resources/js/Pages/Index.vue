<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    scheduled: 'border-violet-200 bg-violet-50',
    held: 'border-emerald-200 bg-emerald-50',
    cancelled: 'border-red-200 bg-red-50 line-through opacity-70',
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
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ title }}</h1>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <div class="flex overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <Link
                            v-for="item in views"
                            :key="item.value"
                            :href="at(calendar.date, item.value)"
                            class="px-3 py-1.5"
                            :class="item.value === calendar.view ? 'bg-violet-600 text-white' : 'text-gray-700 hover:bg-gray-50'"
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                    <Link :href="at(calendar.previous)" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">قبلی</Link>
                    <Link :href="at(calendar.today)" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">امروز</Link>
                    <Link :href="at(calendar.next)" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">بعدی</Link>
                </div>
            </div>

            <h2 class="text-lg font-semibold text-gray-800">{{ calendar.label }}</h2>

            <!-- Month: a grid of the Jalali month, Saturday first. -->
            <div v-if="calendar.view === 'month'" class="hidden grid-cols-7 gap-1 md:grid">
                <div v-for="name in weekdays" :key="name" class="pb-1 text-center text-xs text-gray-500">{{ name }}</div>
                <div v-for="blank in calendar.leading_blanks" :key="`blank-${blank}`" />
                <Link
                    v-for="day in calendar.days"
                    :key="day.date"
                    :href="at(day.date, 'day')"
                    class="min-h-24 rounded-lg border bg-white p-1.5 text-xs transition hover:shadow-sm"
                    :class="[day.is_today ? 'border-violet-400' : 'border-gray-200', day.holiday ? 'bg-red-50/50' : '']"
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
                <p v-if="!visibleDays.length" class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">جلسه‌ای در این بازه نیست.</p>

                <section
                    v-for="day in visibleDays"
                    :key="day.date"
                    class="rounded-2xl bg-white p-4 shadow-sm"
                    :class="day.is_today ? 'ring-2 ring-violet-300' : ''"
                >
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-gray-900">{{ day.label }}</h3>
                        <span v-if="day.is_today" class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-700">امروز</span>
                        <span v-if="day.holiday" class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">تعطیل: {{ day.holiday }}</span>
                    </div>

                    <p v-if="!day.sessions.length" class="text-sm text-gray-500">جلسه‌ای نیست.</p>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <Link
                            v-for="session in day.sessions"
                            :key="session.id"
                            :href="route('my-classes.session', { session: session.id, ...forWard })"
                            class="block rounded-xl border p-3 text-sm transition hover:shadow-sm"
                            :class="statusClass[session.status]"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold text-gray-900">{{ session.class }}</span>
                                <span class="text-gray-700" dir="ltr">{{ session.time }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                                <span v-if="session.subject">{{ session.subject }}</span>
                                <span v-if="session.teacher">مدرس: {{ session.teacher }}</span>
                                <span v-if="session.location">{{ session.location }}</span>
                                <span v-if="session.status !== 'scheduled'">{{ session.status_label }}</span>
                                <span v-if="session.kind_label !== 'عادی'">{{ session.kind_label }}</span>
                                <span v-if="session.my_attendance" class="text-violet-700">حضور{{ ward ? '' : ' شما' }}: {{ session.my_attendance }}</span>
                                <span v-if="session.my_homework" class="text-emerald-700">{{ session.my_homework }}</span>
                                <span v-else-if="session.has_homework" class="text-amber-700">تکلیف دارد</span>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>

            <section v-if="classes.length" class="space-y-2">
                <h2 class="text-lg font-semibold text-gray-800">کلاس‌ها</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div v-for="item in classes" :key="item.id" class="rounded-xl bg-white p-4 text-sm shadow-sm">
                        <div class="font-semibold text-gray-900">{{ item.title }}</div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                            <span>نقش: {{ item.roles }}</span>
                            <span>{{ item.mode }}</span>
                            <a v-if="item.meeting_url" :href="item.meeting_url" target="_blank" rel="noopener" class="text-violet-700 hover:underline">ورود به کلاس آنلاین</a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
