<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttemptRunner from '../Components/AttemptRunner.vue';
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
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ exam.title }}</h1>
                <Link :href="route('exams.index')" class="text-sm text-gray-500 hover:text-gray-800">همه‌ی آزمون‌ها</Link>
            </div>

            <p v-if="page.props.flash.status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </p>
            <p
                v-for="(message, key) in page.props.errors"
                :key="key"
                class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"
            >
                {{ message }}
            </p>

            <AttemptRunner v-if="attempt" :key="attempt.id" :exam="exam" :attempt="attempt" />

            <template v-else>
                <div class="rounded-2xl bg-white p-6 shadow-sm">
                    <p v-if="exam.description" class="mb-4 whitespace-pre-line text-gray-700">{{ exam.description }}</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                        <div><dt class="text-gray-500">تعداد سؤال</dt><dd>{{ exam.question_count }}</dd></div>
                        <div><dt class="text-gray-500">مدت</dt><dd>{{ exam.duration_minutes }} دقیقه</dd></div>
                        <div><dt class="text-gray-500">نمره‌ی قبولی</dt><dd>{{ exam.passing_score }} از {{ exam.total_score }}</dd></div>
                        <div v-if="exam.starts_at"><dt class="text-gray-500">از</dt><dd>{{ formatDateTime(exam.starts_at) }}</dd></div>
                        <div v-if="exam.ends_at"><dt class="text-gray-500">تا</dt><dd>{{ formatDateTime(exam.ends_at) }}</dd></div>
                        <div><dt class="text-gray-500">دفعات شرکت</dt><dd>{{ attemptsUsed }} از {{ exam.max_attempts }}</dd></div>
                        <div v-if="exam.negative_marking">
                            <dt class="text-gray-500">نمره‌ی منفی</dt>
                            <dd>هر {{ exam.negative_marking }} پاسخ غلط، یک پاسخ درست</dd>
                        </div>
                    </dl>

                    <div class="mt-6">
                        <p v-if="blocked" class="text-sm text-gray-600">{{ blocked }}</p>
                        <button
                            v-else
                            type="button"
                            class="rounded-lg bg-violet-600 px-5 py-2 font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                            :disabled="starting"
                            @click="start"
                        >
                            {{ attemptsUsed ? 'شرکت دوباره' : 'شروع آزمون' }}
                        </button>
                    </div>
                </div>

                <div v-if="result" class="rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="font-semibold text-gray-900">آخرین شرکت</h2>
                    <p class="mt-2 text-sm text-gray-600">
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
                        :class="result.passed ? 'text-emerald-700' : 'text-rose-700'"
                    >
                        {{ result.passed ? 'قبول' : 'مردود' }}
                    </p>

                    <ol v-if="result.answers.length" class="mt-6 space-y-3">
                        <li v-for="(answer, index) in result.answers" :key="answer.id" class="rounded-lg border border-gray-200 p-4">
                            <p class="whitespace-pre-line font-medium text-gray-900">{{ index + 1 }}. {{ answer.body }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm">
                                <span class="text-gray-500">پاسخ شما:</span> {{ answer.your_answer ?? 'بی‌پاسخ' }}
                            </p>
                            <p v-if="answer.correct_answer" class="mt-1 text-sm text-emerald-700">
                                <span class="text-gray-500">پاسخ درست:</span> {{ answer.correct_answer }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ answer.score === null ? 'تصحیح نشده' : `${answer.score} از ${answer.max_score}` }}
                            </p>
                        </li>
                    </ol>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
