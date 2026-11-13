<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, default: null },
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
});

const variants = {
    primary: 'border-transparent bg-brand-600 text-brand-contrast hover:bg-brand-action-hover',
    outline: 'border-ui-line bg-white text-ui-ink hover:bg-ui-canvas',
    ghost: 'border-transparent text-ui-muted hover:bg-ui-canvas hover:text-ui-ink',
};
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href || undefined"
        :type="href ? undefined : type"
        class="ui-button"
        :class="variants[variant]"
    >
        <slot />
    </component>
</template>
