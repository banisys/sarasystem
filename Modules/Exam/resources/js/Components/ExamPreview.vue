<script setup>
import { ElInput } from 'element-plus';
import 'element-plus/es/components/input/style/css';
import PanelDialog from '@/Components/Ui/PanelDialog.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import { computed, ref } from 'vue';
import { useHttp } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';

const props = defineProps({ exam: { type: Object, required: true } });
const previewOpen = ref(false);
const questions = ref([]);
const editable = ref(false);
const shuffled = ref(false);
const dragged = ref(null);
const error = ref('');
const notice = ref('');
const changed = ref(false);
const orderChanged = ref(false);
const totalScore = ref(null);
const preview = useHttp();
const reorder = useHttp({ order: [], scores: [], revision: '', fix_order: false });
const canMove = computed(() => editable.value && !reorder.processing);
const validScores = computed(() => questions.value.every(({ score }) =>
    score >= 0.25 && score <= 1000 && /^\d+(\.\d{1,2})?$/.test(String(score)),
));
const totalCents = computed(() => Math.round(totalScore.value * 100));
const sumCents = computed(() => questions.value.reduce((sum, question) => sum + Math.round(Number(question.score) * 100), 0));
const difference = computed(() => totalCents.value - sumCents.value);
const formatScore = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 });
const displayCents = (cents) => formatScore.format(cents / 100);
let requestId = 0;

async function open() {
    const currentRequest = ++requestId;
    questions.value = [];
    editable.value = false;
    shuffled.value = false;
    error.value = '';
    notice.value = '';
    changed.value = false;
    orderChanged.value = false;
    totalScore.value = null;
    previewOpen.value = true;

    try {
        const data = await preview.get(props.exam.preview_url);
        if (!previewOpen.value || currentRequest !== requestId) return;
        questions.value = data.questions;
        editable.value = data.can_reorder;
        shuffled.value = data.shuffled;
        totalScore.value = data.total_score;
        reorder.revision = data.revision;
    } catch {
        if (previewOpen.value && currentRequest === requestId) error.value = 'دریافت پیش‌نمایش ممکن نشد. دوباره تلاش کنید.';
    }
}

function close() {
    requestId++;
    preview.cancel();
    dragged.value = null;
}

function move(from, to) {
    if (!canMove.value || from === to || from < 0 || to < 0 || to >= questions.value.length) return;
    const [question] = questions.value.splice(from, 1);
    questions.value.splice(to, 0, question);
    orderChanged.value = true;
    markChanged();
}

function markChanged() {
    changed.value = true;
    notice.value = '';
}

function startDrag(event, index) {
    if (!canMove.value || event.target.closest('input, button, label')) {
        event.preventDefault();
        return;
    }
    dragged.value = index;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(index));
}

function drop(index) {
    if (dragged.value !== null) move(dragged.value, index);
    dragged.value = null;
}

async function save(fixOrder = false) {
    if (!validScores.value) return;
    reorder.order = questions.value.map((question) => question.index);
    reorder.scores = questions.value.map((question) => Number(question.score));
    reorder.fix_order = fixOrder || orderChanged.value;
    error.value = '';
    try {
        const result = await reorder.patch(props.exam.preview_url, {
            onError: (errors) => { error.value = Object.values(errors)[0]; },
        });
        if (!result) return;
        notice.value = result.message;
        changed.value = false;
        orderChanged.value = false;
        shuffled.value = result.shuffled;
        reorder.revision = result.revision;
        questions.value = questions.value.map((question, index) => ({ ...question, index }));
    } catch {
        error.value = 'ذخیرهٔ تغییرات ممکن نشد. دوباره تلاش کنید.';
    }
}
</script>

