<script setup>
import { ElForm } from 'element-plus';
import 'element-plus/es/components/form/style/css';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelDialog from '@/Components/Ui/PanelDialog.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import SessionExamFields from './SessionExamFields.vue';

const props = defineProps({
    exam: { type: Object, required: true },
    settings: { type: Object, required: true },
    url: { type: String, required: true },
});
const settingsOpen = ref(false);
const form = useForm({ ...props.settings.defaults });

function open() {
    form.defaults({ ...props.exam.settings }).resetAndClearErrors();
    settingsOpen.value = true;
}

function save() {
    form.patch(props.url, { preserveScroll: true, onSuccess: () => { settingsOpen.value = false; } });
}
</script>

<template>
    <div>
        <PanelButton variant="outline" aria-haspopup="dialog" @click="open">
            <PanelIcon name="pencil" class="size-5" />
            ویرایش تنظیمات آزمون
        </PanelButton>
        <PanelDialog v-model="settingsOpen" title="ویرایش تنظیمات آزمون" :busy="form.processing">
            <ElForm novalidate class="space-y-3" @submit.prevent="save">
                <p class="wrap-break-word text-sm text-ui-muted">{{ exam.title }}</p>
                <SessionExamFields :form="form" :settings="settings" expanded />
                <p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-sm text-red-700">{{ error }}</p>
                <div class="flex flex-wrap justify-end gap-2">
                    <PanelButton variant="outline" :disabled="form.processing" @click="settingsOpen = false">انصراف</PanelButton>
                    <PanelButton type="submit" :loading="form.processing">ذخیرهٔ تنظیمات</PanelButton>
                </div>
            </ElForm>
        </PanelDialog>
    </div>
</template>
