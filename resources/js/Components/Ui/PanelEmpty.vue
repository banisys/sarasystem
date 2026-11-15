<script setup>
import { ElEmpty } from 'element-plus';
import 'element-plus/es/components/empty/style/css';

defineProps({ description: { type: String, required: true } });
</script>

<template>
    <ElEmpty :description="description" :image-size="64" />
</template>