<template>
    <div>
        <PanelButton variant="outline-brand" circle title="پیش‌نمایش سؤال‌ها" aria-label="پیش‌نمایش سؤال‌ها" aria-haspopup="dialog" @click="open">
            <PanelIcon name="clipboard" class="size-5" />
        </PanelButton>
        <PanelDialog v-model="previewOpen" title="پیش‌نمایش سؤال‌ها" :busy="reorder.processing" @close="close">
            <div class="space-y-3">
                <p class="wrap-break-word text-sm text-ui-muted">{{ exam.title }}</p>
                <p v-if="preview.processing" class="text-sm text-gray-600 panel-dark:text-ui-muted" role="status">در حال دریافت سؤال‌ها…</p>
                <p v-if="error" class="text-sm text-red-700 panel-dark:text-red-300" role="alert">{{ error }}</p>
                <p v-if="editable && questions.length > 1" class="text-sm text-gray-600 panel-dark:text-ui-muted">برای تغییر ترتیب، سؤال‌ها را بکشید یا از فلش‌های کنارشان استفاده کنید.</p>
                <p v-if="shuffled" class="text-sm text-gray-600 panel-dark:text-ui-muted">ترتیب نمایش برای شرکت‌کنندگان تصادفی است. با ذخیرهٔ یک ترتیب دستی، نمایش ثابت می‌شود.</p>
                <ol class="space-y-2">
                    <li v-for="(question, index) in questions" :key="question.index"
                        class="flex items-start gap-3 rounded-lg border border-gray-200 panel-dark:border-ui-line p-3"
                        :class="{ 'cursor-grab': canMove && questions.length > 1, 'opacity-50': dragged === index }"
                        :draggable="canMove && questions.length > 1"
                        @dragstart="startDrag($event, index)" @dragend="dragged = null" @dragover.prevent @drop.prevent="drop(index)">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gray-100 panel-dark:bg-white/8 text-sm">{{ index + 1 }}</span>
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="whitespace-pre-wrap break-words text-sm">{{ question.body }}</p>
                            <ul v-if="question.options.length" class="list-disc space-y-1 ps-5 text-sm text-gray-700 panel-dark:text-ui-ink">
                                <li v-for="(option, optionIndex) in question.options" :key="optionIndex" class="break-words">{{ option }}</li>
                            </ul>
                            <p class="text-xs text-gray-600 panel-dark:text-ui-muted">{{ question.type }}<template v-if="!editable"> · {{ formatScore.format(question.score) }} نمره</template></p>
                            <label v-if="editable" class="inline-flex items-center gap-1.5">
                                <span class="panel-field-label">بارم</span>
                                <ElInput v-model.number="question.score" type="number" min="0.25" max="1000" step="0.01" inputmode="decimal" dir="ltr"
                                    :aria-label="`بارم سؤال ${index + 1}`" :disabled="reorder.processing"
                                    size="small" class="panel-inline-input" @input="markChanged" />
                            </label>
                        </div>
                        <div v-if="editable && questions.length > 1" class="flex shrink-0 gap-1">
                            <PanelButton type="button" :disabled="!canMove || index === 0" :aria-label="`سؤال ${index + 1} به بالا`"
                                variant="outline" size="small" circle
                                @click="move(index, index - 1)">↑</PanelButton>
                            <PanelButton type="button" :disabled="!canMove || index === questions.length - 1" :aria-label="`سؤال ${index + 1} به پایین`"
                                variant="outline" size="small" circle
                                @click="move(index, index + 1)">↓</PanelButton>
                        </div>
                    </li>
                </ol>
            </div>
            <template #footer>
                <div class="space-y-3 text-start">
                    <div v-if="totalScore !== null" aria-live="polite" class="space-y-1 rounded-lg bg-gray-50 panel-dark:bg-ui-canvas px-3 py-2 text-sm">
                        <div class="flex flex-wrap gap-x-5 gap-y-1">
                            <span>جمع بارم سؤال‌ها <strong>{{ validScores ? displayCents(sumCents) : '—' }}</strong></span>
                            <span>نمرهٔ کل <strong>{{ displayCents(totalCents) }}</strong></span>
                        </div>
                        <p v-if="!validScores" class="text-red-700 panel-dark:text-red-300">بارم هر سؤال باید بین ۰٫۲۵ و ۱۰۰۰ و حداکثر با دو رقم اعشار باشد.</p>
                        <p v-else-if="difference" class="text-gray-600 panel-dark:text-ui-muted">
                            {{ displayCents(Math.abs(difference)) }} نمره {{ difference > 0 ? 'کمتر' : 'بیشتر' }} از نمرهٔ کل. نمرهٔ نهایی به مقیاس نمرهٔ کل تبدیل می‌شود.
                        </p>
                        <p v-else class="text-emerald-700 panel-dark:text-emerald-300">جمع بارم با نمرهٔ کل برابر است.</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <span v-if="notice" role="status" class="me-auto text-sm text-emerald-700 panel-dark:text-emerald-300">{{ notice }}</span>
                        <PanelButton type="button" :disabled="reorder.processing" variant="outline" @click="previewOpen = false">بستن</PanelButton>
                        <PanelButton v-if="editable && shuffled && questions.length > 1 && !orderChanged" type="button"
                            :disabled="!validScores || reorder.processing" variant="outline-brand" @click="save(true)">ثابت‌کردن ترتیب</PanelButton>
                        <PanelButton v-if="editable" type="button" :disabled="!changed || !validScores" :loading="reorder.processing" @click="save()">ذخیرهٔ تغییرات</PanelButton>
                    </div>
                </div>
            </template>
        </PanelDialog>
    </div>
</template>
