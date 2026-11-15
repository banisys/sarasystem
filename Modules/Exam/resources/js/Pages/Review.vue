<script setup>
import PanelCard from '@/Components/Ui/PanelCard.vue';
import { ElForm, ElInput } from 'element-plus';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
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
            <PanelNotice v-if="usePage().props.flash.status" tone="success">{{ usePage().props.flash.status }}</PanelNotice>
            <PanelNotice v-if="!can_grade">آزمون هنوز در جریان است. پس از پایان می‌توانید نمره بدهید.</PanelNotice>
            <PanelNotice v-if="!answers.length">پاسخی برای نمایش وجود ندارد.</PanelNotice>
            <ElForm novalidate class="space-y-4" @submit.prevent="form.post(save_url, { preserveScroll: true })">
                <PanelCard v-for="(answer, index) in answers" :key="answer.id" body-class="space-y-3 p-4 sm:p-5">
                    <h2 class="whitespace-pre-wrap wrap-break-word font-semibold">{{ index + 1 }}. {{ answer.body }}</h2>
                    <p class="whitespace-pre-wrap wrap-break-word text-sm leading-7 text-gray-700 panel-dark:text-ui-ink">پاسخ: {{ answer.answer || 'بی‌پاسخ' }}</p>
                    <p v-if="!answer.requires_manual_grading" class="text-sm text-emerald-700 panel-dark:text-emerald-300">پاسخ درست: {{ answer.correct }} · نمره: {{ answer.score ?? '—' }} از {{ answer.max_score }}</p>
                    <template v-else>
                        <p v-if="answer.correct" class="whitespace-pre-wrap wrap-break-word text-sm text-emerald-700 panel-dark:text-emerald-300">پاسخ نمونه: {{ answer.correct }}</p>
                        <label :for="`score-${answer.id}`" class="block text-sm">نمره از {{ answer.max_score }} (خالی: تصحیح‌نشده)</label>
                        <ElInput :id="`score-${answer.id}`" v-model.number="form.scores[answer.id]" :disabled="!can_grade" type="number" step="0.01" min="0" :max="answer.max_score" dir="ltr" class="max-w-36"
                            :aria-invalid="!!form.errors[`scores.${answer.id}`]"
                            :aria-describedby="form.errors[`scores.${answer.id}`] ? `score-error-${answer.id}` : undefined" />
                        <p v-if="form.errors[`scores.${answer.id}`]" :id="`score-error-${answer.id}`" class="ui-field-error" role="alert">{{ form.errors[`scores.${answer.id}`] }}</p>
                    </template>
                </PanelCard>
                <template v-for="(error, key) in form.errors" :key="key">
                    <PanelNotice v-if="!key.startsWith('scores.')" tone="error">{{ error }}</PanelNotice>
                </template>
                <PanelButton v-if="can_grade && answers.some(answer => answer.requires_manual_grading)" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'در حال ثبت…' : 'ثبت نمره‌ها' }}
                </PanelButton>
            </ElForm>
        </div>
    </AppLayout>
</template>
