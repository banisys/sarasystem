<script setup>
import PanelDialog from '@/Components/Ui/PanelDialog.vue';
import PanelConfirm from '@/Components/Ui/PanelConfirm.vue';
import { ElCheckbox, ElCheckboxGroup, ElForm, ElInput, ElOption, ElRadio, ElRadioGroup, ElSelect, ElTable, ElTableColumn, ElTag } from 'element-plus';
import 'element-plus/es/components/checkbox/style/css';
import 'element-plus/es/components/checkbox-group/style/css';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/radio/style/css';
import 'element-plus/es/components/radio-group/style/css';
import 'element-plus/es/components/table/style/css';
import 'element-plus/es/components/tag/style/css';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';

const props = defineProps({ questions: Object, ownBanks: Array, selectedBank: Object, types: Object });
const page = usePage();
const blankOptions = () => Array.from({ length: 4 }, (_, index) => ({ text: '', is_correct: index === 0 }));
const question = useForm({
    type: 'choice', body: '', score: 1, is_active: true, is_shared: false,
    bank_ids: [], options: blankOptions(), accepted_answers: [''], model_answer: '',
});
const deletion = useForm({});
const sharing = useForm({ bank_id: '' });
const questionEditorOpen = ref(false);
const questionBody = ref(null);
const removalOpen = ref(false);
const removingQuestion = ref(null);
const editingQuestion = ref(null);
const addingQuestion = ref(null);
const availableBanks = computed(() => editingQuestion.value || !props.selectedBank
    ? props.ownBanks
    : props.ownBanks.filter((bank) => bank.id !== props.selectedBank.id));
const correctIndex = computed({
    get: () => question.options.findIndex((option) => option.is_correct),
    set: (index) => question.options.forEach((option, optionIndex) => { option.is_correct = optionIndex === index; }),
});
const returnBank = () => ({ return_bank: props.selectedBank?.id ?? null });

function openQuestion(record = null) {
    question.resetAndClearErrors();
    editingQuestion.value = record?.id ?? null;
    if (record) {
        Object.assign(question, {
            type: record.type, body: record.body, score: record.score, is_active: record.is_active,
            is_shared: record.is_shared, bank_ids: [...record.bank_ids],
            options: record.options?.map(({ text, is_correct }) => ({ text, is_correct })) ?? blankOptions(),
            accepted_answers: record.accepted_answers ? [...record.accepted_answers] : [''],
            model_answer: record.model_answer ?? '',
        });
    }
    questionEditorOpen.value = true;
}
function saveQuestion() {
    question.transform((data) => ({ ...data, ...returnBank() }));
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            question.resetAndClearErrors();
            question.defaults();
            questionEditorOpen.value = false;
        },
    };
    if (editingQuestion.value) question.patch(route('exams.library.questions.update', editingQuestion.value), options);
    else if (props.selectedBank) question.post(route('exams.library.banks.questions.store', props.selectedBank.id), options);
    else question.post(route('exams.library.questions.store'), options);
}
function confirmRemoval(id) {
    removingQuestion.value = id;
    deletion.clearErrors();
    removalOpen.value = true;
}

function removeQuestion() {
    if (deletion.processing) return;

    deletion.transform(returnBank).delete(route('exams.library.questions.destroy', removingQuestion.value), {
        preserveScroll: true,
        onSuccess: () => { removalOpen.value = false; },
    });
}
function selectSharedQuestion(id) {
    sharing.resetAndClearErrors();
    addingQuestion.value = addingQuestion.value === id ? null : id;
}
function addSharedQuestion(id) {
    sharing.transform((data) => ({ ...data, ...returnBank() })).post(route('exams.library.questions.banks.store', id), {
        preserveScroll: true, onSuccess: () => selectSharedQuestion(id),
    });
}
watch(() => props.selectedBank?.id, () => {
    questionEditorOpen.value = false;
    removalOpen.value = false;
    addingQuestion.value = null;
    sharing.resetAndClearErrors();
    deletion.clearErrors();
});

function removeOption(index) {
    question.options.splice(index, 1);
    if (correctIndex.value < 0) correctIndex.value = 0;
}
</script>

