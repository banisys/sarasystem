<script setup>
import { ElInput, ElOption, ElSelect } from 'element-plus';
import 'element-plus/es/components/input/style/css';
import 'element-plus/es/components/select/style/css';
import PanelButton from './PanelButton.vue';
import PanelDialog from './PanelDialog.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import { monthNames, today, toPersianDigits, weekdayNames } from '@/jalali.js';
import { useJalaliPicker } from '@/Components/useJalaliPicker.js';

const model = defineModel({ type: String, default: '' });
const props = defineProps({
    id: { type: String, required: true },
    minYear: { type: Number, default: () => today().year - 100 },
    maxYear: { type: Number, default: () => today().year + 10 },
});
const { isOpen, viewYear, viewMonth, years, cells, isFirstMonth, isLastMonth, todayInRange, open, close, moveMonth, pick, pickToday, clear, selected, isSameDay, isToday } = useJalaliPicker(model, props);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <ElInput :id="id" v-model="model" dir="ltr" autocomplete="off" placeholder="1370/01/05" />
        <PanelButton variant="outline" aria-label="انتخاب از تقویم" aria-haspopup="dialog" @click="open">
            <PanelIcon name="calendar" class="size-5" />
        </PanelButton>
        <PanelDialog v-model="isOpen" title="تقویم شمسی" width="min(25rem, calc(100% - 2rem))">
            <div class="flex items-center gap-2">
                <PanelButton variant="outline" size="small" :disabled="isFirstMonth" aria-label="ماه قبل" @click="moveMonth(-1)">›</PanelButton>
                <ElSelect v-model="viewMonth" aria-label="ماه" append-to="#profile-overlays">
                    <ElOption v-for="(name, index) in monthNames" :key="name" :label="name" :value="index + 1" />
                </ElSelect>
                <ElSelect v-model="viewYear" aria-label="سال" append-to="#profile-overlays">
                    <ElOption v-for="year in years" :key="year" :label="toPersianDigits(year)" :value="year" />
                </ElSelect>
                <PanelButton variant="outline" size="small" :disabled="isLastMonth" aria-label="ماه بعد" @click="moveMonth(1)">‹</PanelButton>
            </div>
            <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs text-ui-muted">
                <span v-for="name in weekdayNames" :key="name">{{ name }}</span>
            </div>
            <div class="panel-jalali-days mt-1 grid grid-cols-7 gap-1">
                <template v-for="(day, index) in cells" :key="index">
                    <span v-if="day === null" />
                    <PanelButton v-else :variant="isSameDay(selected, day) ? 'primary' : 'outline'" size="small"
                        class="min-w-0" :aria-pressed="isSameDay(selected, day)"
                        :aria-current="isToday(day) ? 'date' : undefined" @click="pick(day)">
                        {{ toPersianDigits(day) }}
                    </PanelButton>
                </template>
            </div>
            <template #footer>
                <div class="flex flex-wrap gap-2">
                    <PanelButton v-if="todayInRange" variant="outline" @click="pickToday">امروز</PanelButton>
                    <PanelButton v-if="model" variant="link" @click="clear">پاک کردن</PanelButton>
                    <PanelButton variant="ghost" class="ms-auto" @click="close">بستن</PanelButton>
                </div>
            </template>
        </PanelDialog>
    </div>
</template>
