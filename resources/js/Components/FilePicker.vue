<script setup>
import { computed } from 'vue';
import { ElUpload } from 'element-plus';
import 'element-plus/es/components/upload/style/css';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import PanelIcon from '@/Components/PanelIcon.vue';

const model = defineModel({ type: Array, default: () => [] });
const props = defineProps({
    multiple: { type: Boolean, default: true },
    accept: { type: String, default: '' },
    disabled: Boolean,
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});

// Element Plus queues files locally; the surrounding Inertia form submits the raw files with the rest of the fields.
const fileList = computed({
    get: () => model.value.map((raw) => ({ name: raw.name, size: raw.size, raw, uid: raw.uid, status: 'ready' })),
    set: (files) => {
        const rawFiles = files.map(({ raw }) => raw);
        model.value = props.multiple ? rawFiles : rawFiles.slice(-1);
    },
});

function remove(uid) {
    fileList.value = fileList.value.filter((file) => file.uid !== uid);
}

function size(bytes) {
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} کیلوبایت` : `${(bytes / 1024 / 1024).toFixed(1)} مگابایت`;
}
</script>

<template>
    <div>
        <ElUpload v-model:file-list="fileList" drag :auto-upload="false" :multiple="multiple" :accept="accept" :disabled="disabled">
            <span class="inline-flex items-center justify-center gap-2 font-semibold text-brand-700">
                <PanelIcon name="paperclip" class="size-5" />
                انتخاب فایل
            </span>
            <p class="mt-1 text-sm text-ui-muted">یا رها کردن آن در اینجا</p>
            <p v-if="hint" class="mt-1 text-xs text-ui-muted">{{ hint }}</p>
            <div v-if="$slots.actions" class="mt-3 flex flex-wrap items-center justify-center gap-3" @click.stop @keydown.stop>
                <slot name="actions" />
            </div>
            <template #file="{ file }">
                <div class="flex w-full items-center justify-between gap-2 px-2 py-1">
                    <span class="min-w-0 truncate text-ui-ink" dir="auto">{{ file.name }}</span>
                    <span class="flex shrink-0 items-center gap-2 text-xs text-ui-muted">
                        {{ size(file.size) }}
                        <PanelButton variant="danger-link" size="small" :disabled="disabled" :aria-label="`برداشتن فایل ${file.name}`" @click="remove(file.uid)">
                            <PanelIcon name="close" class="size-4" />
                        </PanelButton>
                    </span>
                </div>
            </template>
        </ElUpload>
        <PanelNotice v-if="error" tone="error" class="mt-2">{{ error }}</PanelNotice>
    </div>
</template>
