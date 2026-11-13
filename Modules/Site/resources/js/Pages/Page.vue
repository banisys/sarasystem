<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    page: { type: Object, required: true },
    // [{component, title, props}] in page order, visible ones only; see Modules\Site\Widgets\Widgets::render().
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
        <article class="ui-surface px-5 py-7 sm:px-10 sm:py-10">
            <h1 class="text-3xl font-extrabold text-ui-ink sm:text-4xl">{{ page.title }}</h1>

            <div v-if="widgets.length" class="mt-8 space-y-8">
                <section v-for="(widget, index) in widgets" :key="index">
                    <h2 v-if="widget.title" class="mb-3 text-xl font-bold text-ui-ink">{{ widget.title }}</h2>
                    <component :is="components[widget.component]" v-bind="widget.props" />
                </section>
            </div>
        </article>
    </PublicLayout>
</template>
