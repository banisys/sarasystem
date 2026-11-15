<script setup>
import { ElDialog } from 'element-plus';
import 'element-plus/es/components/dialog/style/css';

const open = defineModel({ type: Boolean, required: true });
defineProps({
    busy: Boolean,
    width: { type: String, default: 'min(40rem, calc(100% - 2rem))' },
});
</script>

<template>
    <ElDialog
        v-model="open" :width="width" align-center destroy-on-close
        :show-close="!busy" :close-on-click-modal="false" :close-on-press-escape="!busy"
    >
        <slot />
        <template v-if="$slots.footer" #footer><slot name="footer" /></template>
    </ElDialog>
</template>
