<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { firstWeekday, format, monthLength, monthNames, parse, today, toPersianDigits, weekdayNames } from '@/jalali.js';

// A Jalali date field (ADR 0015): type the date or pick it from the calendar.
// The value is "1370/01/05", the format App\Rules\JalaliDate validates.
const model = defineModel({ type: String, default: '' });

const props = defineProps({
    id: { type: String, required: true },
    // Year range offered in the calendar; the server rule still decides.
    minYear: { type: Number, default: () => today().year - 100 },
    maxYear: { type: Number, default: () => today().year + 10 },
    placeholder: { type: String, default: '1370/01/05' },
    inputClass: { type: String, default: '' },
});

const root = ref(null);
const isOpen = ref(false);
const viewYear = ref(0);
const viewMonth = ref(1);

const selected = computed(() => parse(model.value));
const todayDate = today();
const years = computed(() => Array.from({ length: props.maxYear - props.minYear + 1 }, (_, index) => props.maxYear - index));

const cells = computed(() => {
    const blanks = Array.from({ length: firstWeekday(viewYear.value, viewMonth.value) }, () => null);
    const days = Array.from({ length: monthLength(viewYear.value, viewMonth.value) }, (_, index) => index + 1);

    return [...blanks, ...days];
});

const isFirstMonth = computed(() => viewYear.value <= props.minYear && viewMonth.value === 1);
const isLastMonth = computed(() => viewYear.value >= props.maxYear && viewMonth.value === 12);
const todayInRange = todayDate.year >= props.minYear && todayDate.year <= props.maxYear;

function open() {
    if (isOpen.value) return;

    const start = selected.value ?? todayDate;
    viewYear.value = Math.min(Math.max(start.year, props.minYear), props.maxYear);
    viewMonth.value = start.month;
    isOpen.value = true;
}

function close() {
    isOpen.value = false;
}

function moveMonth(step) {
    const index = viewYear.value * 12 + viewMonth.value - 1 + step;
    viewYear.value = Math.floor(index / 12);
    viewMonth.value = (index % 12) + 1;
}

function isSameDay(date, day) {
    return date !== null && date.year === viewYear.value && date.month === viewMonth.value && date.day === day;
}

function dayClass(day, index) {
    if (isSameDay(selected.value, day)) return 'bg-violet-600 font-bold text-white';

    const color = index % 7 === 6 ? 'text-red-600' : 'text-gray-800';

    return [color, 'hover:bg-violet-50', isSameDay(todayDate, day) ? 'ring-1 ring-violet-400' : ''];
}

function pick(day) {
    model.value = format({ year: viewYear.value, month: viewMonth.value, day });
    close();
}

function pickToday() {
    model.value = format(todayDate);
    close();
}

function clear() {
    model.value = '';
    close();
}

function onOutsidePointer(event) {
    if (root.value && !root.value.contains(event.target)) close();
}

onMounted(() => document.addEventListener('pointerdown', onOutsidePointer));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutsidePointer));

const navButtonClass = 'rounded-lg px-2 py-1 text-lg leading-none text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent';
const selectClass = 'rounded-lg border border-gray-300 px-2 py-1 text-sm focus:border-violet-500 focus:ring-violet-500';
const footerButtonClass = 'rounded-lg px-2 py-1 text-sm font-medium text-violet-700 hover:bg-violet-50';
</script>

<template>
    <div ref="root" class="relative" @keydown.esc="close">
        <div class="relative">
            <input
                :id="id"
                v-model="model"
                type="text"
                dir="ltr"
                autocomplete="off"
                :placeholder="placeholder"
                :class="[inputClass, 'pl-10']"
                @focus="open"
                @click="open"
            />
            <button
                type="button"
                class="absolute inset-y-0 left-0 mt-1 flex items-center px-3 text-gray-400 hover:text-violet-600"
                tabindex="-1"
                aria-label="انتخاب از تقویم"
                @click="isOpen ? close() : open()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2" />
                    <path d="M3 10h18M8 3v4M16 3v4" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div v-if="isOpen" class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-3 shadow-lg" role="dialog" aria-label="تقویم">
            <div class="flex items-center justify-between gap-1">
                <button type="button" :class="navButtonClass" :disabled="isFirstMonth" aria-label="ماه قبل" @click="moveMonth(-1)">›</button>
                <div class="flex gap-1">
                    <select v-model.number="viewMonth" :class="selectClass" aria-label="ماه">
                        <option v-for="(name, index) in monthNames" :key="name" :value="index + 1">{{ name }}</option>
                    </select>
                    <select v-model.number="viewYear" :class="selectClass" aria-label="سال">
                        <option v-for="year in years" :key="year" :value="year">{{ toPersianDigits(year) }}</option>
                    </select>
                </div>
                <button type="button" :class="navButtonClass" :disabled="isLastMonth" aria-label="ماه بعد" @click="moveMonth(1)">‹</button>
            </div>

            <div class="mt-3 grid grid-cols-7 gap-1 text-center text-xs text-gray-500">
                <span v-for="name in weekdayNames" :key="name">{{ name }}</span>
            </div>
            <div class="mt-1 grid grid-cols-7 gap-1 text-center text-sm">
                <template v-for="(day, index) in cells" :key="index">
                    <span v-if="day === null" />
                    <button
                        v-else
                        type="button"
                        class="rounded-lg py-1.5"
                        :class="dayClass(day, index)"
                        @click="pick(day)"
                    >
                        {{ toPersianDigits(day) }}
                    </button>
                </template>
            </div>

            <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2">
                <button v-if="todayInRange" type="button" :class="footerButtonClass" @click="pickToday">امروز</button>
                <span v-else />
                <div class="flex gap-1">
                    <button v-if="model" type="button" :class="footerButtonClass" @click="clear">پاک کردن</button>
                    <button type="button" :class="footerButtonClass" @click="close">بستن</button>
                </div>
            </div>
        </div>
    </div>
</template>
