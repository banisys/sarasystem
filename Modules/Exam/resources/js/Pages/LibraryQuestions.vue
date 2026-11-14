<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import UiButton from '@/Components/Ui/UiButton.vue';

const props = defineProps({ questions: Object, ownBanks: Array, selectedBank: Object, types: Object });
const page = usePage();
const blankOptions = () => Array.from({ length: 4 }, (_, index) => ({ text: '', is_correct: index === 0 }));
const question = useForm({
    type: 'choice', body: '', score: 1, is_active: true, is_shared: false,
    bank_ids: [], options: blankOptions(), accepted_answers: [''], model_answer: '',
});
const deletion = useForm({});
const sharing = useForm({ bank_id: '' });
const dialog = ref(null);
const editingQuestion = ref(null);
const addingQuestion = ref(null);
const availableBanks = computed(() => editingQuestion.value || !props.selectedBank
    ? props.ownBanks
    : props.ownBanks.filter((bank) => bank.id !== props.selectedBank.id));
const correctIndex = computed({
    get: () => question.options.findIndex((option) => option.is_correct),
    set: (index) => question.options.forEach((option, optionIndex) => { option.is_correct = optionIndex === index; }),
});
const field = 'ui-field';
const primary = 'ui-button border-transparent bg-brand-600 text-brand-contrast hover:bg-brand-action-hover';
const secondary = 'inline-flex min-h-9 items-center text-sm font-medium text-brand-700 hover:underline';
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
    dialog.value.showModal();
}
function saveQuestion() {
    question.transform((data) => ({ ...data, ...returnBank() }));
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            question.resetAndClearErrors();
            question.defaults();
            dialog.value?.close();
        },
    };
    if (editingQuestion.value) question.patch(route('exams.library.questions.update', editingQuestion.value), options);
    else if (props.selectedBank) question.post(route('exams.library.banks.questions.store', props.selectedBank.id), options);
    else question.post(route('exams.library.questions.store'), options);
}
function removeQuestion(id) {
    if (!window.confirm('سؤال حذف شود؟')) return;
    deletion.transform(returnBank).delete(route('exams.library.questions.destroy', id), {
        preserveScroll: true,
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
    dialog.value?.close();
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
                tone="plum"
                :back-href="route('exams.library')"
                back-label="بازگشت به بانک‌ها"
            />
            <UiNotice v-if="page.props.flash.status" tone="success">{{ page.props.flash.status }}</UiNotice>
            <p v-for="(error, key) in deletion.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
            <section class="ui-surface overflow-hidden" aria-label="سؤال‌ها">
                <div v-if="!selectedBank || selectedBank.can_add_question" class="flex justify-end border-b border-ui-line px-4 py-3">
                    <UiButton variant="outline-brand" class="min-h-9 gap-1.5 px-3" aria-haspopup="dialog" @click="openQuestion()">
                        <PanelIcon name="plus" class="size-4" />
                        افزودن سؤال
                    </UiButton>
                </div>
                <div v-if="questions.data.length" class="overflow-x-auto">
                    <table class="w-full divide-y divide-ui-line text-start text-sm">
                        <thead class="bg-ui-canvas text-ui-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-start">متن سؤال</th>
                                <th scope="col" class="px-4 py-3 text-start whitespace-nowrap">نوع</th>
                                <th scope="col" class="px-4 py-3 text-start">بارم</th>
                                <th scope="col" class="px-4 py-3 text-start whitespace-nowrap">وضعیت</th>
                                <th scope="col" class="px-4 py-3 text-start">اشتراک</th>
                                <th scope="col" class="px-4 py-3 text-start">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ui-line">
                            <tr v-for="record in questions.data" :key="record.id" class="align-top">
                                <td class="min-w-56 space-y-2 px-4 py-3">
                                    <p class="whitespace-pre-wrap wrap-break-word font-medium">{{ record.body }}</p>
                                    <details v-if="record.options || record.accepted_answers || record.model_answer" class="text-sm">
                                        <summary class="cursor-pointer text-gray-700">پاسخ‌ها</summary>
                                        <ul v-if="record.options" class="mt-2 list-disc space-y-1 ps-5">
                                            <li v-for="option in record.options" :key="option.key">{{ option.text }}{{ option.is_correct ? ' (درست)' : '' }}</li>
                                        </ul>
                                        <p v-else class="mt-2 whitespace-pre-wrap wrap-break-word">{{ record.accepted_answers?.join('، ') ?? record.model_answer }}</p>
                                    </details>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ types[record.type] }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ record.score }}</td>
                                <td class="px-4 py-3">{{ record.is_active ? 'فعال' : 'غیرفعال' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ record.is_shared ? 'عمومی' : 'خصوصی' }}</td>
                                <td class="px-4 py-2">
                                    <div v-if="record.is_mine" class="flex items-center gap-3 whitespace-nowrap">
                                        <button type="button" :class="secondary" aria-haspopup="dialog" @click="openQuestion(record)">ویرایش</button>
                                        <button type="button" :disabled="deletion.processing" class="text-red-700 hover:underline" @click="removeQuestion(record.id)">حذف</button>
                                    </div>
                                    <button v-else-if="ownBanks.length" type="button" :class="[secondary, 'whitespace-nowrap']" :aria-expanded="addingQuestion === record.id" @click="selectSharedQuestion(record.id)">افزودن به بانک من</button>
                                    <form v-if="addingQuestion === record.id" class="space-y-2" novalidate @submit.prevent="addSharedQuestion(record.id)">
                                        <label class="block space-y-1 text-sm">
                                            <span>بانک مقصد</span>
                                            <select v-model="sharing.bank_id" :class="field">
                                                <option value="">انتخاب کنید</option>
                                                <option v-for="ownBank in ownBanks" :key="ownBank.id" :value="ownBank.id">{{ ownBank.title }}</option>
                                            </select>
                                        </label>
                                        <p v-for="(error, key) in sharing.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
                                        <button :class="primary" :disabled="sharing.processing">افزودن سؤال</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-4 text-sm text-ui-muted">سؤالی برای نمایش وجود ندارد.</p>
                <nav v-if="questions.prev_page_url || questions.next_page_url" class="flex gap-4 border-t border-ui-line px-4 py-2" aria-label="صفحه‌های سؤال">
                    <Link v-if="questions.prev_page_url" :href="questions.prev_page_url" :class="secondary">قبلی</Link>
                    <Link v-if="questions.next_page_url" :href="questions.next_page_url" :class="secondary">بعدی</Link>
                </nav>
            </section>

            <dialog ref="dialog" aria-labelledby="question-dialog-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-auto rounded-ui bg-white p-5 text-ui-ink shadow-xl backdrop:bg-black/50">
                <form class="space-y-4" novalidate @submit.prevent="saveQuestion">
                    <h2 id="question-dialog-title" class="text-lg font-bold">{{ editingQuestion ? 'ویرایش سؤال' : 'افزودن سؤال' }}</h2>
                    <p v-if="selectedBank && !editingQuestion" class="text-sm text-ui-muted">بانک مقصد: {{ selectedBank.title }}</p>
                    <label class="block space-y-1 text-sm">
                        <span>نوع سؤال</span>
                        <select v-model="question.type" :class="field">
                            <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span>متن سؤال</span>
                        <textarea id="question-body" autofocus v-model="question.body" rows="3" :class="field" />
                    </label>
                    <fieldset v-if="question.type === 'choice'" class="space-y-2">
                        <legend class="text-sm font-medium">گزینه‌ها و پاسخ درست</legend>
                        <div v-for="(option, index) in question.options" :key="index" class="flex items-center gap-2">
                            <input v-model="correctIndex" type="radio" name="correct-option" :value="index" :aria-label="`گزینهٔ درست ${index + 1}`" />
                            <input v-model="option.text" :class="field" :aria-label="`متن گزینهٔ ${index + 1}`" />
                            <button v-if="question.options.length > 2" type="button" class="text-sm text-red-700" @click="removeOption(index)">حذف</button>
                        </div>
                        <button v-if="question.options.length < 10" type="button" :class="secondary" @click="question.options.push({ text: '', is_correct: false })">افزودن گزینه</button>
                    </fieldset>
                    <fieldset v-else-if="question.type === 'fill_in_the_blank'" class="space-y-2">
                        <legend class="text-sm font-medium">پاسخ‌های پذیرفتنی</legend>
                        <p class="text-sm text-gray-600">جای پاسخ را در متن سؤال با سه زیرخط (___) مشخص کنید.</p>
                        <div v-for="(_, index) in question.accepted_answers" :key="index" class="flex gap-2">
                            <input v-model="question.accepted_answers[index]" :class="field" :aria-label="`پاسخ پذیرفتنی ${index + 1}`" />
                            <button v-if="question.accepted_answers.length > 1" type="button" class="text-sm text-red-700" @click="question.accepted_answers.splice(index, 1)">حذف</button>
                        </div>
                        <button v-if="question.accepted_answers.length < 10" type="button" :class="secondary" @click="question.accepted_answers.push('')">افزودن پاسخ</button>
                    </fieldset>
                    <label v-else class="block space-y-1 text-sm"><span>پاسخ نمونه (اختیاری)</span><textarea v-model="question.model_answer" rows="2" :class="field" /></label>
                    <label class="block space-y-1 text-sm"><span>بارم</span><input v-model="question.score" type="number" min="0.25" step="0.01" :class="field" /></label>
                    <fieldset v-if="availableBanks.length" class="space-y-1">
                        <legend class="mb-1 text-sm font-medium">{{ selectedBank && !editingQuestion ? 'بانک‌های دیگر من' : 'بانک‌های من' }}</legend>
                        <label v-for="record in availableBanks" :key="record.id" class="flex items-center gap-2 text-sm">
                            <input v-model="question.bank_ids" type="checkbox" :value="record.id" /> {{ record.title }}
                        </label>
                    </fieldset>
                    <label class="flex items-center gap-2 text-sm"><input v-model="question.is_active" type="checkbox" /> فعال برای ساخت آزمون</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="question.is_shared" type="checkbox" /> عمومی در این شعبه</label>
                    <p v-for="(error, key) in question.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <UiButton type="submit" :disabled="question.processing">ذخیرهٔ سؤال</UiButton>
                        <UiButton variant="outline" @click="dialog.close()">انصراف</UiButton>
                    </div>
                </form>
            </dialog>
        </div>
    </AppLayout>
</template>
