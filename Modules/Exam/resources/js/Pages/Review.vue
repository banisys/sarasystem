<script setup>
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import { ElForm, ElInput, ElTag } from 'element-plus';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import 'element-plus/es/components/tag/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ title: String, name: String, status: String, can_grade: Boolean, back_url: String, save_url: String, answers: Array });
const form = useForm({ scores: Object.fromEntries(props.answers.filter(answer => answer.requires_manual_grading).map(answer => [answer.id, answer.score])) });
</script>

<template>
    <AppLayout>
        <Head title="پاسخ‌ها و تصحیح" />
        <div class="grid gap-4 sm:gap-5">
            <UiPageHeader :title="title" :description="name" icon="pencil" :back-href="back_url" back-label="آزمون‌های جلسه">
                <template #actions>
                    <ElTag :type="can_grade ? 'info' : 'warning'" round>{{ status }}</ElTag>
                </template>
            </UiPageHeader>
            <PanelNotice v-if="!can_grade">آزمون هنوز در جریان است. پس از پایان می‌توانید نمره بدهید.</PanelNotice>
            <PanelNotice v-if="!answers.length">پاسخی برای نمایش وجود ندارد.</PanelNotice>
            <ElForm v-else novalidate class="grid gap-3 sm:gap-4" @submit.prevent="form.post(save_url, { preserveScroll: true })">
                <ol class="grid gap-3 sm:gap-4" aria-label="پاسخ‌های آزمون">
                    <li v-for="(answer, index) in answers" :key="answer.id" class="min-w-0">
                        <PanelCard body-class="grid gap-3 [--el-card-padding:0.875rem] sm:gap-4 sm:[--el-card-padding:1.25rem]">
                            <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
                                <h2 class="flex min-w-0 flex-1 basis-full items-start gap-3 font-semibold leading-7 sm:basis-48">
                                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-sm text-brand-700 panel-dark:bg-white/8 panel-dark:text-ui-ink">{{ index + 1 }}</span>
                                    <span class="min-w-0 whitespace-pre-wrap wrap-break-word">{{ answer.body }}</span>
                                </h2>
                                <ElTag type="info" effect="plain" round class="ms-10 sm:ms-0">
                                    {{ answer.requires_manual_grading ? 'تصحیح دستی' : `نمره: ${answer.score ?? '—'} از ${answer.max_score}` }}
                                </ElTag>
                            </div>

                            <dl class="grid gap-2 sm:gap-3" :class="{ 'md:grid-cols-2': answer.correct }">
                                <div class="min-w-0 rounded-ui bg-ui-canvas px-3 py-2.5 sm:p-4">
                                    <dt class="text-xs font-semibold text-ui-muted">پاسخ شرکت‌کننده</dt>
                                    <dd class="mt-1 whitespace-pre-wrap wrap-break-word text-sm leading-7" :class="{ 'text-ui-muted': !answer.answer }">{{ answer.answer || 'بی‌پاسخ' }}</dd>
                                </div>
                                <div v-if="answer.correct" class="min-w-0 rounded-ui bg-emerald-50 px-3 py-2.5 text-emerald-900 panel-dark:bg-emerald-950/40 panel-dark:text-emerald-100 sm:p-4">
                                    <dt class="text-xs font-semibold">{{ answer.requires_manual_grading ? 'پاسخ نمونه' : 'پاسخ درست' }}</dt>
                                    <dd class="mt-1 whitespace-pre-wrap wrap-break-word text-sm leading-7">{{ answer.correct }}</dd>
                                </div>
                            </dl>

                            <div v-if="answer.requires_manual_grading" class="border-t border-ui-line pt-3 sm:pt-4">
                                <PanelFormField :label="`نمره از ${answer.max_score}`" :for="`score-${answer.id}`" :error="form.errors[`scores.${answer.id}`]">
                                    <div class="grid min-w-0 gap-1.5">
                                        <ElInput :id="`score-${answer.id}`" v-model.number="form.scores[answer.id]" :disabled="!can_grade" type="number" step="0.01" min="0" :max="answer.max_score" dir="ltr" class="max-w-36"
                                            inputmode="decimal" :aria-invalid="!!form.errors[`scores.${answer.id}`]"
                                            :aria-describedby="form.errors[`scores.${answer.id}`] ? `score-error-${answer.id}` : `score-hint-${answer.id}`" />
                                        <p v-if="!form.errors[`scores.${answer.id}`]" :id="`score-hint-${answer.id}`" class="text-xs leading-5 text-ui-muted">نمرۀ خالی یعنی پاسخ هنوز تصحیح نشده است.</p>
                                    </div>
                                    <template #error="{ error }">
                                        <p :id="`score-error-${answer.id}`" class="mt-1.5 basis-full text-xs leading-5 text-red-700 panel-dark:text-red-300" role="alert">{{ error }}</p>
                                    </template>
                                </PanelFormField>
                            </div>
                        </PanelCard>
                    </li>
                </ol>
                <template v-for="(error, key) in form.errors" :key="key">
                    <PanelNotice v-if="!key.startsWith('scores.')" tone="error">{{ error }}</PanelNotice>
                </template>
                <div v-if="can_grade && answers.some(answer => answer.requires_manual_grading)" class="flex justify-end">
                    <PanelButton type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        <PanelIcon name="check" class="size-4" />
                        {{ form.processing ? 'در حال ثبت…' : 'ثبت نمره‌ها' }}
                    </PanelButton>
                </div>
            </ElForm>
        </div>
    </AppLayout>
</template>
