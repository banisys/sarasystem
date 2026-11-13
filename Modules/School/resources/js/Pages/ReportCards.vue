<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
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

            <UiNotice v-if="!cards.length">کارنامه‌ای منتشر نشده است.</UiNotice>

            <section v-for="card in cards" :key="`${card.enrollment_id}-${card.period_id}`" class="ui-surface space-y-4 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">{{ card.period }} · {{ card.year }}</h2>
                    <a :href="pdfUrl(card)" class="ui-button border-ui-line bg-white hover:bg-ui-canvas">دریافت PDF</a>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span>{{ card.grade }}</span>
                    <span v-if="card.major">رشته: {{ card.major }}</span>
                    <span v-if="card.classroom">کلاس: {{ card.classroom }}</span>
                    <span>{{ card.tenant }}</span>
                </div>

                <div class="overflow-x-auto rounded-ui border border-ui-line" tabindex="0" role="region" :aria-label="`نمره‌های ${card.period}`">
                    <table class="w-full min-w-80 text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50 text-gray-700">
                                <th scope="col" class="p-2 text-start">درس</th>
                                <th scope="col" v-if="card.scale === 'numeric'" class="p-2">ضریب</th>
                                <th scope="col" class="p-2">{{ card.scale === 'numeric' ? 'نمره' : 'ارزشیابی' }}</th>
                                <th scope="col" class="p-2 text-start">توضیح</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in card.rows" :key="row.subject" class="border-b border-gray-100">
                                <th scope="row" class="p-2 text-start font-medium text-ui-ink">{{ row.subject }}</th>
                                <td v-if="card.scale === 'numeric'" class="p-2 text-center text-gray-600">{{ row.coefficient }}</td>
                                <td class="p-2 text-center font-semibold text-gray-900">{{ row.result ?? '—' }}</td>
                                <td class="p-2 text-gray-600">{{ row.note }}</td>
                            </tr>
                        </tbody>
                    </table>
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
