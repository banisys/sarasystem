<script setup>
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import { ElCheckbox, ElCheckboxGroup, ElForm, ElInput, ElOption, ElSelect, ElTable, ElTableColumn, ElTabPane, ElTabs, ElTag } from 'element-plus';
import 'element-plus/es/components/checkbox-group/style/css';
import 'element-plus/es/components/checkbox/style/css';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/tag/style/css';
import 'element-plus/es/components/table/style/css';
import 'element-plus/es/components/tabs/style/css';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import PanelPagination from '@/Components/Ui/PanelPagination.vue';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelConfirm from '@/Components/Ui/PanelConfirm.vue';
import PanelDialog from '@/Components/Ui/PanelDialog.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelPrintPreview from '@/Components/Ui/PanelPrintPreview.vue';
import { computed, ref } from 'vue';
import { Head, router, useForm, useHttp, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import ExamPreview from '../Components/ExamPreview.vue';
import ExamGroupSelect from '../Components/ExamGroupSelect.vue';
import SessionExamFields from '../Components/SessionExamFields.vue';
import SessionExamSettingsDialog from '../Components/SessionExamSettingsDialog.vue';
import { formatDateTime } from '../format.js';

const props = defineProps({ context: Object, banks: Array, exams: Array, results: Object, settings_form: Object });
const page = usePage();
const examListTitle = 'آزمون ساخته‌شده';
const tabs = computed(() => [
    { key: 'exams', label: examListTitle, icon: 'clipboard' },
    ...(props.context.can_create ? [{ key: 'create', label: 'ساخت آزمون از بانک سؤال', icon: 'book' }] : []),
    ...(props.results ? [{ key: 'results', label: 'نتایج و تصحیح', icon: 'pencil' }] : []),
]);
const requestedTab = new URLSearchParams(page.url.split('?')[1]).get('tab');
const tab = ref(tabs.value.some((item) => item.key === requestedTab) ? requestedTab : tabs.value[0].key);

function goToResultsPage(number) {
    router.get(route('exams.context', parameters()), { page: number, tab: 'results' }, { preserveState: true, preserveScroll: true });
}

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
const groupForm = useForm({ exam_group_id: null });
const groupOpen = ref(false);
const groupingExam = ref(null);
const deletion = useForm({});
const removalOpen = ref(false);
const removingExam = ref(null);
const parameters = () => [props.context.type, props.context.id];
function openGroup(exam) {
    groupingExam.value = exam;
    groupForm.defaults('exam_group_id', exam.exam_group?.id ?? null).resetAndClearErrors();
    groupOpen.value = true;
}

function saveGroup() {
    groupForm.patch(groupingExam.value.group_url, {
        preserveScroll: true,
        onSuccess: () => { groupOpen.value = false; },
    });
}

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
        <Head :title="examListTitle" />
        <div class="space-y-6">
            <UiPageHeader
                :title="examListTitle"
                :description="context.title"
                icon="clipboard"
                :back-href="context.back_url"
                full-page-back
                :back-label="`بازگشت به ${context.host_label}`"
            >
                <template v-if="context.paper_url" #actions>
                    <PanelPrintPreview :url="context.paper_url" title="برگهٔ آزمون جلسه" print-label="چاپ آزمون" />
                </template>
            </UiPageHeader>
            <ElTabs v-model="tab" class="session-exam-tabs pt-4" :aria-label="`بخش‌های آزمون‌های ${context.host_label}`">
                <ElTabPane v-for="item in tabs" :key="item.key" :name="item.key" :label="item.label" lazy>
                    <template #label>
                        <PanelIcon :name="item.icon" class="size-4 shrink-0" />
                        {{ item.label }}
                    </template>
                    <section v-if="item.key === 'exams'" class="grid gap-3 sm:gap-4" :aria-label="item.label">
                        <PanelEmpty v-if="!exams.length" :description="`آزمونی برای این ${context.host_label} ثبت نشده است.`" />
                        <PanelCard v-for="exam in exams" :key="exam.id" body-class="grid gap-3 [--el-card-padding:0.875rem] sm:gap-4 sm:[--el-card-padding:1.25rem]">
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                <h2 class="min-w-0 flex-1 basis-full wrap-break-word font-semibold sm:basis-48">{{ exam.title }}</h2>
                                <div v-if="exam.preview_url || exam.settings || exam.group_url" class="flex max-w-full flex-wrap items-center gap-2">
                                    <ExamPreview v-if="exam.preview_url" :exam="exam" />
                                    <PanelButton v-if="exam.group_url" variant="outline" aria-haspopup="dialog" @click="openGroup(exam)">گروه آزمون</PanelButton>
                                    <SessionExamSettingsDialog v-if="exam.settings" :exam="exam" :settings="settings_form"
                                        :url="route('exams.context.settings.update', [...parameters(), exam.id])" />
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                                    <ElTag v-if="exam.is_practice" type="info">آزمون تمرینی</ElTag>
                                    <ElTag v-if="exam.exam_group" type="info">{{ exam.exam_group.title }}</ElTag>
                                    <ElTag v-if="exam.user_panel_locked" type="warning">ویرایش قفل است</ElTag>
                                    <ElTag :type="exam.is_published ? 'success' : 'info'">{{ exam.is_published ? 'منتشرشده' : 'پنهان از شرکت‌کنندگان' }}</ElTag>
                                    <span v-if="exam.starts_at" class="text-sm text-ui-muted">از {{ formatDateTime(exam.starts_at) }}</span>
                                    <span v-if="exam.ends_at" class="text-sm text-ui-muted">تا {{ formatDateTime(exam.ends_at) }}</span>
                                    <span v-if="exam.allow_late_start" class="text-sm text-ui-muted">بدون مهلت پایانی برای شروع</span>
                                    <span v-if="context.can_manage" class="text-sm text-gray-600 panel-dark:text-ui-muted">تعداد دفعات شرکت: {{ exam.attempts_count }}</span>
                                </div>
                                <PanelButton v-if="exam.take_url" :href="exam.take_url" class="ms-auto">{{ exam.is_practice ? 'ورود به تمرین' : 'ورود به آزمون و مشاهدهٔ نتیجه' }}</PanelButton>
                            </div>
                            <div v-if="context.can_manage && !exam.user_panel_locked" class="flex flex-wrap items-center gap-x-4 gap-y-2 pt-1">
                                <PanelButton :disabled="publication.processing || deletion.processing" variant="link" @click="publish(exam)">{{ exam.is_published ? 'توقف انتشار' : 'انتشار آزمون' }}</PanelButton>
                                <PanelButton v-if="exam.delete_url" type="button" :disabled="deletion.processing || publication.processing" variant="danger-link" aria-haspopup="dialog" @click="confirmRemoval(exam)">حذف آزمون</PanelButton>
                            </div>
                        </PanelCard>
                        <p v-for="(error, key) in publication.errors" :key="key" class="text-sm text-red-600 panel-dark:text-red-300" role="alert">{{ error }}</p>
                        <PanelDialog v-if="groupingExam" v-model="groupOpen" title="گروه آزمون" :busy="groupForm.processing">
                            <ElForm novalidate class="grid gap-3" @submit.prevent="saveGroup">
                                <p class="wrap-break-word text-sm text-ui-muted">{{ groupingExam.title }}</p>
                                <ExamGroupSelect v-model="groupForm.exam_group_id" :groups="settings_form.exam_groups" />
                                <p v-if="groupForm.errors.exam_group_id" class="text-sm text-red-700 panel-dark:text-red-300" role="alert">{{ groupForm.errors.exam_group_id }}</p>
                                <PanelButton type="submit" class="justify-self-end" :loading="groupForm.processing">ذخیرهٔ گروه</PanelButton>
                            </ElForm>
                        </PanelDialog>
                        <PanelConfirm v-model="removalOpen" title="حذف آزمون" confirm-label="حذف آزمون" danger :busy="deletion.processing" @confirm="remove">
                            <p>«{{ removingExam?.title }}» از این {{ context.host_label }} حذف شود؟</p>
                            <PanelNotice v-if="deletion.errors.delete_exam" tone="error" class="mt-3">{{ deletion.errors.delete_exam }}</PanelNotice>
                        </PanelConfirm>
                    </section>
                    <PanelCard v-if="item.key === 'create'" body-class="[--el-card-padding:1rem] sm:[--el-card-padding:1.5rem]">
                        <p v-if="!banks.some((bank) => bank.question_count > 0)" class="text-sm text-gray-600 panel-dark:text-ui-muted">
                            برای ساخت آزمون، در بانک خودتان سؤال فعال ثبت کنید یا از یک بانک عمومیِ دارای سؤال استفاده کنید.
                        </p>
                        <ElForm v-else novalidate class="grid gap-6 sm:gap-8" @submit.prevent="generate">
                            <SessionExamFields :form="generation" :settings="settings_form" class="[--exam-settings-gap:1.25rem] sm:[--exam-settings-gap:1.5rem]" />
                            <ExamGroupSelect v-model="generation.exam_group_id" :groups="settings_form.exam_groups" class="sm:max-w-sm" />
                            <div class="grid gap-4 sm:gap-5">
                                <div v-for="(row, index) in generation.banks" :key="index" class="grid items-end gap-4 rounded-ui bg-ui-canvas p-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] sm:gap-5 sm:p-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                                    <PanelFormField label="بانک سؤال" :for="`exam-bank-${index}`">
                                        <ElSelect :id="`exam-bank-${index}`" v-model="row.bank_id" placeholder="انتخاب کنید" append-to="#profile-overlays" @change="delete row.question_ids">
                                            <ElOption v-for="bank in banks" :key="bank.id" :value="bank.id"
                                                :label="`${bank.title} · ${bank.is_mine ? 'بانک من' : 'عمومی'} · ${bank.question_count} سؤال`"
                                                :disabled="!bank.question_count || generation.banks.some((other) => other !== row && other.bank_id === bank.id)" />
                                        </ElSelect>
                                    </PanelFormField>
                                    <PanelFormField label="تعداد سؤال">
                                        <ElInput v-model.number="row.question_count" type="number" min="1" :max="Math.min(bankFor(row.bank_id)?.question_count ?? 50, 50)" :readonly="!!row.question_ids" />
                                    </PanelFormField>
                                    <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-1">
                                        <PanelButton v-if="row.question_ids" variant="link" @click="delete row.question_ids">انتخاب تصادفی</PanelButton>
                                        <PanelButton v-if="generation.banks.length > 1" variant="danger-link" @click="generation.banks.splice(index, 1)">حذف بانک</PanelButton>
                                        <PanelButton type="button" :disabled="!row.bank_id" title="انتخاب سؤال‌ها" aria-label="انتخاب سؤال‌ها" aria-haspopup="dialog"
                                            variant="outline-brand" @click="openQuestions(row)">
                                            <PanelIcon name="clipboard" class="size-5" />
                                            انتخاب سؤال‌ها
                                        </PanelButton>
                                    </div>
                                </div>
                                <PanelButton v-if="generation.banks.length < Math.min(10, banks.filter((bank) => bank.question_count > 0).length)" variant="link" class="justify-self-start" @click="generation.banks.push(bankRow())">افزودن بانک دیگر</PanelButton>
                            </div>
                            <div class="grid justify-items-start gap-4">
                                <p class="text-sm text-gray-600 panel-dark:text-ui-muted">سؤال‌ها به‌طور پیش‌فرض تصادفی و بدون تکرار انتخاب می‌شوند. برای انتخاب دستی، «انتخاب سؤال‌ها» را بزنید. <span v-if="context.uses_host_window">زمان برگزاری از {{ context.host_label }} گرفته می‌شود.</span></p>
                                <p v-for="(error, key) in generation.errors" :key="key" class="text-sm text-red-700 panel-dark:text-red-300" role="alert">{{ error }}</p>
                                <PanelButton type="submit" :disabled="generation.processing">
                                    {{ generation.processing ? 'در حال ساخت…' : generation.is_practice ? 'ساخت پیش‌نویس آزمون تمرینی' : 'ساخت پیش‌نویس آزمون' }}
                                </PanelButton>
                            </div>
                        </ElForm>
                        <PanelDialog v-model="pickerOpen" title="انتخاب سؤال‌ها"
                            class="[--el-dialog-padding-primary:1rem]! sm:[--el-dialog-padding-primary:1.5rem]!" @close="closeQuestions">
                            <div class="space-y-3">
                                <p class="text-sm text-ui-muted">{{ bankFor(editingRow?.bank_id)?.title }}</p>
                                <p v-if="questionRequest.processing" role="status" class="text-sm text-ui-muted">در حال دریافت سؤال‌ها…</p>
                                <PanelNotice v-else-if="loadError" tone="error">دریافت سؤال‌ها ممکن نشد. پنجره را ببندید و دوباره تلاش کنید.</PanelNotice>
                                <p v-else-if="!candidates.length" class="text-sm text-ui-muted">سؤال فعالی در این بانک در دسترس نیست.</p>
                                <p v-else-if="!checked.length && candidates.every(({ id }) => taken.has(id))" class="text-sm text-ui-muted">همهٔ سؤال‌های این بانک در ردیف‌های دیگر انتخاب شده‌اند.</p>
                                <ElCheckboxGroup v-model="checked" aria-label="سؤال‌های بانک" class="panel-option-stack gap-3!">
                                    <ElCheckbox v-for="question in candidates" :key="question.id" :value="question.id" border
                                        class="panel-wrap-option h-auto! px-4! py-3! sm:px-5! [&>.el-checkbox__input]:mt-1!"
                                        :disabled="taken.has(question.id) || (checked.length >= 50 && !checked.includes(question.id))">
                                        <span class="block whitespace-pre-wrap text-sm">{{ question.body }}</span>
                                        <span class="block text-xs text-ui-muted">
                                            {{ question.type }}<template v-if="!generation.is_practice"> · {{ question.score }} نمره</template>{{ taken.has(question.id) ? ' · انتخاب‌شده در بانک دیگر' : '' }}
                                        </span>
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
                    </PanelCard>
                    <PanelCard v-if="item.key === 'results'" body-class="space-y-4 p-4 sm:p-5">
                        <ElTable :data="results.data" row-key="id" empty-text="هنوز کسی در آزمون شرکت نکرده است."
                            table-layout="auto" native-scrollbar :scrollbar-tabindex="0" flexible>
                            <ElTableColumn prop="name" label="شرکت‌کننده" min-width="125" align="right" />
                            <ElTableColumn prop="exam" label="آزمون" min-width="125" align="right" />
                            <ElTableColumn prop="status" label="وضعیت" min-width="100" align="right" />
                            <ElTableColumn label="نمره" width="100" align="right">
                                <template #default="{ row }">{{ row.score !== null ? `${row.score} از ${row.total_score}` : '—' }}</template>
                            </ElTableColumn>
                            <ElTableColumn label="عملیات" width="145" align="right">
                                <template #default="{ row }">
                                    <PanelButton :href="row.url" variant="link">پاسخ‌ها و تصحیح</PanelButton>
                                </template>
                            </ElTableColumn>
                        </ElTable>
                        <nav v-if="results.last_page > 1" aria-label="صفحه‌های نتایج">
                            <PanelPagination :current-page="results.current_page" :page-size="results.per_page" :total="results.total"
                                @update:current-page="goToResultsPage" />
                        </nav>
                    </PanelCard>
                </ElTabPane>
            </ElTabs>
        </div>
    </AppLayout>
</template>

<style scoped>
.session-exam-tabs :deep(.el-tabs__nav-wrap:not(.is-scrollable) .el-tabs__nav) {
    float: inline-start;
}
</style>
