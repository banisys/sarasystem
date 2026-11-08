<script setup>
import { ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';

// A Persian file picker for user-panel forms: a dashed drop area that lists
// the chosen files (v-model: an array of File).
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    multiple: { type: Boolean, default: true },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);
const input = ref(null);
const dragging = ref(false);

function choose(files) {
    emit('update:modelValue', Array.from(files ?? []));
}

function drop(event) {
    dragging.value = false;
    choose(props.multiple ? event.dataTransfer.files : [...event.dataTransfer.files].slice(0, 1));
}

function remove(index) {
    emit('update:modelValue', props.modelValue.filter((_, at) => at !== index));
    if (input.value) {
        input.value.value = '';
    }
}

function size(bytes) {
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} کیلوبایت` : `${(bytes / 1024 / 1024).toFixed(1)} مگابایت`;
}
</script>

<template>
    <div>
        <label
            class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-5 text-center text-sm transition"
            :class="dragging ? 'border-violet-400 bg-violet-50' : 'border-gray-300 bg-gray-50 hover:border-violet-300 hover:bg-violet-50/50'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="drop"
        >
            <PanelIcon name="paperclip" class="size-6 text-violet-600" />
            <span class="font-medium text-gray-800">انتخاب فایل <span class="font-normal text-gray-500">یا رها کردن آن در اینجا</span></span>
            <span v-if="hint" class="text-xs text-gray-500">{{ hint }}</span>
            <input ref="input" type="file" :multiple="multiple" class="sr-only" @change="choose($event.target.files)" />
        </label>
        <ul v-if="modelValue.length" class="mt-2 space-y-1">
            <li v-for="(file, index) in modelValue" :key="`${file.name}-${index}`" class="flex items-center justify-between gap-2 rounded-lg bg-violet-50 px-3 py-1.5 text-sm text-violet-900">
                <span class="truncate" dir="auto">{{ file.name }}</span>
                <span class="flex shrink-0 items-center gap-2 text-xs text-violet-700">
                    {{ size(file.size) }}
                    <button type="button" class="rounded p-0.5 hover:bg-violet-100" aria-label="برداشتن فایل" @click="remove(index)">
                        <PanelIcon name="close" class="size-4" />
                    </button>
                </span>
            </li>
        </ul>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
