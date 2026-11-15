<script setup>
import { Link } from '@inertiajs/vue3';
import PanelCard from './PanelCard.vue';

defineProps({
    href: { type: String, required: true },
    cardClass: { type: [String, Array], default: '' },
    bodyClass: { type: String, default: 'p-4 sm:p-5' },
});
</script>

<template>
    <Link :href="href" class="group block min-w-0 rounded-ui focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
        <PanelCard :class="['panel-card-link h-full', cardClass]" :body-class="bodyClass">
            <slot />
        </PanelCard>
    </Link>
</template>
