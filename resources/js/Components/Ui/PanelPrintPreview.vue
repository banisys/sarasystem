<script setup>
import { useHttp } from '@inertiajs/vue3';
import { ref, useTemplateRef } from 'vue';
import PanelButton from './PanelButton.vue';
import PanelDialog from './PanelDialog.vue';
import PanelIcon from '@/Components/PanelIcon.vue';

const props = defineProps({
    url: { type: String, required: true },
    title: { type: String, required: true },
    printLabel: { type: String, default: 'چاپ' },
});
const open = ref(false);
const result = ref(null);
const ready = ref(false);
const frame = useTemplateRef('paper');
const request = useHttp();
let requestId = 0;

async function show() {
    const currentRequest = ++requestId;
    result.value = null;
    ready.value = false;
    open.value = true;

    try {
        const response = await request.get(props.url);
        if (currentRequest === requestId) result.value = response;
    } catch {
        if (currentRequest === requestId) result.value = { error: 'دریافت پیش‌نمایش ممکن نشد. دوباره تلاش کنید.' };
    }
}

function close() {
    requestId++;
    request.cancel();
}

function print() {
    frame.value?.contentWindow?.print();
}
</script>

<template>
    <div>
        <PanelButton variant="outline-brand" aria-haspopup="dialog" @click="show">
            <PanelIcon name="document" class="size-5" />
            {{ title }}
        </PanelButton>
        <PanelDialog v-model="open" :title="title" width="min(62rem, calc(100% - 2rem))" @close="close">
            <p v-if="!result" role="status">در حال دریافت پیش‌نمایش…</p>
            <p v-else-if="result.error" role="status">{{ result.error }}</p>
            <div v-else class="space-y-3">
                <p v-if="result.notice" class="text-sm text-ui-muted">{{ result.notice }}</p>
                <iframe ref="paper" :srcdoc="result.html" :title="title" sandbox="allow-same-origin allow-modals"
                    class="h-[65vh] w-full rounded-ui border border-ui-line bg-white" @load="ready = true" />
            </div>
            <template #footer>
                <div class="flex flex-wrap justify-end gap-2">
                    <PanelButton variant="outline" @click="open = false">بستن</PanelButton>
                    <PanelButton v-if="result?.html" :disabled="!ready" @click="print">{{ printLabel }}</PanelButton>
                </div>
            </template>
        </PanelDialog>
    </div>
</template>
