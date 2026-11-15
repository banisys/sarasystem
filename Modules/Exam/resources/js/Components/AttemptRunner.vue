<script setup>
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelPagination from '@/Components/Ui/PanelPagination.vue';
import { ElInput, ElProgress, ElRadio, ElRadioGroup } from 'element-plus';
import 'element-plus/es/components/input/style/css';
import 'element-plus/es/components/progress/style/css';
import 'element-plus/es/components/radio-group/style/css';
import 'element-plus/es/components/radio/style/css';
import PanelConfirm from '@/Components/Ui/PanelConfirm.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
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
const confirming = ref(false);

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

function submit() {
    if (submitting.value) {
        return;
    }

    confirming.value = false;
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
        <PanelCard class="sticky top-16 z-10 lg:top-0" body-class="flex flex-wrap items-center justify-between gap-2 p-3 sm:p-4">
            <div class="min-w-0 flex-1 space-y-1.5 text-sm text-ui-muted">
                <p class="font-semibold text-ui-ink">{{ answeredCount }} از {{ attempt.questions.length }} سؤال پاسخ داده شده</p>
                <ElProgress class="max-w-48" :percentage="Math.round(100 * answeredCount / (attempt.questions.length || 1))" :show-text="false" aria-label="پیشرفت پاسخ‌گویی" />
                <div class="flex flex-wrap items-center gap-x-2 text-xs" role="status">
                    <span v-if="saveFailed" class="text-rose-700 panel-dark:text-rose-300">ذخیره نشد.</span>
                    <span v-else-if="autosave.processing">در حال ذخیره…</span>
                    <span v-else-if="savedAt" class="text-emerald-700 panel-dark:text-emerald-300">ذخیره شد</span>
                    <PanelButton v-if="saveFailed" variant="link" :disabled="autosave.processing || submitting" @click="save">تلاش دوباره</PanelButton>
                </div>
            </div>
            <div
                class="shrink-0 rounded-lg px-3 py-1 text-lg font-bold tabular-nums"
                role="timer"
                aria-label="زمان باقی‌مانده"
                :class="secondsLeft < 60 ? 'bg-rose-50 panel-dark:bg-rose-950/50 text-rose-700 panel-dark:text-rose-300' : 'bg-brand-50 text-brand-700'"
                dir="ltr"
            >
                {{ formatDuration(secondsLeft) }}
            </div>
        </PanelCard>

        <h2 ref="pageHeading" tabindex="-1" class="scroll-mt-48 text-sm font-semibold text-ui-muted focus:outline-none lg:scroll-mt-28">
            صفحهٔ {{ currentPage + 1 }} از {{ pageCount }}
        </h2>

        <PanelCard
            v-for="(question, index) in pageQuestions"
            :key="question.id"
            body-class="p-4 sm:p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0 flex-1 basis-48 whitespace-pre-line wrap-break-word font-medium leading-8 text-ui-ink">
                    <span class="text-brand-700">{{ currentPage * perPage + index + 1 }}.</span>
                    <template v-if="question.blank_parts">
                        {{ question.blank_parts[0] }}
                        <ElInput
                            v-model="autosave.answers[question.id]"
                            type="text"
                            :aria-label="`پاسخ جای خالی سؤال ${currentPage * perPage + index + 1}`" class="panel-inline-input my-1"
                        />
                        {{ question.blank_parts[1] }}
                    </template>
                    <template v-else>{{ question.body }}</template>
                </div>
                <span class="shrink-0 text-xs text-gray-500 panel-dark:text-ui-muted">{{ question.max_score }} نمره</span>
            </div>

            <div v-if="question.type === 'choice'" class="mt-4 space-y-2">
                <ElRadioGroup v-model="autosave.answers[question.id]" :aria-label="`پاسخ سؤال ${currentPage * perPage + index + 1}`" class="panel-option-stack">
                    <ElRadio v-for="option in question.options" :key="option.key" :value="option.key"
                        :name="`question-${question.id}`" border class="panel-wrap-option"
                    >{{ option.text }}</ElRadio>
                </ElRadioGroup>
                <PanelButton
                    v-if="autosave.answers[question.id]"
                    variant="link"
                    @click="autosave.answers[question.id] = null"
                >
                    پاک کردن پاسخ
                </PanelButton>
            </div>

            <ElInput type="textarea"
                v-else-if="!question.blank_parts"
                v-model="autosave.answers[question.id]"
                :rows="5" class="mt-4"
                :aria-label="`پاسخ سؤال ${currentPage * perPage + index + 1}`"
                placeholder="پاسخ خود را بنویسید"
            />
        </PanelCard>

        <PanelCard body-class="flex flex-wrap items-center justify-between gap-3 p-3">
            <nav v-if="pageCount > 1" aria-label="صفحه‌های آزمون">
                <PanelPagination :current-page="currentPage + 1" :page-count="pageCount" :disabled="submitting"
                    @update:current-page="goToPage($event - 1)" />
            </nav>
            <PanelButton :loading="submitting" aria-haspopup="dialog" @click="confirming = true">
                {{ submitting ? 'در حال ثبت پاسخ‌ها…' : 'پایان آزمون و ثبت پاسخ‌ها' }}
            </PanelButton>
        </PanelCard>
        <PanelConfirm v-model="confirming" title="پایان آزمون" confirm-label="پایان آزمون و ثبت پاسخ‌ها" @confirm="submit">
            <p v-if="answeredCount < attempt.questions.length">{{ attempt.questions.length - answeredCount }} سؤال بی‌پاسخ است. آزمون پایان یابد؟</p>
            <p v-else>آزمون پایان یابد؟</p>
        </PanelConfirm>
    </div>
</template>
