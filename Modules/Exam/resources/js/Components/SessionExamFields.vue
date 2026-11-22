<script setup>
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import { ElCheckbox, ElCollapse, ElCollapseItem, ElInput, ElOption, ElSelect } from 'element-plus';
import 'element-plus/es/components/checkbox/style/css';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/input/style/css';
import { ref } from 'vue';
import 'element-plus/es/components/collapse/style/css';
const props = defineProps({
    form: { type: Object, required: true },
    settings: { type: Object, required: true },
    expanded: Boolean,
});

const openSections = ref(props.expanded ? ['advanced'] : []);

const basic = [
    ['duration_minutes', 1, 1440, 1],
    ['total_score', 1, 1000, 0.01],
    ['passing_score', 0, 1000, 0.01],
];
const advanced = [
    ['grace_minutes', 0, 60],
    ['questions_per_page', 1, 100],
    ['max_attempts', 1, 100],
];
</script>

<template>
    <div class="grid gap-[var(--exam-settings-gap,1rem)]">
        <PanelFormField :label="settings.labels.title">
            <ElInput v-model="form.title" maxlength="255" />
        </PanelFormField>
        <div class="grid gap-[var(--exam-settings-gap,0.75rem)] sm:grid-cols-3">
            <PanelFormField v-for="[key, min, max, step] in basic" :key="key" :label="settings.labels[key]">
                <ElInput v-model.number="form[key]" type="number" :min="min" :max="max" :step="step" />
            </PanelFormField>
        </div>
        <div class="rounded-lg border border-ui-line p-3">
            <ElCheckbox v-model="form.allow_late_start" class="panel-wrap-option">
                <span class="block font-medium">{{ settings.labels.allow_late_start }}</span>
                <span class="mt-1 block text-ui-muted">برای شروع آزمون مهلت پایانی وجود ندارد. مدت پاسخ‌گویی از لحظهٔ شروع هر تلاش حساب می‌شود.</span>
            </ElCheckbox>
        </div>
        <ElCollapse v-model="openSections" class="panel-settings-collapse sm:[--profile-settings-padding:1.5rem]!">
            <ElCollapseItem name="advanced" title="تنظیمات بیشتر">
                <div class="grid gap-[var(--exam-settings-gap,1rem)]">
                    <PanelFormField :label="settings.labels.description">
                        <ElInput type="textarea" v-model="form.description" :rows="3" maxlength="20000" />
                    </PanelFormField>
                    <div class="grid gap-[var(--exam-settings-gap,0.75rem)] sm:grid-cols-3">
                        <PanelFormField v-for="[key, min, max] in advanced" :key="key" :label="settings.labels[key]">
                            <ElInput v-model.number="form[key]" type="number" :min="min" :max="max" />
                        </PanelFormField>
                    </div>
                    <div class="grid gap-[var(--exam-settings-gap,0.75rem)] sm:grid-cols-2">
                        <PanelFormField :label="settings.labels.retake_policy">
                            <ElSelect v-model="form.retake_policy" :aria-label="settings.labels.retake_policy" append-to="#profile-overlays">
                                <ElOption v-for="(label, value) in settings.retake_policies" :key="value" :value="value" :label="label" />
                            </ElSelect>
                        </PanelFormField>
                        <PanelFormField :label="settings.labels.result_visibility">
                            <ElSelect v-model="form.result_visibility" :aria-label="settings.labels.result_visibility" append-to="#profile-overlays">
                                <ElOption v-for="(label, value) in settings.result_visibility" :key="value" :value="value" :label="label" />
                            </ElSelect>
                        </PanelFormField>
                        <PanelFormField :label="settings.labels.negative_marking" class="sm:col-span-2">
                            <ElSelect v-model="form.negative_marking" clearable :value-on-clear="null" placeholder="بدون نمرهٔ منفی" :aria-label="settings.labels.negative_marking" append-to="#profile-overlays">
                                <ElOption v-for="count in [2, 3, 4, 5, 6]" :key="count" :value="count" :label="`هر ${count} پاسخ غلط، یک پاسخ درست را حذف کند`" />
                            </ElSelect>
                        </PanelFormField>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <ElCheckbox v-for="key in ['shuffle_questions', 'shuffle_options']" :key="key" v-model="form[key]">
                            {{ settings.labels[key] }}
                        </ElCheckbox>
                    </div>
                </div>
            </ElCollapseItem>
        </ElCollapse>
    </div>
</template>
