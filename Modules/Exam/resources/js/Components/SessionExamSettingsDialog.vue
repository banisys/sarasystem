<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import SessionExamFields from './SessionExamFields.vue';

const props = defineProps({
    exam: { type: Object, required: true },
    settings: { type: Object, required: true },
    url: { type: String, required: true },
});
const dialog = ref(null);
const trigger = ref(null);
const form = useForm({ ...props.settings.defaults });

function open() {
    form.defaults({ ...props.exam.settings }).resetAndClearErrors();
    dialog.value.showModal();
}

function save() {
    form.patch(props.url, { preserveScroll: true, onSuccess: () => dialog.value?.close() });
}
</script>

<template>
    <div>
        <UiButton ref="trigger" variant="outline" aria-haspopup="dialog" @click="open">
            <PanelIcon name="pencil" class="size-5" />
            ویرایش تنظیمات آزمون
        </UiButton>
        <dialog ref="dialog" :aria-labelledby="`exam-settings-${exam.id}`"
            class="m-auto w-[calc(100%_-_2rem)] max-w-2xl rounded-2xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/45"
            @cancel="form.processing && $event.preventDefault()" @close="trigger?.$el.focus()">
            <form novalidate class="flex max-h-[85dvh] flex-col" @submit.prevent="save">
                <header class="flex items-center justify-between gap-3 border-b border-gray-200 p-4">
                    <div class="min-w-0">
                        <h2 :id="`exam-settings-${exam.id}`" class="font-semibold">ویرایش تنظیمات آزمون</h2>
                        <p class="mt-1 wrap-break-word text-sm text-ui-muted">{{ exam.title }}</p>
                    </div>
                    <button type="button" aria-label="بستن" :disabled="form.processing"
                        class="grid size-9 place-items-center rounded-lg hover:bg-gray-100" @click="dialog.close()">
                        <PanelIcon name="close" class="size-5" />
                    </button>
                </header>
                <div class="min-h-0 space-y-3 overflow-y-auto p-4">
                    <SessionExamFields :form="form" :settings="settings" expanded />
                    <p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-sm text-red-700">{{ error }}</p>
                </div>
                <footer class="flex items-center justify-end gap-3 border-t border-gray-200 p-4">
                    <button type="button" :disabled="form.processing" class="text-sm text-gray-700" @click="dialog.close()">انصراف</button>
                    <button :disabled="form.processing" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">ذخیرهٔ تنظیمات</button>
                </footer>
            </form>
        </dialog>
    </div>
</template>
