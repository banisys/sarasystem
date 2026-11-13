<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, useHttp, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import ExamPreview from '../Components/ExamPreview.vue';
import { formatDateTime } from '../format.js';

const props = defineProps({ context: Object, banks: Array, exams: Array, results: Object, library_url: String });
const bankRow = () => ({ bank_id: '', question_count: 1 });
const generation = useForm({ title: '', duration_minutes: 30, total_score: 20, passing_score: 12, banks: [bankRow()] });
const inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm';
const bankFor = (id) => props.banks.find((bank) => bank.id === id);
const dialog = ref(null);
const editingRow = ref(null);
const candidates = ref([]);
const checked = ref([]);
const loadError = ref(false);
const questionRequest = useHttp();
let dialogTrigger = null;
const taken = computed(() => new Set(generation.banks.flatMap((row) =>
    row === editingRow.value ? [] : (row.question_ids ?? []),
)));

async function openQuestions(row, trigger) {
    dialogTrigger = trigger;
    editingRow.value = row;
    candidates.value = [];
    checked.value = [];
    loadError.value = false;
    dialog.value.showModal();

    try {
        const { questions } = await questionRequest.get(route('exams.context.bank-questions', [...parameters(), row.bank_id]));
        if (!dialog.value.open || editingRow.value !== row) return;

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
        if (dialog.value.open && editingRow.value === row) loadError.value = true;
    }
}

function closeQuestions() {
    questionRequest.cancel();
    editingRow.value = null;
    dialogTrigger?.focus();
    dialogTrigger = null;
}

