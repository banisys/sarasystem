<script setup>
import { ElCheckbox, ElCheckboxGroup, ElForm, ElInput, ElOption, ElSelect, ElTag } from 'element-plus';
import 'element-plus/es/components/checkbox-group/style/css';
import 'element-plus/es/components/checkbox/style/css';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/tag/style/css';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelConfirm from '@/Components/Ui/PanelConfirm.vue';
import PanelDialog from '@/Components/Ui/PanelDialog.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import { computed, ref } from 'vue';
import { Head, Link, useForm, useHttp, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import ExamPreview from '../Components/ExamPreview.vue';
import SessionExamFields from '../Components/SessionExamFields.vue';
import SessionExamSettingsDialog from '../Components/SessionExamSettingsDialog.vue';
import { formatDateTime } from '../format.js';

const props = defineProps({ context: Object, banks: Array, exams: Array, results: Object, settings_form: Object });
const page = usePage();
const tabs = computed(() => [
    { key: 'exams', label: 'لیست آزمون‌های ساخته‌شده', icon: 'clipboard' },
    ...(props.context.can_manage ? [{ key: 'create', label: 'ساخت آزمون از بانک سؤال', icon: 'book' }] : []),
    ...(props.results ? [{ key: 'results', label: 'نتایج و تصحیح', icon: 'pencil' }] : []),
]);
const requestedTab = new URLSearchParams(page.url.split('?')[1]).get('tab');
const tab = ref(tabs.value.some((item) => item.key === requestedTab) ? requestedTab : tabs.value[0].key);

const bankRow = () => ({ bank_id: '', question_count: 1 });
const generation = useForm({ ...props.settings_form?.defaults, banks: [bankRow()] });
const bankFor = (id) => props.banks.find((bank) => bank.id === id);
const pickerOpen = ref(false);
const editingRow = ref(null);
const candidates = ref([]);
const checked = ref([]);
const loadError = ref(false);
const questionRequest = useHttp();
let requestId = 0;
const taken = computed(() => new Set(generation.banks.flatMap((row) =>
    row === editingRow.value ? [] : (row.question_ids ?? []),
)));

async function openQuestions(row) {
    const currentRequest = ++requestId;
    editingRow.value = row;
    candidates.value = [];
    checked.value = [];
    loadError.value = false;
    pickerOpen.value = true;

    try {
        const { questions } = await questionRequest.get(route('exams.context.bank-questions', [...parameters(), row.bank_id]));
        if (!pickerOpen.value || currentRequest !== requestId) return;

        candidates.value = questions;
        if (row.question_ids) {
            checked.value = questions.filter(({ id }) => row.question_ids.includes(id) && !taken.value.has(id)).map(({ id }) => id);
        } else {
            const available = questions.filter(({ id }) => !taken.value.has(id)).map(({ id }) => id);
            const count = Math.min(50, Math.max(1, Number(row.question_count) || 1));
            for (let index = 0; index < Math.min(count, available.length); index++) {
                const other = index + Math.floor(Math.random() * (available.length - index));
                [available[index], available[other]] = [available[other], available[index]];
            }
            checked.value = available.slice(0, count);
        }
    } catch {
        if (pickerOpen.value && currentRequest === requestId) loadError.value = true;
    }
}

function closeQuestions() {
    requestId++;
    questionRequest.cancel();
    editingRow.value = null;
}

function applyQuestions() {
    editingRow.value.question_ids = [...checked.value];
    editingRow.value.question_count = checked.value.length;
    pickerOpen.value = false;
}
function generate() {
    generation.post(route('exams.context.store', parameters()), {
        preserveScroll: true,
        onSuccess: () => {
            generation.reset();
            tab.value = 'exams';
        },
    });
}
const publication = useForm({ is_published: false });
const deletion = useForm({});
const removalOpen = ref(false);
const removingExam = ref(null);
const parameters = () => [props.context.type, props.context.id];
function publish(exam) {
    publication.is_published = !exam.is_published;
    publication.patch(route('exams.context.publish', [...parameters(), exam.id]), { preserveScroll: true });
}
function confirmRemoval(exam) {
    removingExam.value = exam;
    deletion.clearErrors();
    removalOpen.value = true;
}

function remove() {
    deletion.delete(removingExam.value.delete_url, {
        preserveScroll: true,
        onSuccess: () => { removalOpen.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="آزمون‌های جلسه" />
        <div class="space-y-6">
            <UiPageHeader
                title="آزمون‌های جلسه"
                :description="context.title"
                icon="clipboard"
                tone="brand"
                :back-href="context.back_url"
                full-page-back
                back-label="بازگشت به جلسه"
            />
            <PanelNotice v-if="page.props.flash.status" tone="success">{{ page.props.flash.status }}</PanelNotice>
            <nav v-if="tabs.length > 1" class="ui-surface flex flex-wrap gap-1 p-2" aria-label="بخش‌های آزمون‌های جلسه">
                <PanelButton
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    :aria-pressed="tab === item.key"
                    :variant="tab === item.key ? 'primary' : 'ghost'" class="panel-wrap-button"
                    @click="tab = item.key"
                >
                    <PanelIcon :name="item.icon" class="size-5" />
                    {{ item.label }}
                </PanelButton>
            </nav>
            <section v-if="context.can_manage" v-show="tab === 'create'" aria-label="ساخت آزمون از بانک سؤال" class="space-y-4 ui-surface p-4 sm:p-5">
                <p v-if="!banks.some((bank) => bank.question_count > 0)" class="text-sm text-gray-600 panel-dark:text-ui-muted">
                    برای ساخت آزمون، در بانک خودتان سؤال فعال ثبت کنید یا از یک بانک عمومیِ دارای سؤال استفاده کنید.
                </p>
                <ElForm v-else novalidate class="space-y-4" @submit.prevent="generate">
                    <SessionExamFields :form="generation" :settings="settings_form" />
                    <div v-for="(row, index) in generation.banks" :key="index" class="grid items-end gap-3 rounded-ui bg-ui-canvas p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                        <div class="min-w-0 space-y-1 text-sm font-medium">
                            <label :for="`exam-bank-${index}`">بانک سؤال</label>
                            <ElSelect :id="`exam-bank-${index}`" v-model="row.bank_id" placeholder="انتخاب کنید" append-to="#profile-overlays" @change="delete row.question_ids">
                                <ElOption v-for="bank in banks" :key="bank.id" :value="bank.id"
                                    :label="`${bank.title} · ${bank.is_mine ? 'بانک من' : 'عمومی'} · ${bank.question_count} سؤال`"
                                    :disabled="!bank.question_count || generation.banks.some((other) => other !== row && other.bank_id === bank.id)" />
                            </ElSelect>
                        </div>
                        <label class="space-y-1 text-sm font-medium">
                            <span>تعداد سؤال</span>
                            <ElInput v-model.number="row.question_count" type="number" min="1" :max="Math.min(bankFor(row.bank_id)?.question_count ?? 50, 50)" :readonly="!!row.question_ids" />
                        </label>
                        <div class="flex flex-wrap items-center gap-2 sm:col-span-2 xl:col-span-1">
                            <PanelButton v-if="row.question_ids" variant="link" @click="delete row.question_ids">انتخاب تصادفی</PanelButton>
                            <PanelButton v-if="generation.banks.length > 1" variant="danger-link" @click="generation.banks.splice(index, 1)">حذف بانک</PanelButton>
                            <PanelButton type="button" :disabled="!row.bank_id" title="انتخاب سؤال‌ها" aria-label="انتخاب سؤال‌ها" aria-haspopup="dialog"
                                variant="outline-brand" @click="openQuestions(row)">
                                <PanelIcon name="clipboard" class="size-5" />
                                انتخاب سؤال‌ها
                            </PanelButton>
                        </div>
                    </div>
                    <PanelButton v-if="generation.banks.length < Math.min(10, banks.filter((bank) => bank.question_count > 0).length)" variant="link" @click="generation.banks.push(bankRow())">افزودن بانک دیگر</PanelButton>
                    <p class="text-sm text-gray-600 panel-dark:text-ui-muted">سؤال‌ها به‌طور پیش‌فرض تصادفی و بدون تکرار انتخاب می‌شوند. برای انتخاب دستی، «انتخاب سؤال‌ها» را بزنید. زمان برگزاری از جلسه گرفته می‌شود.</p>
                    <p v-for="(error, key) in generation.errors" :key="key" class="text-sm text-red-700 panel-dark:text-red-300" role="alert">{{ error }}</p>
                    <PanelButton type="submit" :disabled="generation.processing">{{ generation.processing ? 'در حال ساخت…' : 'ساخت پیش‌نویس آزمون' }}</PanelButton>
                </ElForm>
                <PanelDialog v-model="pickerOpen" title="انتخاب سؤال‌ها" @close="closeQuestions">
                    <div class="space-y-3">
                        <p class="text-sm text-ui-muted">{{ bankFor(editingRow?.bank_id)?.title }}</p>
                        <p v-if="questionRequest.processing" role="status" class="text-sm text-ui-muted">در حال دریافت سؤال‌ها…</p>
                        <PanelNotice v-else-if="loadError" tone="error">دریافت سؤال‌ها ممکن نشد. پنجره را ببندید و دوباره تلاش کنید.</PanelNotice>
                        <p v-else-if="!candidates.length" class="text-sm text-ui-muted">سؤال فعالی در این بانک در دسترس نیست.</p>
                        <p v-else-if="!checked.length && candidates.every(({ id }) => taken.has(id))" class="text-sm text-ui-muted">همهٔ سؤال‌های این بانک در ردیف‌های دیگر انتخاب شده‌اند.</p>
                        <ElCheckboxGroup v-model="checked" aria-label="سؤال‌های بانک" class="panel-option-stack">
                            <ElCheckbox v-for="question in candidates" :key="question.id" :value="question.id" border class="panel-wrap-option"
                                :disabled="taken.has(question.id) || (checked.length >= 50 && !checked.includes(question.id))">
                                <span class="block whitespace-pre-wrap text-sm">{{ question.body }}</span>
                                <span class="block text-xs text-ui-muted">{{ question.type }} · {{ question.score }} نمره{{ taken.has(question.id) ? ' · انتخاب‌شده در بانک دیگر' : '' }}</span>
                            </ElCheckbox>
                        </ElCheckboxGroup>
                    </div>
                    <template #footer>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-sm text-ui-muted" aria-live="polite">{{ checked.length }} سؤال انتخاب شده (حداکثر ۵۰)</span>
                            <div class="flex flex-wrap gap-2">
                                <PanelButton variant="outline" @click="pickerOpen = false">انصراف</PanelButton>
                                <PanelButton :disabled="!checked.length || questionRequest.processing || loadError" @click="applyQuestions">ثبت انتخاب</PanelButton>
                            </div>
                        </div>
                    </template>
                </PanelDialog>
            </section>
            <section v-show="tab === 'exams'" class="space-y-4" aria-label="لیست آزمون‌های ساخته‌شده">
                <PanelNotice v-if="!exams.length">آزمونی برای این جلسه ثبت نشده است.</PanelNotice>
                <article v-for="exam in exams" :key="exam.id" class="space-y-3 ui-surface p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h2 class="min-w-0 wrap-break-word font-semibold">{{ exam.title }}</h2>
                        <div class="flex max-w-full flex-wrap items-center gap-2">
                            <ExamPreview v-if="exam.preview_url" :exam="exam" />
                            <SessionExamSettingsDialog v-if="exam.settings" :exam="exam" :settings="settings_form"
                                :url="route('exams.context.settings.update', [...parameters(), exam.id])" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <ElTag :type="exam.is_published ? 'success' : 'info'">{{ exam.is_published ? 'منتشرشده' : 'پنهان از شرکت‌کنندگان' }}</ElTag>
                        <span v-if="exam.starts_at" class="text-sm text-ui-muted">از {{ formatDateTime(exam.starts_at) }}</span>
                        <span v-if="exam.ends_at" class="text-sm text-ui-muted">تا {{ formatDateTime(exam.ends_at) }}</span>
                        <span v-if="exam.allow_late_start" class="text-sm text-ui-muted">بدون مهلت پایانی برای شروع</span>
                    </div>
                    <p v-if="context.can_manage" class="text-sm text-gray-600 panel-dark:text-ui-muted">تعداد دفعات شرکت: {{ exam.attempts_count }}</p>
                    <div class="flex flex-wrap gap-4">
                        <PanelButton v-if="exam.take_url" :href="exam.take_url">ورود به آزمون و مشاهدهٔ نتیجه</PanelButton>
                        <PanelButton v-if="context.can_manage" :disabled="publication.processing || deletion.processing" variant="link" @click="publish(exam)">{{ exam.is_published ? 'توقف انتشار' : 'انتشار آزمون' }}</PanelButton>
                        <PanelButton v-if="exam.delete_url" type="button" :disabled="deletion.processing || publication.processing" variant="danger-link" aria-haspopup="dialog" @click="confirmRemoval(exam)">حذف آزمون</PanelButton>
                    </div>
                </article>
                <p v-for="(error, key) in publication.errors" :key="key" class="text-sm text-red-600 panel-dark:text-red-300" role="alert">{{ error }}</p>
                <PanelConfirm v-model="removalOpen" title="حذف آزمون" confirm-label="حذف آزمون" danger :busy="deletion.processing" @confirm="remove">
                    <p>«{{ removingExam?.title }}» از این جلسه حذف شود؟</p>
                    <PanelNotice v-if="deletion.errors.delete_exam" tone="error" class="mt-3">{{ deletion.errors.delete_exam }}</PanelNotice>
                </PanelConfirm>
            </section>
            <section v-if="results" v-show="tab === 'results'" aria-label="نتایج و تصحیح" class="space-y-4 ui-surface p-4 sm:p-5">
                <p v-if="!results.data.length" class="text-sm text-gray-600 panel-dark:text-ui-muted">هنوز کسی در آزمون شرکت نکرده است.</p>
                <div v-for="attempt in results.data" :key="attempt.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 panel-dark:border-ui-line pb-3">
                    <div><p class="font-medium">{{ attempt.name }}</p><p class="mt-1 text-sm text-gray-500 panel-dark:text-ui-muted">{{ attempt.exam }} · {{ attempt.status }}</p></div>
                    <span v-if="attempt.score !== null" class="text-sm">{{ attempt.score }} از {{ attempt.total_score }}</span>
                    <PanelButton :href="attempt.url" variant="outline">پاسخ‌ها و تصحیح</PanelButton>
                </div>
                <nav v-if="results.prev_page_url || results.next_page_url" class="flex gap-6" aria-label="صفحه‌های نتایج">
                    <Link v-if="results.prev_page_url" :href="results.prev_page_url" :data="{ tab: 'results' }" preserve-state class="text-sm text-brand-700">قبلی</Link>
                    <Link v-if="results.next_page_url" :href="results.next_page_url" :data="{ tab: 'results' }" preserve-state class="text-sm text-brand-700">بعدی</Link>
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
