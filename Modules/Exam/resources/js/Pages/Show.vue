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
        <div class="grid gap-3 sm:gap-4">
            <UiPageHeader
                :title="exam.title"
                :description="exam.is_practice ? 'آزمون تمرینی · بدون نمره' : undefined"
                icon="clipboard"
                :back-href="attempt ? null : exam.context_url || route('exams.index')"
                :back-label="exam.context_url ? 'آزمون‌های جلسه' : 'همه‌ی آزمون‌ها'"
            />

            <PanelNotice v-for="(message, key) in page.props.errors" :key="key" tone="error">{{ message }}</PanelNotice>

            <AttemptRunner v-if="attempt" :key="attempt.id" :exam="exam" :attempt="attempt" />

            <template v-else>
                <PanelCard body-class="grid gap-3 [--el-card-padding:0.875rem] sm:gap-4 sm:[--el-card-padding:1.25rem]">
                    <p v-if="exam.description" class="whitespace-pre-line wrap-break-word text-gray-700 panel-dark:text-ui-ink">{{ exam.description }}</p>
                    <dl class="grid grid-cols-1 gap-4 rounded-ui bg-ui-canvas p-4 text-sm leading-6 min-[360px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 [&_dd]:wrap-anywhere [&_dd]:font-semibold [&_dd]:text-ui-ink [&_dt]:text-ui-muted">
                        <div><dt>تعداد سؤال</dt><dd>{{ exam.question_count }}</dd></div>
                        <div><dt>مدت</dt><dd>{{ exam.duration_minutes }} دقیقه</dd></div>
                        <div v-if="!exam.is_practice"><dt>نمره‌ی قبولی</dt><dd>{{ exam.passing_score }} از {{ exam.total_score }}</dd></div>
                        <div><dt>دفعات شرکت</dt><dd>{{ exam.is_practice ? 'نامحدود' : `${attemptsUsed} از ${exam.max_attempts}` }}</dd></div>
                        <div v-if="exam.negative_marking">
                            <dt>نمره‌ی منفی</dt>
                            <dd>هر {{ exam.negative_marking }} پاسخ غلط چندگزینه‌ای، یک پاسخ درست</dd>
                        </div>
                    </dl>

                    <dl v-if="exam.starts_at || exam.ends_at || exam.allow_late_start"
                        class="flex flex-wrap gap-x-6 gap-y-3 border-t border-ui-line px-1 pt-3 text-sm [&_dd]:mt-1 [&_dd]:font-medium [&_dd]:text-ui-ink [&_dt]:text-ui-muted">
                        <div v-if="exam.starts_at"><dt>زمان شروع</dt><dd>{{ formatDateTime(exam.starts_at) }}</dd></div>
                        <div v-if="exam.ends_at"><dt>زمان پایان</dt><dd>{{ formatDateTime(exam.ends_at) }}</dd></div>
                        <div v-if="exam.allow_late_start"><dt>مهلت شروع</dt><dd>بدون مهلت پایانی</dd></div>
                    </dl>

                    <div class="grid border-t border-ui-line pt-3 sm:pt-4">
                        <PanelNotice v-if="blocked">{{ blocked }}</PanelNotice>
                        <PanelButton v-else class="w-full justify-self-end sm:w-auto" :disabled="starting" @click="start">
                            {{ starting ? 'در حال ورود…' : exam.is_practice ? (attemptsUsed ? 'تمرین دوباره' : 'شروع تمرین') : (attemptsUsed ? 'شرکت دوباره' : 'شروع آزمون') }}
                        </PanelButton>
                    </div>
                </PanelCard>

                <PanelCard v-if="result" body-class="grid gap-3 [--el-card-padding:0.875rem] sm:gap-4 sm:[--el-card-padding:1.25rem]">
                    <div class="grid gap-1">
                        <h2 class="font-semibold text-gray-900 panel-dark:text-ui-ink">{{ exam.is_practice ? 'آخرین تمرین' : 'نتیجهٔ آخرین شرکت' }}</h2>
                        <p class="text-sm text-gray-600 panel-dark:text-ui-muted">
                            {{ formatDateTime(result.submitted_at) }}
                            <span v-if="result.timed_out">(با پایان وقت)</span>
                            — {{ result.status }}
                        </p>
                    </div>
                    <div v-if="result.score !== null || result.passed !== null" class="flex flex-wrap items-baseline gap-x-4 gap-y-1 rounded-ui bg-ui-canvas px-4 py-3">
                        <p v-if="result.score !== null" class="text-lg">
                            نمره: <strong>{{ result.score }}</strong> از {{ exam.total_score }}
                        </p>
                        <p
                            v-if="result.passed !== null"
                            class="font-medium"
                            :class="result.passed ? 'text-emerald-700 panel-dark:text-emerald-300' : 'text-rose-700 panel-dark:text-rose-300'"
                        >
                            {{ result.passed ? 'قبول' : 'مردود' }}
                        </p>
                    </div>

                    <div v-if="result.answers.length" class="grid gap-3">
                        <h3 class="font-semibold text-ui-ink">پاسخ‌ها</h3>
                        <ol class="grid gap-2 sm:gap-3">
                            <li v-for="(answer, index) in result.answers" :key="answer.id" class="grid gap-1.5 rounded-lg border border-gray-200 p-3 panel-dark:border-ui-line sm:p-4">
                                <p class="whitespace-pre-line wrap-break-word font-medium text-gray-900 panel-dark:text-ui-ink">{{ index + 1 }}. {{ answer.body }}</p>
                                <dl class="grid gap-2 text-sm" :class="{ 'sm:grid-cols-2': answer.correct_answer }">
                                    <div class="min-w-0 rounded-ui bg-ui-canvas p-3">
                                        <dt class="font-medium text-ui-muted">پاسخ شما</dt>
                                        <dd class="mt-1 whitespace-pre-wrap wrap-break-word">{{ answer.your_answer ?? 'بی‌پاسخ' }}</dd>
                                    </div>
                                    <div v-if="answer.correct_answer" class="min-w-0 rounded-ui bg-ui-canvas p-3">
                                        <dt class="font-medium text-emerald-700 panel-dark:text-emerald-300">{{ answer.type === 'descriptive' ? 'پاسخ نمونه' : 'پاسخ درست' }}</dt>
                                        <dd class="mt-1 whitespace-pre-wrap wrap-break-word">{{ answer.correct_answer }}</dd>
                                    </div>
                                </dl>
                                <PracticeSolution v-if="answer.solution_url" :url="answer.solution_url" :type="answer.type" />
                                <p v-else class="text-xs text-gray-500 panel-dark:text-ui-muted">
                                    {{ answer.score === null ? 'تصحیح نشده' : `${answer.score} از ${answer.max_score}` }}
                                </p>
                            </li>
                        </ol>
                    </div>
                </PanelCard>
            </template>
        </div>
    </AppLayout>
</template>
