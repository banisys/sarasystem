import { computed, ref } from 'vue';
import { firstWeekday, format, monthLength, parse, today } from '@/jalali.js';

// Both date fields select the same Jalali date and submit the same YYYY/MM/DD value.
export function useJalaliPicker(model, props) {
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
    const todayInRange = computed(() => todayDate.year >= props.minYear && todayDate.year <= props.maxYear);

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

    function isToday(day) {
        return isSameDay(todayDate, day);
    }

    function dayClass(day, index) {
        if (isSameDay(selected.value, day)) return 'bg-brand-600 font-bold text-brand-contrast';

        const color = index % 7 === 6 ? 'text-red-600 panel-dark:text-red-300' : 'text-gray-800 panel-dark:text-ui-ink';

        return [color, 'hover:bg-brand-50', isToday(day) ? 'ring-1 ring-brand-400' : ''];
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

    return { isOpen, viewYear, viewMonth, years, cells, isFirstMonth, isLastMonth, todayInRange, open, close, moveMonth, selected, isSameDay, isToday, dayClass, pick, pickToday, clear };
}
