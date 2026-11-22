<script setup>
import PanelCard from '@/Components/Ui/PanelCard.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttemptRunner from '../Components/AttemptRunner.vue';
import PracticeSolution from '../Components/PracticeSolution.vue';
import { formatDateTime } from '../format.js';

const props = defineProps({
    exam: { type: Object, required: true },
    attemptsUsed: { type: Number, required: true },
    blocked: { type: String, default: null },
    attempt: { type: Object, default: null },
    result: { type: Object, default: null },
});

const page = usePage();
const starting = ref(false);

function start() {
    starting.value = true;
    router.post(route('exams.attempt.start', props.exam.id), {}, {
        onFinish: () => {
            starting.value = false;
        },
    });
}
</script>

<template>
    <Head :title="exam.title" />

    <AppLayout>
        <div class="space-y-4">
            <UiPageHeader
                :title="exam.title"
                :description="exam.is_practice ? 'آزمون تمرینی · بدون نمره' : undefined"
                icon="clipboard"
                :back-href="attempt ? null : exam.context_url || route('exams.index')"
                :back-label="exam.context_url ? 'آزمون‌های جلسه' : 'همه‌ی آزمون‌ها'"
            />

            <PanelNotice v-if="page.props.flash.status" tone="success">{{ page.props.flash.status }}</PanelNotice>
            <PanelNotice v-for="(message, key) in page.props.errors" :key="key" tone="error">{{ message }}</PanelNotice>

            <AttemptRunner v-if="attempt" :key="attempt.id" :exam="exam" :attempt="attempt" />

            <template v-else>
                <PanelCard body-class="p-4 sm:p-6">
                    <p v-if="exam.description" class="mb-4 whitespace-pre-line text-gray-700 panel-dark:text-ui-ink">{{ exam.description }}</p>
                    <dl class="grid grid-cols-2 gap-3 rounded-ui bg-ui-canvas p-3 text-sm leading-7 sm:grid-cols-3 sm:p-4">
                        <div><dt class="text-gray-500 panel-dark:text-ui-muted">تعداد سؤال</dt><dd>{{ exam.question_count }}</dd></div>
                        <div><dt class="text-gray-500 panel-dark:text-ui-muted">مدت</dt><dd>{{ exam.duration_minutes }} دقیقه</dd></div>
                        <div v-if="!exam.is_practice"><dt class="text-gray-500 panel-dark:text-ui-muted">نمره‌ی قبولی</dt><dd>{{ exam.passing_score }} از {{ exam.total_score }}</dd></div>
                        <div v-if="exam.starts_at"><dt class="text-gray-500 panel-dark:text-ui-muted">از</dt><dd>{{ formatDateTime(exam.starts_at) }}</dd></div>
                        <div v-if="exam.ends_at"><dt class="text-gray-500 panel-dark:text-ui-muted">تا</dt><dd>{{ formatDateTime(exam.ends_at) }}</dd></div>
                        <div v-if="exam.allow_late_start"><dt class="text-gray-500 panel-dark:text-ui-muted">مهلت شروع</dt><dd>بدون مهلت پایانی</dd></div>
                        <div><dt class="text-gray-500 panel-dark:text-ui-muted">دفعات شرکت</dt><dd>{{ exam.is_practice ? 'نامحدود' : `${attemptsUsed} از ${exam.max_attempts}` }}</dd></div>
                        <div v-if="exam.negative_marking">
                            <dt class="text-gray-500 panel-dark:text-ui-muted">نمره‌ی منفی</dt>
                            <dd>هر {{ exam.negative_marking }} پاسخ غلط چندگزینه‌ای، یک پاسخ درست</dd>
                        </div>
                    </dl>

                    <div class="mt-6">
                        <PanelNotice v-if="blocked">{{ blocked }}</PanelNotice>
                        <PanelButton v-else :disabled="starting" @click="start">
                            {{ starting ? 'در حال ورود…' : exam.is_practice ? (attemptsUsed ? 'تمرین دوباره' : 'شروع تمرین') : (attemptsUsed ? 'شرکت دوباره' : 'شروع آزمون') }}
                        </PanelButton>
                    </div>
                </PanelCard>

                <PanelCard v-if="result" body-class="p-4 sm:p-6">
                    <h2 class="font-semibold text-gray-900 panel-dark:text-ui-ink">{{ exam.is_practice ? 'آخرین تمرین' : 'آخرین شرکت' }}</h2>
                    <p class="mt-2 text-sm text-gray-600 panel-dark:text-ui-muted">
                        {{ formatDateTime(result.submitted_at) }}
                        <span v-if="result.timed_out">(با پایان وقت)</span>
                        — {{ result.status }}
                    </p>
                    <p v-if="result.score !== null" class="mt-3 text-lg">
                        نمره: <strong>{{ result.score }}</strong> از {{ exam.total_score }}
                    </p>
                    <p
                        v-if="result.passed !== null"
                        class="mt-2 font-medium"
                        :class="result.passed ? 'text-emerald-700 panel-dark:text-emerald-300' : 'text-rose-700 panel-dark:text-rose-300'"
                    >
                        {{ result.passed ? 'قبول' : 'مردود' }}
                    </p>

                    <ol v-if="result.answers.length" class="mt-6 space-y-3">
                        <li v-for="(answer, index) in result.answers" :key="answer.id" class="rounded-lg border border-gray-200 panel-dark:border-ui-line p-4">
                            <p class="whitespace-pre-line wrap-break-word font-medium text-gray-900 panel-dark:text-ui-ink">{{ index + 1 }}. {{ answer.body }}</p>
                            <p class="mt-2 whitespace-pre-line wrap-break-word text-sm">
                                <span class="text-gray-500 panel-dark:text-ui-muted">پاسخ شما:</span> {{ answer.your_answer ?? 'بی‌پاسخ' }}
                            </p>
                            <p v-if="answer.correct_answer" class="mt-1 whitespace-pre-wrap wrap-break-word text-sm text-emerald-700 panel-dark:text-emerald-300">
                                <span class="text-gray-500 panel-dark:text-ui-muted">{{ answer.type === 'descriptive' ? 'پاسخ نمونه:' : 'پاسخ درست:' }}</span> {{ answer.correct_answer }}
                            </p>
                            <PracticeSolution v-if="answer.solution_url" :url="answer.solution_url" :type="answer.type" />
                            <p v-else class="mt-1 text-xs text-gray-500 panel-dark:text-ui-muted">
                                {{ answer.score === null ? 'تصحیح نشده' : `${answer.score} از ${answer.max_score}` }}
                            </p>
                        </li>
                    </ol>
                </PanelCard>
            </template>
        </div>
    </AppLayout>
</template>
