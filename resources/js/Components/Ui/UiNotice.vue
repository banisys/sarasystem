<script setup>
defineProps({
    tone: { type: String, default: 'neutral' },
});

const tones = {
    neutral: 'border-ui-line bg-white text-ui-muted',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    error: 'border-rose-200 bg-rose-50 text-rose-900',
};
</script>

<template>
    <p class="rounded-ui border px-4 py-3 text-sm leading-7" :class="tones[tone]" :role="tone === 'error' ? 'alert' : 'status'">
        <slot />
    </p>
</template>
