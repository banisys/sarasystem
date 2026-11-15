<script setup>
import { ElCard } from 'element-plus';
import 'element-plus/es/components/card/style/css';
</script>

<template>
    <ElCard class="panel-card" shadow="never">
        <slot />
    </ElCard>
</template>
