<script setup>
import PanelButton from './PanelButton.vue';
import PanelCard from './PanelCard.vue';
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
    <PanelCard body-class="px-4 py-5 sm:px-6" :class="tone === 'brand' && '[--panel-card-bg:var(--profile-header)] [--panel-card-color:white]'">
        <PanelButton v-if="backHref" :href="backHref" :full-page="fullPageBack" :variant="tone === 'brand' ? 'header-link' : 'link'" class="mb-3" size="small">
            <PanelIcon name="back" class="size-4 ltr:rotate-180" />
            {{ backLabel }}
        </PanelButton>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <span v-if="icon" class="grid size-10 shrink-0 place-items-center rounded-ui" :class="tone === 'brand' ? 'bg-white/12' : 'bg-brand-50 text-brand-700'">
                    <PanelIcon :name="icon" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h1 class="wrap-break-word text-xl font-bold leading-snug sm:text-2xl">{{ title }}</h1>
                    <p v-if="description" class="mt-1 wrap-break-word text-sm leading-7" :class="tone === 'brand' ? 'text-white/85' : 'text-ui-muted'">{{ description }}</p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex max-w-full flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </PanelCard>
</template>
