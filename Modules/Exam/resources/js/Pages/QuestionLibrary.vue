<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ banks: Object, questions: Object, ownBanks: Array, selectedBank: Object, types: Object });
const page = usePage();
const bank = useForm({ title: '', description: '', is_shared: false });
const blankOptions = () => Array.from({ length: 4 }, (_, index) => ({ text: '', is_correct: index === 0 }));
const question = useForm({
    type: 'choice', body: '', score: 1, is_active: true, is_shared: false,
    bank_ids: [], options: blankOptions(), accepted_answers: [''], model_answer: '',
});
const deletion = useForm({});
const sharing = useForm({ bank_id: '' });
const editingBank = ref(null);
const editingQuestion = ref(null);
const addingQuestion = ref(null);
const correctIndex = computed({
    get: () => question.options.findIndex((option) => option.is_correct),
    set: (index) => question.options.forEach((option, optionIndex) => { option.is_correct = optionIndex === index; }),
});
const field = 'ui-field';
const primary = 'ui-button border-transparent bg-brand-600 text-brand-contrast hover:bg-brand-action-hover';
const secondary = 'inline-flex min-h-9 items-center text-sm font-medium text-brand-700 hover:underline';
const libraryUrl = () => route('exams.library');

function editBank(record = null) {
    bank.resetAndClearErrors();
    editingBank.value = record?.id ?? null;
    if (record) {
        Object.assign(bank, { title: record.title, description: record.description ?? '', is_shared: record.is_shared });
        document.getElementById('bank-title')?.focus();
    }
}
function saveBank() {
    const options = { preserveScroll: true, onSuccess: () => editBank() };
    if (editingBank.value) bank.patch(route('exams.library.banks.update', editingBank.value), options);
    else bank.post(route('exams.library.banks.store'), options);
}
function editQuestion(record = null) {
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
        document.getElementById('question-body')?.focus();
    }
}
function saveQuestion() {
    const options = { preserveScroll: true, onSuccess: () => editQuestion() };
    if (editingQuestion.value) question.patch(route('exams.library.questions.update', editingQuestion.value), options);
    else question.post(route('exams.library.questions.store'), options);
}
function remove(kind, id) {
    if (!window.confirm(kind === 'banks' ? 'بانک حذف شود؟ سؤال‌های آن باقی می‌مانند.' : 'سؤال حذف شود؟')) return;
    deletion.delete(route(`exams.library.${kind}.destroy`, id), {
        preserveScroll: true,
        onSuccess: () => {
            if (kind === 'banks' && editingBank.value === id) editBank();
            if (kind === 'questions' && editingQuestion.value === id) editQuestion();
        },
    });
}
function selectSharedQuestion(id) {
    sharing.resetAndClearErrors();
    addingQuestion.value = addingQuestion.value === id ? null : id;
}
function addSharedQuestion(id) {
    sharing.post(route('exams.library.questions.banks.store', id), {
        preserveScroll: true, onSuccess: () => selectSharedQuestion(id),
    });
}
function removeOption(index) {
    question.options.splice(index, 1);
    if (correctIndex.value < 0) correctIndex.value = 0;
}
</script>