function applyQuestions() {
    editingRow.value.question_ids = [...checked.value];
    editingRow.value.question_count = checked.value.length;
    dialog.value.close();
}
function generate() {
    generation.post(route('exams.context.store', parameters()), { preserveScroll: true, onSuccess: () => generation.reset() });
}
const publication = useForm({ is_published: false });
const deletion = useForm({});
const parameters = () => [props.context.type, props.context.id];
function publish(exam) {
    publication.is_published = !exam.is_published;
    publication.patch(route('exams.context.publish', [...parameters(), exam.id]), { preserveScroll: true });
}
function remove(exam) {
    if (window.confirm(`«${exam.title}» از این جلسه حذف شود؟`)) {
        deletion.delete(exam.delete_url, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="آزمون‌های جلسه" />
        <div class="space-y-6">
            <a :href="context.back_url" class="text-sm text-violet-700 hover:underline">بازگشت به جلسه</a>
            <div><h1 class="text-xl font-bold text-gray-900">آزمون‌های جلسه</h1><p class="mt-2 text-sm text-gray-600">{{ context.title }}</p></div>
            <p v-if="usePage().props.flash.status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800" role="status">{{ usePage().props.flash.status }}</p>
            <section v-if="context.can_manage" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold">ساخت آزمون از بانک سؤال</h2>
                    <Link v-if="library_url" :href="library_url" class="text-sm font-medium text-violet-700 hover:underline">بانک سؤال</Link>
                </div>
                <p v-if="!banks.some((bank) => bank.question_count > 0)" class="text-sm text-gray-600">
                    برای ساخت آزمون، در بانک خودتان سؤال فعال ثبت کنید یا از یک بانک عمومیِ دارای سؤال استفاده کنید.
                </p>
                <form v-else novalidate class="space-y-4" @submit.prevent="generate">
                    <label class="block space-y-1 text-sm font-medium">
                        <span>عنوان آزمون</span><input v-model="generation.title" :class="inputClass" />
                    </label>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="space-y-1 text-sm font-medium">
                            <span>مدت (دقیقه)</span><input v-model="generation.duration_minutes" type="number" min="1" :class="inputClass" />
                        </label>
                        <label class="space-y-1 text-sm font-medium">
                            <span>نمرهٔ کل</span><input v-model="generation.total_score" type="number" min="1" step="0.01" :class="inputClass" />
                        </label>
                        <label class="space-y-1 text-sm font-medium">
                            <span>نمرهٔ قبولی</span><input v-model="generation.passing_score" type="number" min="0" step="0.01" :class="inputClass" />
                        </label>
                    </div>
                    <div v-for="(row, index) in generation.banks" :key="index" class="grid items-end gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                        <label class="space-y-1 text-sm font-medium">
                            <span>بانک سؤال</span>
                            <select v-model="row.bank_id" :class="inputClass" @change="delete row.question_ids">
                                <option value="">انتخاب کنید</option>
                                <option v-for="bank in banks" :key="bank.id" :value="bank.id"
                                    :disabled="!bank.question_count || generation.banks.some((other) => other !== row && other.bank_id === bank.id)">
                                    {{ bank.title }} · {{ bank.is_mine ? 'بانک من' : 'عمومی' }} · {{ bank.question_count }} سؤال
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1 text-sm font-medium">
                            <span>تعداد سؤال</span>
                            <input v-model="row.question_count" type="number" min="1" :max="Math.min(bankFor(row.bank_id)?.question_count ?? 50, 50)" :readonly="!!row.question_ids" :class="inputClass" />
                        </label>
                        <div class="flex items-center justify-end gap-2">
                            <button v-if="row.question_ids" type="button" class="text-sm text-violet-700 hover:underline" @click="delete row.question_ids">انتخاب تصادفی</button>
                            <button v-if="generation.banks.length > 1" type="button" class="px-2 py-2 text-sm text-red-700" @click="generation.banks.splice(index, 1)">حذف بانک</button>
                            <button type="button" :disabled="!row.bank_id" title="انتخاب سؤال‌ها" aria-label="انتخاب سؤال‌ها" aria-haspopup="dialog"
                                class="grid size-10 shrink-0 place-items-center rounded-lg border border-violet-200 bg-white text-violet-700 hover:bg-violet-50 disabled:opacity-40"
                                @click="openQuestions(row, $event.currentTarget)">
                                <PanelIcon name="clipboard" class="size-5" />
                            </button>
                        </div>
                    </div>
                    <button v-if="generation.banks.length < Math.min(10, banks.filter((bank) => bank.question_count > 0).length)" type="button"
                        class="text-sm font-medium text-violet-700 hover:underline" @click="generation.banks.push(bankRow())">افزودن بانک دیگر</button>
                    <p class="text-sm text-gray-600">سؤال‌ها به‌طور پیش‌فرض تصادفی و بدون تکرار انتخاب می‌شوند. با آیکن کنار هر بانک می‌توانید سؤال‌ها را تغییر دهید. زمان برگزاری از جلسه گرفته می‌شود.</p>
                    <p v-for="(error, key) in generation.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
                    <button :disabled="generation.processing" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">ساخت پیش‌نویس آزمون</button>
                </form>
                <dialog ref="dialog" aria-labelledby="bank-question-title"
                    class="m-auto w-[calc(100%_-_2rem)] max-w-2xl rounded-2xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/45"
                    @close="closeQuestions">
                    <div class="flex max-h-[80dvh] flex-col">
                        <header class="flex items-start justify-between gap-3 border-b border-gray-200 p-4">
                            <div>
                                <h2 id="bank-question-title" class="font-semibold">انتخاب سؤال‌ها</h2>
                                <p class="mt-1 text-sm text-gray-600">{{ bankFor(editingRow?.bank_id)?.title }}</p>
                            </div>
                            <button type="button" autofocus aria-label="بستن" class="grid size-9 place-items-center rounded-lg hover:bg-gray-100" @click="dialog.close()">
                                <PanelIcon name="close" class="size-5" />
                            </button>
                        </header>
                        <div class="min-h-0 space-y-2 overflow-y-auto p-4">
                            <p v-if="questionRequest.processing" role="status" class="text-sm text-gray-600">در حال دریافت سؤال‌ها…</p>
                            <p v-else-if="loadError" role="alert" class="text-sm text-red-700">دریافت سؤال‌ها ممکن نشد. پنجره را ببندید و دوباره تلاش کنید.</p>
                            <p v-else-if="!candidates.length" class="text-sm text-gray-600">سؤال فعالی در این بانک در دسترس نیست.</p>
                            <p v-else-if="!checked.length && candidates.every(({ id }) => taken.has(id))" class="text-sm text-gray-600">همهٔ سؤال‌های این بانک در ردیف‌های دیگر انتخاب شده‌اند.</p>
                            <label v-for="question in candidates" :key="question.id" class="flex items-start gap-3 rounded-lg border border-gray-200 p-3"
                                :class="checked.includes(question.id) ? 'bg-violet-50' : ''">
                                <input v-model="checked" type="checkbox" :value="question.id" class="mt-1"
                                    :disabled="taken.has(question.id) || (checked.length >= 50 && !checked.includes(question.id))" />
                                <span class="min-w-0 space-y-1">
                                    <span class="block whitespace-pre-wrap break-words text-sm">{{ question.body }}</span>
                                    <span class="block text-xs text-gray-600">{{ question.type }} · {{ question.score }} نمره{{ taken.has(question.id) ? ' · انتخاب‌شده در بانک دیگر' : '' }}</span>
                                </span>
                            </label>
                        </div>
                        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 p-4">
                            <span class="text-sm text-gray-700" aria-live="polite">{{ checked.length }} سؤال انتخاب شده (حداکثر ۵۰)</span>
                            <div class="flex items-center gap-3">
                                <button type="button" class="text-sm text-gray-700" @click="dialog.close()">انصراف</button>
                                <button type="button" :disabled="!checked.length || questionRequest.processing || loadError"
                                    class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" @click="applyQuestions">ثبت انتخاب</button>
                            </div>
                        </footer>
                    </div>
                </dialog>
            </section>
            <p v-if="!exams.length" class="rounded-2xl bg-white p-5 text-gray-600">آزمونی برای این جلسه ثبت نشده است.</p>
            <article v-for="exam in exams" :key="exam.id" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-semibold text-gray-900">{{ exam.title }}</h2>
                    <ExamPreview v-if="exam.preview_url" :exam="exam" />
                </div>
                <p class="text-sm text-gray-600">{{ exam.is_published ? 'منتشرشده' : 'پنهان از شرکت‌کنندگان' }} · از {{ formatDateTime(exam.starts_at) }} تا {{ formatDateTime(exam.ends_at) }}</p>
                <p v-if="context.can_manage" class="text-sm text-gray-600">تعداد دفعات شرکت: {{ exam.attempts_count }}</p>
                <div class="flex flex-wrap gap-4">
                    <Link v-if="exam.take_url" :href="exam.take_url" class="text-sm font-medium text-violet-700">ورود به آزمون و مشاهدهٔ نتیجه</Link>
                    <button v-if="context.can_manage" :disabled="publication.processing || deletion.processing" class="text-sm text-gray-700 underline disabled:opacity-50" @click="publish(exam)">{{ exam.is_published ? 'توقف انتشار' : 'انتشار آزمون' }}</button>
                    <button v-if="exam.delete_url" type="button" :disabled="deletion.processing || publication.processing" class="text-sm font-medium text-red-700 underline disabled:opacity-50" @click="remove(exam)">حذف آزمون</button>
                </div>
            </article>
            <p v-for="(error, key) in publication.errors" :key="key" class="text-sm text-red-600" role="alert">{{ error }}</p>
            <p v-if="deletion.errors.delete_exam" class="text-sm text-red-600" role="alert">{{ deletion.errors.delete_exam }}</p>
            <section v-if="results" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900">نتایج و تصحیح</h2>
                <p v-if="!results.data.length" class="text-sm text-gray-600">هنوز کسی در آزمون شرکت نکرده است.</p>
                <div v-for="attempt in results.data" :key="attempt.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <div><p class="font-medium">{{ attempt.name }}</p><p class="mt-1 text-sm text-gray-500">{{ attempt.exam }} · {{ attempt.status }}</p></div>
                    <span v-if="attempt.score !== null" class="text-sm">{{ attempt.score }} از {{ attempt.total_score }}</span>
                    <Link :href="attempt.url" class="text-sm text-violet-700">پاسخ‌ها و تصحیح</Link>
                </div>
                <nav v-if="results.prev_page_url || results.next_page_url" class="flex gap-6" aria-label="صفحه‌های نتایج">
                    <Link v-if="results.prev_page_url" :href="results.prev_page_url" class="text-sm text-violet-700">قبلی</Link>
                    <Link v-if="results.next_page_url" :href="results.next_page_url" class="text-sm text-violet-700">بعدی</Link>
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
