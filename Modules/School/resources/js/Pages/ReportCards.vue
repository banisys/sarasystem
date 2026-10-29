<script setup>
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
            <h1 class="text-xl font-bold text-gray-900">{{ title }}</h1>

            <p v-if="!cards.length" class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">کارنامه‌ای منتشر نشده است.</p>

            <section v-for="card in cards" :key="`${card.enrollment_id}-${card.period_id}`" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">{{ card.period }} · {{ card.year }}</h2>
                    <a :href="pdfUrl(card)" class="rounded-lg border border-violet-300 px-3 py-1 text-sm text-violet-700 hover:bg-violet-50">دریافت PDF</a>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span>{{ card.grade }}</span>
                    <span v-if="card.major">رشته: {{ card.major }}</span>
                    <span v-if="card.classroom">کلاس: {{ card.classroom }}</span>
                    <span>{{ card.tenant }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50 text-gray-700">
                                <th class="p-2 text-start">درس</th>
                                <th v-if="card.scale === 'numeric'" class="p-2">ضریب</th>
                                <th class="p-2">{{ card.scale === 'numeric' ? 'نمره' : 'ارزشیابی' }}</th>
                                <th class="p-2 text-start">توضیح</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in card.rows" :key="row.subject" class="border-b border-gray-100">
                                <td class="p-2 text-gray-900">{{ row.subject }}</td>
                                <td v-if="card.scale === 'numeric'" class="p-2 text-center text-gray-600">{{ row.coefficient }}</td>
                                <td class="p-2 text-center font-semibold text-gray-900">{{ row.result ?? '—' }}</td>
                                <td class="p-2 text-gray-600">{{ row.note }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <span v-if="card.scale === 'numeric'" class="font-semibold text-gray-900">معدل: {{ card.average ?? '—' }}</span>
                    <span class="text-gray-700">غیبت: {{ card.absences }}<span v-if="card.excused"> (+{{ card.excused }} موجه)</span></span>
                    <span class="text-gray-700">تأخیر: {{ card.lates }}</span>
                    <span v-if="card.homework_average" class="text-gray-700">میانگین تکالیف: {{ card.homework_average }} از ۲۰</span>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
