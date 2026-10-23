<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    page: { type: Object, required: true },
    // [{component, props}] in page order; see Modules\Site\Widgets\Widgets::render().
    widgets: { type: Array, required: true },
});

// Widget components by name (Widgets/Html.vue → "Html").
const components = Object.fromEntries(
    Object.entries(import.meta.glob('../Widgets/*.vue', { eager: true })).map(([path, module]) => [
        path.split('/').pop().replace('.vue', ''),
        module.default,
    ]),
);
</script>

<template>
    <Head :title="page.title" />

    <PublicLayout>
        <article class="rounded-2xl bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">{{ page.title }}</h1>

            <div v-if="widgets.length" class="mt-6 space-y-6">
                <component :is="components[widget.component]" v-for="(widget, index) in widgets" :key="index" v-bind="widget.props" />
            </div>
        </article>
    </PublicLayout>
</template>
