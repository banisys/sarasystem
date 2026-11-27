<script setup>
import PanelButton from './PanelButton.vue';
import PanelCard from './PanelCard.vue';
import PanelIcon from '@/Components/PanelIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    icon: { type: String, default: null },
    backHref: { type: String, default: null },
    fullPageBack: { type: Boolean, default: false },
    backLabel: { type: String, default: null },
});
</script>

<template>
    <PanelCard class="panel-page-header"
        body-class="grid min-h-28 content-center gap-3 [--el-card-padding:1rem] sm:min-h-33 sm:[--el-card-padding:1.5rem]">
        <PanelButton v-if="backHref" :href="backHref" :full-page="fullPageBack" variant="link" class="justify-self-start" size="small">
            <PanelIcon name="back" class="size-4 ltr:rotate-180" />
            {{ backLabel }}
        </PanelButton>
        <div class="flex flex-wrap items-center justify-between gap-3 sm:gap-5">
            <div class="flex min-w-0 items-center gap-3">
                <span v-if="icon" class="grid size-10 shrink-0 place-items-center rounded-ui bg-brand-50 text-brand-700 sm:size-12">
                    <PanelIcon :name="icon" class="size-6" />
                </span>
                <div class="min-w-0">
                    <h1 class="wrap-break-word text-xl font-extrabold sm:text-2xl">{{ title }}</h1>
                    <p v-if="description" class="mt-1 wrap-break-word text-sm text-ui-muted">{{ description }}</p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex max-w-full flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </PanelCard>
</template>

<style scoped>
.panel-page-header {
    background-image: url('./page-header-pattern.svg');
    background-position: center;
}
</style>
