<script setup>
import { ElAlert } from 'element-plus';
import 'element-plus/es/components/alert/style/css';

defineProps({ tone: { type: String, default: 'neutral' } });
</script>

<template>
    <ElAlert :type="tone === 'neutral' ? 'info' : tone" :closable="false" :role="tone === 'error' ? 'alert' : 'status'">
        <slot />
    </ElAlert>
</template>
