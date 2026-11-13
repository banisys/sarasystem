<script setup>
import { Link } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    icon: { type: String, default: null },
    tone: { type: String, default: 'surface' },
    backHref: { type: String, default: null },
    fullPageBack: { type: Boolean, default: false },
    backLabel: { type: String, default: null },
});
</script>

<template>
    <header class="px-4 py-5 sm:px-6" :class="tone === 'plum' ? 'rounded-ui bg-ui-plum text-white' : 'ui-surface'">
        <component v-if="backHref" :is="fullPageBack ? 'a' : Link" :href="backHref" class="mb-3 inline-flex min-h-8 items-center gap-1.5 text-sm font-semibold hover:underline" :class="tone === 'plum' ? 'text-white' : 'text-brand-700'">
            <PanelIcon name="back" class="size-4 ltr:rotate-180" />
            {{ backLabel }}
        </component>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <span v-if="icon" class="grid size-10 shrink-0 place-items-center rounded-ui" :class="tone === 'plum' ? 'bg-white/12' : 'bg-brand-50 text-brand-700'">
                    <PanelIcon :name="icon" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h1 class="wrap-break-word text-xl font-bold leading-snug sm:text-2xl">{{ title }}</h1>
                    <p v-if="description" class="mt-1 wrap-break-word text-sm leading-7" :class="tone === 'plum' ? 'text-white/85' : 'text-ui-muted'">{{ description }}</p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex max-w-full flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
