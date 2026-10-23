<script setup>
// The site header's links (PublicSite::navigationUsing(), e.g. the tenant's
// menu, ADR 0012): the top level in a row, submenus open on hover/focus,
// deeper levels indented inside them. Renders its submenus with itself.
defineOptions({ name: 'NavMenu' });

defineProps({
    // [{title, url (null = no link), new_tab, children: [...]}]
    items: { type: Array, required: true },
    depth: { type: Number, default: 0 },
});
</script>

<template>
    <ul
        :class="
            depth === 0
                ? 'flex flex-wrap items-center gap-1'
                : depth === 1
                  ? 'invisible absolute start-0 top-full z-20 min-w-48 rounded-xl bg-white p-2 opacity-0 shadow-lg ring-1 ring-gray-100 transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100'
                  : 'mt-1 space-y-1 border-s-2 border-gray-100 ps-4'
        "
    >
        <li v-for="(item, index) in items" :key="index" :class="depth === 0 ? 'group relative' : ''">
            <a
                v-if="item.url"
                :href="item.url"
                :target="item.new_tab ? '_blank' : null"
                :rel="item.new_tab ? 'noopener' : null"
                class="block rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-violet-50 hover:text-violet-700"
            >
                {{ item.title }}
            </a>
            <span v-else tabindex="0" class="block cursor-default px-3 py-2 text-sm font-medium text-gray-700">{{ item.title }}</span>

            <NavMenu v-if="item.children.length" :items="item.children" :depth="depth + 1" />
        </li>
    </ul>
</template>
