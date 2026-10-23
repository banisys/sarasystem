<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavMenu from '@/Components/Public/NavMenu.vue';

// The static header and footer of every public page (ADR 0010); the
// `site` prop comes from App\PublicSite\PublicSite::layoutProps().
const page = usePage();
const site = computed(() => page.props.site);
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-4">
                <Link :href="site.tenant.url" class="text-lg font-bold text-gray-900">{{ site.tenant.name }}</Link>
                <Link
                    :href="user ? route('dashboard') : route('login')"
                    class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
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

        <footer class="border-t border-gray-200 bg-white">
            <div class="mx-auto max-w-5xl px-4 py-6 text-sm text-gray-500">
                <nav v-if="site.otherTenants.length" class="mb-4">
                    <p class="text-xs text-gray-400">سایر {{ page.props.tenant.label }}‌ها</p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <li v-for="other in site.otherTenants" :key="other.url">
                            <Link :href="other.url" class="inline-block rounded-full bg-gray-100 px-3 py-1 text-gray-700 hover:bg-violet-50 hover:text-violet-700">
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