<template>
    <AppLayout>
        <Head title="بانک سؤال" />
        <div class="space-y-6">
            <UiPageHeader
                title="بانک سؤال"
                description="همکاران مجاز همین شعبه می‌توانند از بانک‌ها و سؤال‌های عمومی استفاده کنند."
                icon="book"
            >
                <template #actions>
                    <a href="#bank-title" class="ui-button border-ui-line bg-white hover:bg-ui-canvas">{{ editingBank ? 'ویرایش بانک' : 'بانک جدید' }}</a>
                    <a href="#question-body" class="ui-button border-ui-line bg-white hover:bg-ui-canvas">{{ editingQuestion ? 'ویرایش سؤال' : 'سؤال جدید' }}</a>
                </template>
            </UiPageHeader>
            <UiNotice v-if="page.props.flash.status" tone="success">{{ page.props.flash.status }}</UiNotice>
            <p v-for="(error, key) in deletion.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
            <section class="ui-surface grid gap-5 p-4 sm:p-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                <form class="min-w-0 space-y-3 rounded-ui bg-ui-canvas p-3 sm:p-4" novalidate @submit.prevent="saveBank">
                    <h2 class="font-semibold">{{ editingBank ? 'ویرایش بانک' : 'بانک جدید' }}</h2>
                    <label class="block space-y-1 text-sm"><span>عنوان بانک</span><input id="bank-title" class="scroll-mt-24" v-model="bank.title" :class="field" /></label>
                    <label class="block space-y-1 text-sm"><span>توضیحات</span><textarea v-model="bank.description" rows="2" :class="field" /></label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="bank.is_shared" type="checkbox" /> عمومی در این شعبه</label>
                    <p v-for="(error, key) in bank.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button :class="primary" :disabled="bank.processing">ذخیرهٔ بانک</button>
                        <button v-if="editingBank" type="button" :class="secondary" @click="editBank()">انصراف</button>
                    </div>
                </form>
                <div class="space-y-3">
                    <h2 class="font-semibold">بانک‌های من و بانک‌های عمومی</h2>
                    <p v-if="!banks.data.length" class="text-sm text-gray-600">هنوز بانکی در دسترس نیست.</p>
                    <article v-for="record in banks.data" :key="record.id" :class="{ 'bg-brand-50/60': selectedBank?.id === record.id }" class="min-w-0 space-y-2 rounded-ui border border-ui-line p-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="font-medium">{{ record.title }}</h3>
                            <span class="text-xs text-gray-600">{{ record.is_mine ? 'بانک من · ' : '' }}{{ record.is_shared ? 'عمومی' : 'خصوصی' }} · {{ record.questions_count }} سؤال</span>
                        </div>
                        <p v-if="record.description" class="whitespace-pre-wrap wrap-break-word text-sm text-gray-600">{{ record.description }}</p>
                        <div class="flex flex-wrap gap-4">
                            <Link :href="route('exams.library', { bank: record.id })" :class="secondary" :aria-current="selectedBank?.id === record.id ? 'true' : undefined" preserve-scroll>{{ selectedBank?.id === record.id ? 'بانک انتخاب‌شده' : 'سؤال‌های بانک' }}</Link>
                            <button v-if="record.is_mine" type="button" :class="secondary" @click="editBank(record)">ویرایش</button>
                            <button v-if="record.is_mine" type="button" :disabled="deletion.processing" class="text-sm text-red-700" @click="remove('banks', record.id)">حذف</button>
                        </div>
                    </article>
                    <nav v-if="banks.prev_page_url || banks.next_page_url" class="flex gap-4" aria-label="صفحه‌های بانک سؤال">
                        <Link v-if="banks.prev_page_url" :href="banks.prev_page_url" :class="secondary">قبلی</Link>
                        <Link v-if="banks.next_page_url" :href="banks.next_page_url" :class="secondary">بعدی</Link>
                    </nav>
                </div>
            </section>
            <section class="ui-surface grid gap-5 p-4 sm:p-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                <form class="min-w-0 space-y-3 rounded-ui bg-ui-canvas p-3 sm:p-4" novalidate @submit.prevent="saveQuestion">
                    <h2 class="font-semibold">{{ editingQuestion ? 'ویرایش سؤال' : 'سؤال جدید' }}</h2>
                    <label class="block space-y-1 text-sm">
                        <span>نوع سؤال</span>
                        <select v-model="question.type" :class="field">
                            <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span>متن سؤال</span>
                        <textarea id="question-body" class="scroll-mt-24" v-model="question.body" rows="3" :class="field" />
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
                    <fieldset v-if="ownBanks.length" class="space-y-1">
                        <legend class="mb-1 text-sm font-medium">بانک‌های من</legend>
                        <label v-for="record in ownBanks" :key="record.id" class="flex items-center gap-2 text-sm">
                            <input v-model="question.bank_ids" type="checkbox" :value="record.id" /> {{ record.title }}
                        </label>
                    </fieldset>
                    <label class="flex items-center gap-2 text-sm"><input v-model="question.is_active" type="checkbox" /> فعال برای ساخت آزمون</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="question.is_shared" type="checkbox" /> عمومی در این شعبه</label>
                    <p v-for="(error, key) in question.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button :class="primary" :disabled="question.processing">ذخیرهٔ سؤال</button>
                        <button v-if="editingQuestion" type="button" :class="secondary" @click="editQuestion()">انصراف</button>
                    </div>
                </form>
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold">{{ selectedBank ? selectedBank.title : 'سؤال‌های من و سؤال‌های عمومی' }}</h2>
                        <Link v-if="selectedBank" :href="libraryUrl()" :class="secondary" preserve-scroll>همهٔ سؤال‌ها</Link>
                    </div>
                    <p v-if="!questions.data.length" class="text-sm text-gray-600">سؤالی برای نمایش وجود ندارد.</p>
                    <article v-for="record in questions.data" :key="record.id" class="min-w-0 space-y-2 rounded-ui border border-ui-line p-3">
                        <p class="whitespace-pre-wrap wrap-break-word text-sm font-medium">{{ record.body }}</p>
                        <p class="text-xs text-gray-600">{{ types[record.type] }} · {{ record.score }} نمره · {{ record.is_mine ? 'سؤال من · ' : '' }}{{ record.is_shared ? 'عمومی' : 'خصوصی' }}{{ record.is_active ? '' : ' · غیرفعال' }}</p>
                        <details v-if="record.options || record.accepted_answers || record.model_answer" class="text-sm">
                            <summary class="cursor-pointer text-gray-700">پاسخ‌ها</summary>
                            <ul v-if="record.options" class="mt-2 list-disc space-y-1 ps-5">
                                <li v-for="option in record.options" :key="option.key">{{ option.text }}{{ option.is_correct ? ' (درست)' : '' }}</li>
                            </ul>
                            <p v-else class="mt-2 whitespace-pre-wrap wrap-break-word">{{ record.accepted_answers?.join('، ') ?? record.model_answer }}</p>
                        </details>
                        <div v-if="record.is_mine" class="flex gap-4">
                            <button type="button" :class="secondary" @click="editQuestion(record)">ویرایش</button>
                            <button type="button" :disabled="deletion.processing" class="text-sm text-red-700" @click="remove('questions', record.id)">حذف</button>
                        </div>
                        <button v-else-if="ownBanks.length" type="button" :class="secondary" @click="selectSharedQuestion(record.id)">افزودن به بانک من</button>
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
                    </article>
                    <nav v-if="questions.prev_page_url || questions.next_page_url" class="flex gap-4" aria-label="صفحه‌های سؤال">
                        <Link v-if="questions.prev_page_url" :href="questions.prev_page_url" :class="secondary">قبلی</Link>
                        <Link v-if="questions.next_page_url" :href="questions.next_page_url" :class="secondary">بعدی</Link>
                    </nav>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
