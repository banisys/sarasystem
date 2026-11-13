<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import NavMenu from '@/Components/Public/NavMenu.vue';
import TutorialBar from '@/Components/TutorialBar.vue';
import UiButton from '@/Components/Ui/UiButton.vue';

const page = usePage();
const site = computed(() => page.props.site);
const user = computed(() => page.props.auth.user);

const brandStyle = computed(() => {
    const color = site.value.tenant.color;
    if (!color) return null;

    return {
        '--brand': color.primary,
        '--brand-contrast': color.contrast,
        '--brand-hover-mix': color.contrast === '#ffffff' ? '#000' : '#fff',
    };
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-ui-paper text-ui-ink" :class="{ 'site-brand': brandStyle }" :style="brandStyle">
        <TutorialBar />
        <header class="border-t-4 border-b border-t-brand-600 border-b-ui-line bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-8 sm:py-5">
                <Link :href="site.tenant.url" class="flex min-w-0 items-center gap-3 rounded-ui font-bold">
                    <span class="grid size-10 shrink-0 place-items-center rounded-ui bg-brand-600 text-brand-contrast">
                        <PanelIcon name="book" class="size-5" />
                    </span>
                    <span class="min-w-0 wrap-break-word">{{ site.tenant.name }}</span>
                </Link>
                <UiButton :href="user ? route('dashboard') : route('login')" variant="outline">
                    {{ user ? 'پنل کاربری' : 'ورود' }}
                </UiButton>
            </div>
            <nav v-if="site.navigation.length" class="mx-auto max-w-6xl px-4 pb-3 sm:px-8" aria-label="منوی سایت">
                <NavMenu :items="site.navigation" />
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-8 sm:py-12">
            <slot />
        </main>

        <footer class="border-t border-ui-line bg-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6 text-sm text-ui-muted sm:px-8">
                <nav v-if="site.otherTenants.length" :aria-label="`سایر ${page.props.tenant.label}‌ها`">
                    <p class="mb-2 font-semibold text-ui-ink">سایر {{ page.props.tenant.label }}‌ها</p>
                    <ul class="flex flex-wrap gap-x-4 gap-y-2">
                        <li v-for="other in site.otherTenants" :key="other.url">
                            <Link :href="other.url" class="inline-flex min-h-9 items-center underline-offset-4 hover:underline">
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
