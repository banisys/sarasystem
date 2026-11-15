<script setup>
import { ElFormItem } from 'element-plus';
import 'element-plus/es/components/form-item/style/css';
</script>

<template>
    <ElFormItem label-position="top" class="panel-form-field">
        <slot />
        <template v-if="$slots.error" #error="{ error }">
            <slot name="error" :error="error" />
        </template>
    </ElFormItem>
</template>
