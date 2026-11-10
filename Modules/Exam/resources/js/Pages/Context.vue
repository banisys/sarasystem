<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '../format.js';

const props = defineProps({ context: Object, templates: Array, exams: Array, results: Object });
const assignment = useForm({ template_id: '' });
const publication = useForm({ is_published: false });
const parameters = () => [props.context.type, props.context.id];
function assign() {
    assignment.post(route('exams.context.store', parameters()), { preserveScroll: true, onSuccess: () => assignment.reset() });
}
function publish(exam) {
    publication.is_published = !exam.is_published;
    publication.patch(route('exams.context.publish', [...parameters(), exam.id]), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="آزمون‌های جلسه" />
        <div class="space-y-6">
            <a :href="context.back_url" class="text-sm text-violet-700 hover:underline">بازگشت به جلسه</a>
            <div><h1 class="text-xl font-bold text-gray-900">آزمون‌های جلسه</h1><p class="mt-2 text-sm text-gray-600">{{ context.title }}</p></div>
            <p v-if="usePage().props.flash.status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800" role="status">{{ usePage().props.flash.status }}</p>
            <form v-if="context.can_manage" novalidate class="space-y-3 rounded-2xl bg-white p-5 shadow-sm" @submit.prevent="assign">
                <h2 class="font-semibold">افزودن آزمون</h2>
                <p class="text-sm leading-7 text-gray-600">از آزمون منتشرشده یک نسخهٔ مستقل برای این جلسه ساخته می‌شود. زمان شروع و پایان از جلسه گرفته می‌شود و اعضای کلاس در روز جلسه می‌توانند شرکت کنند. نمره‌ها و دفعات شرکت با جلسه‌های دیگر مشترک نیست.</p>
                <p class="text-sm text-gray-600">تنظیمات و تعداد سؤال از هر بانک کپی می‌شوند و بانک‌های سؤال مشترک می‌مانند؛ تغییر زمان جلسه بعداً، زمان آزمون ساخته‌شده را تغییر نمی‌دهد. تنظیمات نسخهٔ جلسه از بخش «آزمون‌ها» در پنل مدیریت قابل ویرایش است.</p>
                <label for="exam-template" class="block text-sm font-medium">آزمون آماده</label>
                <select id="exam-template" v-model="assignment.template_id" class="w-full rounded-lg border border-gray-300 bg-white p-2">
                    <option value="">انتخاب کنید</option><option v-for="exam in templates" :key="exam.id" :value="exam.id">{{ exam.title }}</option>
                </select>
                <p v-if="!templates.length" class="text-sm text-gray-600">ابتدا در بخش «آزمون‌ها» یک آزمون بسازید و منتشر کنید.</p>
                <p v-for="(error, key) in assignment.errors" :key="key" class="text-sm text-red-600" role="alert">{{ error }}</p>
                <button :disabled="assignment.processing || !templates.length" class="rounded-lg bg-violet-600 px-4 py-2 text-sm text-white disabled:opacity-50">افزودن به جلسه</button>
            </form>
            <p v-if="!exams.length" class="rounded-2xl bg-white p-5 text-gray-600">آزمونی برای این جلسه ثبت نشده است.</p>
            <article v-for="exam in exams" :key="exam.id" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900">{{ exam.title }}</h2>
                <p class="text-sm text-gray-600">{{ exam.is_published ? 'منتشرشده' : 'پنهان از شرکت‌کنندگان' }} · از {{ formatDateTime(exam.starts_at) }} تا {{ formatDateTime(exam.ends_at) }}</p>
                <p v-if="context.can_manage" class="text-sm text-gray-600">تعداد دفعات شرکت: {{ exam.attempts_count }}</p>
                <div class="flex flex-wrap gap-4">
                    <Link v-if="exam.take_url" :href="exam.take_url" class="text-sm font-medium text-violet-700">ورود به آزمون و مشاهدهٔ نتیجه</Link>
                    <button v-if="context.can_manage" :disabled="publication.processing" class="text-sm text-gray-700 underline disabled:opacity-50" @click="publish(exam)">{{ exam.is_published ? 'توقف انتشار' : 'انتشار آزمون' }}</button>
                </div>
            </article>
            <p v-for="(error, key) in publication.errors" :key="key" class="text-sm text-red-600" role="alert">{{ error }}</p>
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
