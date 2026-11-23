<script setup>
import { ElOption, ElSelect } from 'element-plus';
import 'element-plus/es/components/select/style/css';
import PanelFormField from '@/Components/Ui/PanelFormField.vue';

const group = defineModel({ type: Number, default: null });
defineProps({ groups: { type: Array, required: true } });
</script>

<template>
    <PanelFormField label="گروه آزمون">
        <ElSelect v-model="group" filterable clearable :value-on-clear="null" placeholder="بدون گروه"
            aria-label="گروه آزمون" no-data-text="گروهی برای این شعبه ثبت نشده است" append-to="#profile-overlays">
            <ElOption v-for="option in groups" :key="option.id" :value="option.id" :label="option.title" />
        </ElSelect>
    </PanelFormField>
</template>