<template>
    <AppLayout>
        <Head :title="selectedBank?.title ?? 'همهٔ سؤال‌ها'" />
        <div class="space-y-6">
            <UiPageHeader
                :title="selectedBank?.title ?? 'همهٔ سؤال‌ها'"
                icon="book"
                tone="brand"
                :back-href="route('exams.library')"
                back-label="بازگشت به بانک‌ها"
            />
            <PanelNotice v-if="page.props.flash.status" tone="success">{{ page.props.flash.status }}</PanelNotice>
            <section class="ui-surface overflow-hidden" aria-label="سؤال‌ها">
                <div v-if="!selectedBank || selectedBank.can_add_question" class="flex justify-end border-b border-ui-line px-4 py-3">
                    <PanelButton variant="outline-brand" size="default" aria-haspopup="dialog" @click="openQuestion()">
                        <PanelIcon name="plus" class="size-4" />
                        افزودن سؤال
                    </PanelButton>
                </div>
                <ElTable :data="questions.data" row-key="id" empty-text="سؤالی برای نمایش وجود ندارد."
                    table-layout="auto" native-scrollbar :scrollbar-tabindex="0" flexible>
                    <ElTableColumn label="متن سؤال" min-width="230" align="right">
                        <template #default="{ row }">
                            <p class="min-w-52 whitespace-pre-wrap wrap-break-word font-medium">{{ row.body }}</p>
                            <details v-if="row.options || row.accepted_answers || row.model_answer" class="text-sm">
                                <summary class="cursor-pointer text-ui-muted">پاسخ‌ها</summary>
                                <ul v-if="row.options" class="mt-2 list-disc space-y-1 ps-5">
                                    <li v-for="option in row.options" :key="option.key">{{ option.text }}{{ option.is_correct ? ' (درست)' : '' }}</li>
                                </ul>
                                <p v-else class="mt-2 whitespace-pre-wrap wrap-break-word">{{ row.accepted_answers?.join('، ') ?? row.model_answer }}</p>
                            </details>
                        </template>
                    </ElTableColumn>
                    <ElTableColumn label="نوع" width="110" align="right">
                        <template #default="{ row }">{{ types[row.type] }}</template>
                    </ElTableColumn>
                    <ElTableColumn label="بارم" prop="score" width="64" align="right" />
                    <ElTableColumn label="وضعیت" width="90" align="right">
                        <template #default="{ row }">
                            <ElTag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? 'فعال' : 'غیرفعال' }}</ElTag>
                        </template>
                    </ElTableColumn>
                    <ElTableColumn label="اشتراک" width="90" align="right">
                        <template #default="{ row }">
                            <ElTag :type="row.is_shared ? 'success' : 'info'" size="small">{{ row.is_shared ? 'عمومی' : 'خصوصی' }}</ElTag>
                        </template>
                    </ElTableColumn>
                    <ElTableColumn label="عملیات" width="210" align="right">
                        <template #default="{ row }">
                            <div v-if="row.is_mine" class="flex flex-wrap gap-3">
                                <PanelButton variant="link" aria-haspopup="dialog" @click="openQuestion(row)">ویرایش</PanelButton>
                                <PanelButton variant="danger-link" :disabled="deletion.processing" aria-haspopup="dialog" @click="confirmRemoval(row.id)">حذف</PanelButton>
                            </div>
                            <PanelButton v-else-if="ownBanks.length" variant="link" :aria-expanded="addingQuestion === row.id" @click="selectSharedQuestion(row.id)">افزودن به بانک من</PanelButton>
                            <ElForm v-if="addingQuestion === row.id" class="mt-1 space-y-2" novalidate @submit.prevent="addSharedQuestion(row.id)">
                                <label class="block space-y-1 text-sm">
                                    <span>بانک مقصد</span>
                                    <ElSelect v-model="sharing.bank_id" aria-label="بانک مقصد" placeholder="انتخاب کنید" append-to="#profile-overlays">
                                        <ElOption v-for="ownBank in ownBanks" :key="ownBank.id" :value="ownBank.id" :label="ownBank.title" />
                                    </ElSelect>
                                </label>
                                <p v-for="(error, key) in sharing.errors" :key="key" class="ui-field-error" role="alert">{{ error }}</p>
                                <PanelButton type="submit" :loading="sharing.processing">افزودن سؤال</PanelButton>
                            </ElForm>
                        </template>
                    </ElTableColumn>
                </ElTable>
                <nav v-if="questions.prev_page_url || questions.next_page_url" class="flex gap-4 border-t border-ui-line px-4 py-2" aria-label="صفحه‌های سؤال">
                    <PanelButton v-if="questions.prev_page_url" :href="questions.prev_page_url" variant="link">قبلی</PanelButton>
                    <PanelButton v-if="questions.next_page_url" :href="questions.next_page_url" variant="link">بعدی</PanelButton>
                </nav>
            </section>

            <PanelDialog v-model="questionEditorOpen" :title="editingQuestion ? 'ویرایش سؤال' : 'افزودن سؤال'"
                :busy="question.processing" @opened="questionBody?.focus()">
                <ElForm class="space-y-4" novalidate @submit.prevent="saveQuestion">
                    <p v-if="selectedBank && !editingQuestion" class="text-sm text-ui-muted">بانک مقصد: {{ selectedBank.title }}</p>
                    <label class="block space-y-1 text-sm">
                        <span>نوع سؤال</span>
                        <ElSelect v-model="question.type" aria-label="نوع سؤال" append-to="#profile-overlays">
                            <ElOption v-for="(label, value) in types" :key="value" :value="value" :label="label" />
                        </ElSelect>
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span>متن سؤال</span>
                        <ElInput type="textarea" id="question-body" ref="questionBody" v-model="question.body" :rows="3" />
                    </label>
                    <fieldset v-if="question.type === 'choice'" class="space-y-2">
                        <legend class="text-sm font-medium">گزینه‌ها و پاسخ درست</legend>
                        <ElRadioGroup v-model="correctIndex" aria-label="پاسخ درست" class="panel-option-stack">
                            <div v-for="(option, index) in question.options" :key="index" class="flex w-full items-center gap-2">
                                <ElRadio :value="index" :aria-label="`گزینهٔ درست ${index + 1}`" />
                                <ElInput v-model="option.text" :aria-label="`متن گزینهٔ ${index + 1}`" />
                                <PanelButton v-if="question.options.length > 2" variant="danger-link" @click="removeOption(index)">حذف</PanelButton>
                            </div>
                        </ElRadioGroup>
                        <PanelButton v-if="question.options.length < 10" variant="link" @click="question.options.push({ text: '', is_correct: false })">افزودن گزینه</PanelButton>
                    </fieldset>
                    <fieldset v-else-if="question.type === 'fill_in_the_blank'" class="space-y-2">
                        <legend class="text-sm font-medium">پاسخ‌های پذیرفتنی</legend>
                        <p class="text-sm text-gray-600 panel-dark:text-ui-muted">جای پاسخ را در متن سؤال با سه زیرخط (___) مشخص کنید.</p>
                        <div v-for="(_, index) in question.accepted_answers" :key="index" class="flex gap-2">
                            <ElInput v-model="question.accepted_answers[index]" :aria-label="`پاسخ پذیرفتنی ${index + 1}`" />
                            <PanelButton v-if="question.accepted_answers.length > 1" variant="danger-link" @click="question.accepted_answers.splice(index, 1)">حذف</PanelButton>
                        </div>
                        <PanelButton v-if="question.accepted_answers.length < 10" variant="link" @click="question.accepted_answers.push('')">افزودن پاسخ</PanelButton>
                    </fieldset>
                    <label v-else class="block space-y-1 text-sm"><span>پاسخ نمونه (اختیاری)</span><ElInput type="textarea" v-model="question.model_answer" :rows="2" /></label>
                    <label class="block space-y-1 text-sm"><span>بارم</span><ElInput v-model.number="question.score" type="number" min="0.25" step="0.01" /></label>
                    <fieldset v-if="availableBanks.length" class="space-y-1">
                        <legend class="mb-1 text-sm font-medium">{{ selectedBank && !editingQuestion ? 'بانک‌های دیگر من' : 'بانک‌های من' }}</legend>
                        <ElCheckboxGroup v-model="question.bank_ids" :aria-label="selectedBank && !editingQuestion ? 'بانک‌های دیگر من' : 'بانک‌های من'" class="panel-option-stack">
                            <ElCheckbox v-for="record in availableBanks" :key="record.id" :value="record.id" class="panel-wrap-option">
                                {{ record.title }}
                            </ElCheckbox>
                        </ElCheckboxGroup>
                    </fieldset>
                    <div><ElCheckbox v-model="question.is_active">فعال برای ساخت آزمون</ElCheckbox></div>
                    <div><ElCheckbox v-model="question.is_shared">عمومی در این شعبه</ElCheckbox></div>
                    <p v-for="(error, key) in question.errors" :key="key" class="text-sm text-red-700 panel-dark:text-red-300" role="alert">{{ error }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <PanelButton type="submit" :disabled="question.processing">ذخیرهٔ سؤال</PanelButton>
                        <PanelButton variant="outline" :disabled="question.processing" @click="questionEditorOpen = false">انصراف</PanelButton>
                    </div>
                </ElForm>
            </PanelDialog>
            <PanelConfirm v-model="removalOpen" title="حذف سؤال" confirm-label="حذف سؤال" danger
                :busy="deletion.processing" @confirm="removeQuestion">
                <p>سؤال حذف شود؟</p>
                <PanelNotice v-for="(error, key) in deletion.errors" :key="key" class="mt-2" tone="error">{{ error }}</PanelNotice>
            </PanelConfirm>
        </div>
    </AppLayout>
</template>
