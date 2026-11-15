<script setup>
import { ElCheckbox, ElInput, ElOption, ElSelect } from 'element-plus';
import 'element-plus/es/components/checkbox/style/css';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/input/style/css';
defineProps({
    form: { type: Object, required: true },
    settings: { type: Object, required: true },
    expanded: Boolean,
});

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
    <div class="space-y-4">
        <label class="block space-y-1 text-sm font-medium">
            <span>{{ settings.labels.title }}</span><ElInput v-model="form.title" maxlength="255" />
        </label>
        <div class="grid gap-3 sm:grid-cols-3">
            <label v-for="[key, min, max, step] in basic" :key="key" class="space-y-1 text-sm font-medium">
                <span>{{ settings.labels[key] }}</span>
                <ElInput v-model.number="form[key]" type="number" :min="min" :max="max" :step="step" />
            </label>
        </div>
        <div class="rounded-lg border border-ui-line p-3">
            <ElCheckbox v-model="form.allow_late_start" class="panel-wrap-option">
                <span class="block font-medium">{{ settings.labels.allow_late_start }}</span>
                <span class="mt-1 block text-ui-muted">برای شروع آزمون مهلت پایانی وجود ندارد. مدت پاسخ‌گویی از لحظهٔ شروع هر تلاش حساب می‌شود.</span>
            </ElCheckbox>
        </div>
        <details :open="expanded || undefined" class="rounded-lg border border-gray-200 panel-dark:border-ui-line">
            <summary class="cursor-pointer px-3 py-3 text-sm font-medium text-violet-700 panel-dark:text-brand-700">تنظیمات بیشتر</summary>
            <div class="space-y-4 border-t border-gray-200 panel-dark:border-ui-line p-3">
                <label class="block space-y-1 text-sm font-medium">
                    <span>{{ settings.labels.description }}</span>
                    <ElInput type="textarea" v-model="form.description" :rows="3" maxlength="20000" />
                </label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label v-for="[key, min, max] in advanced" :key="key" class="space-y-1 text-sm font-medium">
                        <span>{{ settings.labels[key] }}</span>
                        <ElInput v-model.number="form[key]" type="number" :min="min" :max="max" />
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="space-y-1 text-sm font-medium">
                        <span>{{ settings.labels.retake_policy }}</span>
                        <ElSelect v-model="form.retake_policy" :aria-label="settings.labels.retake_policy" append-to="#profile-overlays">
                            <ElOption v-for="(label, value) in settings.retake_policies" :key="value" :value="value" :label="label" />
                        </ElSelect>
                    </label>
                    <label class="space-y-1 text-sm font-medium">
                        <span>{{ settings.labels.result_visibility }}</span>
                        <ElSelect v-model="form.result_visibility" :aria-label="settings.labels.result_visibility" append-to="#profile-overlays">
                            <ElOption v-for="(label, value) in settings.result_visibility" :key="value" :value="value" :label="label" />
                        </ElSelect>
                    </label>
                    <label class="space-y-1 text-sm font-medium sm:col-span-2">
                        <span>{{ settings.labels.negative_marking }}</span>
                        <ElSelect v-model="form.negative_marking" clearable :value-on-clear="null" placeholder="بدون نمرهٔ منفی" :aria-label="settings.labels.negative_marking" append-to="#profile-overlays">
                            <ElOption v-for="count in [2, 3, 4, 5, 6]" :key="count" :value="count" :label="`هر ${count} پاسخ غلط، یک پاسخ درست را حذف کند`" />
                        </ElSelect>
                    </label>
                </div>
                <div class="flex flex-wrap gap-4">
                    <ElCheckbox v-for="key in ['shuffle_questions', 'shuffle_options']" :key="key" v-model="form[key]">
                        {{ settings.labels[key] }}
                    </ElCheckbox>
                </div>
            </div>
        </details>
    </div>
</template>
