<script setup>
import { ElTable, ElTableColumn } from 'element-plus';
import 'element-plus/es/components/table/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// «کارنامه» (ADR 0032): the person's published report cards, or a ward's
// for their guardian (`ward`, ADR 0027).
const props = defineProps({
    cards: { type: Array, required: true },
    ward: { type: Object, default: null },
});

const title = computed(() => (props.ward ? `کارنامه‌ی ${props.ward.name}` : 'کارنامه'));

function pdfUrl(card) {
    return route('school.report-cards.pdf', { enrollment: card.enrollment_id, periodId: card.period_id, ...(props.ward ? { for: props.ward.id } : {}) });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout>
        <div class="space-y-4">
            <UiPageHeader :title="title" icon="academic" />

            <PanelNotice v-if="!cards.length">کارنامه‌ای منتشر نشده است.</PanelNotice>

            <section v-for="card in cards" :key="`${card.enrollment_id}-${card.period_id}`" class="ui-surface space-y-4 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">{{ card.period }} · {{ card.year }}</h2>
                    <PanelButton :href="pdfUrl(card)" full-page variant="outline">دریافت PDF</PanelButton>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span>{{ card.grade }}</span>
                    <span v-if="card.major">رشته: {{ card.major }}</span>
                    <span v-if="card.classroom">کلاس: {{ card.classroom }}</span>
                    <span>{{ card.tenant }}</span>
                </div>

                <div class="overflow-hidden rounded-ui border border-ui-line" role="region" :aria-label="`نمره‌های ${card.period}`">
                    <ElTable :data="card.rows" table-layout="auto" native-scrollbar :scrollbar-tabindex="0" flexible>
                        <ElTableColumn prop="subject" label="درس" width="140" align="right" />
                        <ElTableColumn v-if="card.scale === 'numeric'" prop="coefficient" label="ضریب" width="65" align="center" />
                        <ElTableColumn :label="card.scale === 'numeric' ? 'نمره' : 'ارزشیابی'" width="90" align="center">
                            <template #default="{ row }">{{ row.result ?? '—' }}</template>
                        </ElTableColumn>
                        <ElTableColumn prop="note" label="توضیح" align="right" />
                    </ElTable>
                </div>

                <div class="flex flex-wrap gap-x-5 gap-y-2 rounded-ui bg-ui-canvas p-3 text-sm">
                    <span v-if="card.scale === 'numeric'" class="font-semibold text-gray-900">معدل: {{ card.average ?? '—' }}</span>
                    <span class="text-gray-700">غیبت: {{ card.absences }}<span v-if="card.excused"> (+{{ card.excused }} موجه)</span></span>
                    <span class="text-gray-700">تأخیر: {{ card.lates }}</span>
                    <span v-if="card.homework_average != null" class="text-gray-700">میانگین تکالیف: {{ card.homework_average }} از ۲۰</span>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
