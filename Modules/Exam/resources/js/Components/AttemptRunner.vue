<script setup>
import { router, useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDuration } from '../format.js';

const props = defineProps({
    exam: { type: Object, required: true },
    attempt: { type: Object, required: true },
});

// Answers keyed by answer id: an option key or text. Saved while answering,
// so nothing is lost if the page closes or the time runs out.
const autosave = useHttp({
    answers: Object.fromEntries(props.attempt.questions.map((question) => [question.id, question.answer ?? null])),
});
const savedAt = ref(null);
const saveFailed = ref(false);
const submitting = ref(false);

const perPage = Math.max(1, props.exam.questions_per_page);
const pageCount = Math.max(1, Math.ceil(props.attempt.questions.length / perPage));
const currentPage = ref(0);
const pageQuestions = computed(() => props.attempt.questions.slice(currentPage.value * perPage, (currentPage.value + 1) * perPage));
const answeredCount = computed(() => Object.values(autosave.answers).filter((value) => value !== null && value !== '').length);

const endsAt = Date.now() + props.attempt.seconds_left * 1000;
const secondsLeft = ref(props.attempt.seconds_left);
let timer = null;
let saveTimeout = null;

function tick() {
    secondsLeft.value = Math.max(0, Math.round((endsAt - Date.now()) / 1000));

    if (secondsLeft.value === 0) {
        submit();
    }
}

function save() {
    clearTimeout(saveTimeout);

    autosave.put(route('exams.attempt.save', props.exam.id), {
        onSuccess: (response) => {
            savedAt.value = response.saved_at;
            saveFailed.value = false;
        },
        onError: () => {
            saveFailed.value = true;
        },
    });
}

watch(
    () => ({ ...autosave.answers }),
    () => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(save, 1500);
    },
);

function submit(askFirst = false) {
    if (submitting.value) {
        return;
    }

    const unanswered = props.attempt.questions.length - answeredCount.value;

    if (askFirst && !window.confirm(unanswered > 0 ? `${unanswered} سؤال بی‌پاسخ است. آزمون پایان یابد؟` : 'آزمون پایان یابد؟')) {
        return;
    }

    submitting.value = true;
    clearTimeout(saveTimeout);
    clearInterval(timer);

    router.post(route('exams.attempt.submit', props.exam.id), { answers: autosave.answers }, {
        onFinish: () => {
            submitting.value = false;
        },
    });
}

onMounted(() => {
    timer = setInterval(tick, 1000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    clearTimeout(saveTimeout);
});
</script>

<template>
    <div class="space-y-4">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-sm">
            <div class="text-sm text-gray-600">
                {{ answeredCount }} از {{ attempt.questions.length }} سؤال پاسخ داده شده
                <span v-if="saveFailed" class="ms-2 text-rose-600">ذخیره نشد؛ اتصال را بررسی کنید.</span>
                <span v-else-if="autosave.processing" class="ms-2 text-gray-400">در حال ذخیره…</span>
                <span v-else-if="savedAt" class="ms-2 text-emerald-600">ذخیره شد</span>
            </div>
            <div
                class="rounded-lg px-3 py-1 font-mono text-lg"
                :class="secondsLeft < 60 ? 'bg-rose-50 text-rose-700' : 'bg-violet-50 text-violet-700'"
                dir="ltr"
            >
                {{ formatDuration(secondsLeft) }}
            </div>
        </div>

        <div
            v-for="(question, index) in pageQuestions"
            :key="question.id"
            class="rounded-2xl bg-white p-5 shadow-sm"
        >
            <div class="flex items-start justify-between gap-3">
                <p class="whitespace-pre-line font-medium text-gray-900">
                    <span class="text-violet-700">{{ currentPage * perPage + index + 1 }}.</span>
                    {{ question.body }}
                </p>
                <span class="shrink-0 text-xs text-gray-500">{{ question.max_score }} نمره</span>
            </div>

            <div v-if="question.type === 'choice'" class="mt-4 space-y-2">
                <label
                    v-for="option in question.options"
                    :key="option.key"
                    class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2"
                    :class="autosave.answers[question.id] === option.key ? 'border-violet-500 bg-violet-50' : 'border-gray-200 hover:bg-gray-50'"
                >
                    <input
                        v-model="autosave.answers[question.id]"
                        type="radio"
                        :name="`question-${question.id}`"
                        :value="option.key"
                        class="text-violet-600"
                    />
                    <span>{{ option.text }}</span>
                </label>
                <button
                    v-if="autosave.answers[question.id]"
                    type="button"
                    class="text-xs text-gray-500 hover:text-gray-800"
                    @click="autosave.answers[question.id] = null"
                >
                    پاک کردن پاسخ
                </button>
            </div>

            <textarea
                v-else
                v-model="autosave.answers[question.id]"
                rows="5"
                class="mt-4 w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
                placeholder="پاسخ خود را بنویسید"
            />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm disabled:opacity-40"
                    :disabled="currentPage === 0"
                    @click="currentPage--"
                >
                    قبلی
                </button>
                <span class="text-sm text-gray-600">صفحه‌ی {{ currentPage + 1 }} از {{ pageCount }}</span>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm disabled:opacity-40"
                    :disabled="currentPage >= pageCount - 1"
                    @click="currentPage++"
                >
                    بعدی
                </button>
            </div>
            <button
                type="button"
                class="rounded-lg bg-violet-600 px-5 py-2 font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                :disabled="submitting"
                @click="submit(true)"
            >
                پایان آزمون و ثبت پاسخ‌ها
            </button>
        </div>
    </div>
</template>
