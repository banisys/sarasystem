<script setup>
import UiButton from '@/Components/Ui/UiButton.vue';
import { router, useHttp } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
const pageHeading = ref(null);

async function goToPage(page) {
    currentPage.value = page;
    await nextTick();
    pageHeading.value?.focus();
}
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

    void autosave.put(route('exams.attempt.save', props.exam.id), {
        onSuccess: (response) => {
            savedAt.value = response.saved_at;
            saveFailed.value = false;
        },
        onError: () => {
            saveFailed.value = true;
        },
    }).catch(() => {
        saveFailed.value = true;
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
        <div class="ui-surface sticky top-16 z-10 flex flex-wrap items-center justify-between gap-2 p-3 sm:p-4 lg:top-0">
            <div class="min-w-0 flex-1 space-y-1.5 text-sm text-ui-muted">
                <p class="font-semibold text-ui-ink">{{ answeredCount }} از {{ attempt.questions.length }} سؤال پاسخ داده شده</p>
                <progress class="block h-1.5 w-full max-w-48 accent-brand-600" :value="answeredCount" :max="attempt.questions.length || 1" aria-label="پیشرفت پاسخ‌گویی" />
                <div class="flex flex-wrap items-center gap-x-2 text-xs" role="status">
                    <span v-if="saveFailed" class="text-rose-700">ذخیره نشد.</span>
                    <span v-else-if="autosave.processing">در حال ذخیره…</span>
                    <span v-else-if="savedAt" class="text-emerald-700">ذخیره شد</span>
                    <button v-if="saveFailed" type="button" class="min-h-8 text-brand-700 underline" :disabled="autosave.processing || submitting" @click="save">تلاش دوباره</button>
                </div>
            </div>
            <div
                class="shrink-0 rounded-lg px-3 py-1 text-lg font-bold tabular-nums"
                role="timer"
                aria-label="زمان باقی‌مانده"
                :class="secondsLeft < 60 ? 'bg-rose-50 text-rose-700' : 'bg-violet-50 text-violet-700'"
                dir="ltr"
            >
                {{ formatDuration(secondsLeft) }}
            </div>
        </div>

        <h2 ref="pageHeading" tabindex="-1" class="scroll-mt-48 text-sm font-semibold text-ui-muted focus:outline-none lg:scroll-mt-28">
            صفحهٔ {{ currentPage + 1 }} از {{ pageCount }}
        </h2>

        <div
            v-for="(question, index) in pageQuestions"
            :key="question.id"
            class="ui-surface p-4 sm:p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="min-w-0 flex-1 basis-48 whitespace-pre-line wrap-break-word font-medium leading-8 text-ui-ink">
                    <span class="text-violet-700">{{ currentPage * perPage + index + 1 }}.</span>
                    <template v-if="question.blank_parts">
                        {{ question.blank_parts[0] }}
                        <input
                            v-model="autosave.answers[question.id]"
                            type="text"
                            :aria-label="`پاسخ جای خالی سؤال ${currentPage * perPage + index + 1}`"
                            class="ui-field my-1 inline-block w-36 max-w-full"
                        />
                        {{ question.blank_parts[1] }}
                    </template>
                    <template v-else>{{ question.body }}</template>
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
                v-else-if="!question.blank_parts"
                v-model="autosave.answers[question.id]"
                rows="5"
                class="ui-field mt-4"
                :aria-label="`پاسخ سؤال ${currentPage * perPage + index + 1}`"
                placeholder="پاسخ خود را بنویسید"
            />
        </div>

        <div class="ui-surface flex flex-wrap items-center justify-between gap-3 p-3">
            <nav v-if="pageCount > 1" aria-label="صفحه‌های آزمون" class="flex flex-wrap items-center gap-2">
                <UiButton variant="outline" :disabled="currentPage === 0 || submitting" @click="goToPage(currentPage - 1)">قبلی</UiButton>
                <UiButton variant="outline" :disabled="currentPage >= pageCount - 1 || submitting" @click="goToPage(currentPage + 1)">بعدی</UiButton>
            </nav>
            <UiButton :disabled="submitting" @click="submit(true)">
                {{ submitting ? 'در حال ثبت پاسخ‌ها…' : 'پایان آزمون و ثبت پاسخ‌ها' }}
            </UiButton>
        </div>
    </div>
</template>
