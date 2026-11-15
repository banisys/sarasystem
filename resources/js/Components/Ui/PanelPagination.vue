<script setup>
import { ElPagination } from 'element-plus';
import 'element-plus/es/components/pagination/style/css';
</script>

<template>
    <ElPagination :pager-count="5" layout="prev, pager, next" prev-text="قبلی" next-text="بعدی" background />
</template>
