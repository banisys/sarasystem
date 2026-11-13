<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ title: String, name: String, status: String, can_grade: Boolean, back_url: String, save_url: String, answers: Array });
const form = useForm({ scores: Object.fromEntries(props.answers.filter(answer => answer.requires_manual_grading).map(answer => [answer.id, answer.score])) });
</script>

<template>
    <AppLayout>
        <Head title="پاسخ‌ها و تصحیح" />
        <div class="space-y-5">
            <UiPageHeader :title="title" :description="`${name} · ${status}`" icon="pencil" :back-href="back_url" back-label="آزمون‌های جلسه" />
            <UiNotice v-if="usePage().props.flash.status" tone="success">{{ usePage().props.flash.status }}</UiNotice>
            <UiNotice v-if="!can_grade">آزمون هنوز در جریان است. پس از پایان می‌توانید نمره بدهید.</UiNotice>
            <UiNotice v-if="!answers.length">پاسخی برای نمایش وجود ندارد.</UiNotice>
            <form novalidate class="space-y-4" @submit.prevent="form.post(save_url, { preserveScroll: true })">
                <article v-for="(answer, index) in answers" :key="answer.id" class="ui-surface space-y-3 p-4 sm:p-5">
                    <h2 class="whitespace-pre-wrap wrap-break-word font-semibold">{{ index + 1 }}. {{ answer.body }}</h2>
                    <p class="whitespace-pre-wrap wrap-break-word text-sm leading-7 text-gray-700">پاسخ: {{ answer.answer || 'بی‌پاسخ' }}</p>
                    <p v-if="!answer.requires_manual_grading" class="text-sm text-emerald-700">پاسخ درست: {{ answer.correct }} · نمره: {{ answer.score ?? '—' }} از {{ answer.max_score }}</p>
                    <template v-else>
                        <p v-if="answer.correct" class="whitespace-pre-wrap wrap-break-word text-sm text-emerald-700">پاسخ نمونه: {{ answer.correct }}</p>
                        <label :for="`score-${answer.id}`" class="block text-sm">نمره از {{ answer.max_score }} (خالی: تصحیح‌نشده)</label>
                        <input :id="`score-${answer.id}`" v-model="form.scores[answer.id]" :disabled="!can_grade" type="number" step="0.01" min="0" :max="answer.max_score" dir="ltr" class="ui-field max-w-36"
                            :aria-invalid="!!form.errors[`scores.${answer.id}`]"
                            :aria-describedby="form.errors[`scores.${answer.id}`] ? `score-error-${answer.id}` : undefined" />
                        <p v-if="form.errors[`scores.${answer.id}`]" :id="`score-error-${answer.id}`" class="ui-field-error" role="alert">{{ form.errors[`scores.${answer.id}`] }}</p>
                    </template>
                </article>
                <template v-for="(error, key) in form.errors" :key="key">
                    <UiNotice v-if="!key.startsWith('scores.')" tone="error">{{ error }}</UiNotice>
                </template>
                <UiButton v-if="can_grade && answers.some(answer => answer.requires_manual_grading)" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'در حال ثبت…' : 'ثبت نمره‌ها' }}
                </UiButton>
            </form>
        </div>
    </AppLayout>
</template>
