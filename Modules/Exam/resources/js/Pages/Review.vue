<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ title: String, name: String, status: String, can_grade: Boolean, back_url: String, save_url: String, answers: Array });
const form = useForm({ scores: Object.fromEntries(props.answers.filter(answer => answer.requires_manual_grading).map(answer => [answer.id, answer.score])) });
</script>

<template>
    <AppLayout>
        <Head title="پاسخ‌ها و تصحیح" />
        <div class="space-y-5">
            <Link :href="back_url" class="text-sm text-violet-700">بازگشت به آزمون‌های جلسه</Link>
            <h1 class="text-xl font-bold">{{ title }}</h1>
            <p class="text-gray-600">{{ name }} · {{ status }}</p>
            <p v-if="usePage().props.flash.status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800" role="status">{{ usePage().props.flash.status }}</p>
            <p v-if="!can_grade" class="text-sm text-amber-700">آزمون هنوز در جریان است؛ پس از پایان می‌توانید نمره بدهید.</p>
            <form novalidate class="space-y-4" @submit.prevent="form.post(save_url, { preserveScroll: true })">
                <article v-for="(answer, index) in answers" :key="answer.id" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
                    <h2 class="whitespace-pre-wrap font-semibold">{{ index + 1 }}. {{ answer.body }}</h2>
                    <p class="whitespace-pre-wrap text-sm leading-7 text-gray-700">پاسخ: {{ answer.answer || 'بی‌پاسخ' }}</p>
                    <p v-if="!answer.requires_manual_grading" class="text-sm text-emerald-700">پاسخ درست: {{ answer.correct }} · نمره: {{ answer.score ?? '—' }} از {{ answer.max_score }}</p>
                    <template v-else>
                        <label :for="`score-${answer.id}`" class="block text-sm">نمره از {{ answer.max_score }} (خالی: تصحیح‌نشده)</label>
                        <input :id="`score-${answer.id}`" v-model="form.scores[answer.id]" :disabled="!can_grade" type="number" step="0.01" min="0" :max="answer.max_score" dir="ltr" class="w-32 rounded-lg border border-gray-300 p-2 disabled:bg-gray-100" />
                    </template>
                </article>
                <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-600" role="alert">{{ error }}</p>
                <button v-if="can_grade && answers.some(answer => answer.requires_manual_grading)" :disabled="form.processing" class="rounded-lg bg-violet-600 px-5 py-2 text-sm text-white disabled:opacity-50">ثبت نمره‌ها</button>
            </form>
        </div>
    </AppLayout>
</template>
