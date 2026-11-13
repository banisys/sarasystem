<script setup>
defineProps({
    form: { type: Object, required: true },
    settings: { type: Object, required: true },
    expanded: Boolean,
});

const field = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm';
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
            <span>{{ settings.labels.title }}</span><input v-model="form.title" :class="field" maxlength="255" />
        </label>
        <div class="grid gap-3 sm:grid-cols-3">
            <label v-for="[key, min, max, step] in basic" :key="key" class="space-y-1 text-sm font-medium">
                <span>{{ settings.labels[key] }}</span>
                <input v-model="form[key]" type="number" :min="min" :max="max" :step="step" :class="field" />
            </label>
        </div>
        <details :open="expanded || undefined" class="rounded-lg border border-gray-200">
            <summary class="cursor-pointer px-3 py-3 text-sm font-medium text-violet-700">تنظیمات بیشتر</summary>
            <div class="space-y-4 border-t border-gray-200 p-3">
                <label class="block space-y-1 text-sm font-medium">
                    <span>{{ settings.labels.description }}</span>
                    <textarea v-model="form.description" :class="field" rows="3" maxlength="20000" />
                </label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label v-for="[key, min, max] in advanced" :key="key" class="space-y-1 text-sm font-medium">
                        <span>{{ settings.labels[key] }}</span>
                        <input v-model="form[key]" type="number" :min="min" :max="max" :class="field" />
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="space-y-1 text-sm font-medium">
                        <span>{{ settings.labels.retake_policy }}</span>
                        <select v-model="form.retake_policy" :class="field">
                            <option v-for="(label, value) in settings.retake_policies" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-sm font-medium">
                        <span>{{ settings.labels.result_visibility }}</span>
                        <select v-model="form.result_visibility" :class="field">
                            <option v-for="(label, value) in settings.result_visibility" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-sm font-medium sm:col-span-2">
                        <span>{{ settings.labels.negative_marking }}</span>
                        <select v-model="form.negative_marking" :class="field">
                            <option :value="null">بدون نمرهٔ منفی</option>
                            <option v-for="count in [2, 3, 4, 5, 6]" :key="count" :value="count">هر {{ count }} پاسخ غلط، یک پاسخ درست را حذف کند</option>
                        </select>
                    </label>
                </div>
                <div class="flex flex-wrap gap-4">
                    <label v-for="key in ['shuffle_questions', 'shuffle_options']" :key="key" class="flex items-center gap-2 text-sm">
                        <input v-model="form[key]" type="checkbox" /> {{ settings.labels[key] }}
                    </label>
                </div>
            </div>
        </details>
    </div>
</template>
