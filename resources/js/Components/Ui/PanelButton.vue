<script setup>
import { Link } from '@inertiajs/vue3';
import { ElButton } from 'element-plus';
import 'element-plus/es/components/button/style/css';

defineProps({
    href: { type: String, default: null },
    fullPage: Boolean,
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
});

const colors = { primary: 'primary', 'outline-brand': 'primary', link: 'primary', danger: 'danger', 'danger-link': 'danger' };
</script>

<template>
    <ElButton
        :tag="href ? (fullPage ? 'a' : Link) : 'button'"
        :href="href ?? undefined"
        :native-type="type"
        :type="colors[variant]"
        :plain="variant === 'outline-brand'"
        :text="variant === 'ghost'"
        :link="variant === 'link' || variant === 'danger-link'"
        :class="{ 'panel-nav-button': variant === 'navigation' }"
    >
        <slot />
    </ElButton>
</template>
