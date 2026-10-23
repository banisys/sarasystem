<script setup>
// HtmlWidget: HTML sanitized on the server (Modules\Site\Widgets\HtmlWidget).
defineProps({
    html: { type: String, required: true },
});
</script>

<template>
    <div class="site-html" v-html="html" />
</template>

<style scoped>
/* Tailwind's preflight strips default element styles; give pasted HTML sensible ones back. */
.site-html :deep(h1) { font-size: 1.875rem; font-weight: 700; margin: 1rem 0 0.5rem; }
.site-html :deep(h2) { font-size: 1.5rem; font-weight: 700; margin: 1rem 0 0.5rem; }
.site-html :deep(h3) { font-size: 1.25rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
.site-html :deep(p) { margin: 0.5rem 0; line-height: 1.9; }
.site-html :deep(ul) { list-style: disc; padding-inline-start: 1.5rem; margin: 0.5rem 0; }
.site-html :deep(ol) { list-style: decimal; padding-inline-start: 1.5rem; margin: 0.5rem 0; }
.site-html :deep(a) { color: rgb(124 58 237); text-decoration: underline; }
.site-html :deep(img), .site-html :deep(iframe) { max-width: 100%; }
.site-html :deep(table) { border-collapse: collapse; }
.site-html :deep(th), .site-html :deep(td) { border: 1px solid rgb(229 231 235); padding: 0.25rem 0.5rem; }
</style>
