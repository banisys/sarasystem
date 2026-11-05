<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavMenu from '@/Components/Public/NavMenu.vue';
import TutorialBar from '@/Components/TutorialBar.vue';

// The static header and footer of every public page (ADR 0010); the
// `site` prop comes from App\PublicSite\PublicSite::layoutProps().
const page = usePage();
const site = computed(() => page.props.site);
const user = computed(() => page.props.auth.user);

// The tenant's own colour (ADR 0062): resources/css/app.css works out the
// brand-* shades from these under .site-brand; without one they stay violet.
const brandStyle = computed(() =>
    site.value.tenant.color ? { '--brand': site.value.tenant.color.primary, '--brand-contrast': site.value.tenant.color.contrast } : null,
);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-50" :class="{ 'site-brand': brandStyle }" :style="brandStyle">
        <TutorialBar />
        <header class="border-t-4 border-b border-t-brand-600 border-b-gray-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-4">
                <Link :href="site.tenant.url" class="text-lg font-bold text-gray-900">{{ site.tenant.name }}</Link>
                <Link
                    :href="user ? route('dashboard') : route('login')"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-brand-contrast hover:bg-brand-700"
                >
                    {{ user ? 'ورود به داشبورد' : 'ورود' }}
                </Link>
            </div>
            <nav v-if="site.navigation.length" class="mx-auto max-w-5xl px-4 pb-3">
                <NavMenu :items="site.navigation" />
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer class="border-t-4 border-t-brand-600 bg-white">
            <div class="mx-auto max-w-5xl px-4 py-6 text-sm text-gray-500">
                <nav v-if="site.otherTenants.length" class="mb-4">
                    <p class="text-xs text-gray-400">سایر {{ page.props.tenant.label }}‌ها</p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <li v-for="other in site.otherTenants" :key="other.url">
                            <Link :href="other.url" class="inline-block rounded-full bg-gray-100 px-3 py-1 text-gray-700 hover:bg-brand-50 hover:text-brand-700">
                                {{ other.name }}
                            </Link>
                        </li>
                    </ul>
                </nav>
                <p>{{ site.tenant.name }} · {{ page.props.appName }}</p>
            </div>
        </footer>
    </div>
</template>
