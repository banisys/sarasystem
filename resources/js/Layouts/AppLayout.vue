<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import TutorialBar from '@/Components/TutorialBar.vue';

// The user panel's frame (ADR 0069): a side menu at the start (the right in
// Persian) with the modules' links (UserPanel::navigationUsing()), the
// tenant and the account; on small screens it slides in from a top bar.
const page = usePage();
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);
const impersonator = computed(() => page.props.auth.impersonator);
const menuOpen = ref(false);

// «داشبورد» only when no module offers the panel's home (e.g. a teacher's
// calendar, which is then the first menu link).
const links = computed(() => [
    ...(page.props.panelHome || !tenant.value.current ? [] : [{ title: 'داشبورد', url: route('dashboard'), icon: 'home' }]),
    ...(page.props.navigation ?? []),
]);
const homeUrl = computed(() => page.props.panelHome ?? route('dashboard'));

function isActive(url) {
    const target = new URL(url, window.location.origin);
    const current = new URL(page.url, window.location.origin);
    const samePath = current.pathname === target.pathname || current.pathname.startsWith(`${target.pathname.replace(/\/$/, '')}/`);

    // A guardian's «کلاس‌های …» share the path and differ by `for`.
    return samePath && current.searchParams.get('for') === target.searchParams.get('for');
}

function logout() {
    router.post(route('logout'));
}

function leaveImpersonation() {
    router.post(route('impersonation.leave'));
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <TutorialBar />
        <div
            v-if="impersonator"
            class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 bg-amber-500 px-4 py-2 text-sm text-white"
        >
            <span>
                شما ({{ impersonator.name }}) با حساب <strong>{{ user?.name }}</strong> وارد شده‌اید.
            </span>
            <button type="button" class="rounded-md bg-white/20 px-3 py-1 font-medium hover:bg-white/30" @click="leaveImpersonation">
                بازگشت به حساب خودم
            </button>
        </div>

        <!-- Top bar on small screens. -->
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 lg:hidden">
            <button
                type="button"
                class="rounded-lg p-1.5 text-gray-600 hover:bg-gray-100"
                aria-label="منو"
                :aria-expanded="menuOpen"
                @click="menuOpen = true"
            >
                <PanelIcon name="menu" class="size-6" />
            </button>
            <Link :href="homeUrl" class="font-bold text-gray-900">{{ page.props.appName }}</Link>
            <span class="size-9" />
        </header>

        <div class="lg:flex">
            <!-- Backdrop of the sliding menu. -->
            <div v-if="menuOpen" class="fixed inset-0 z-40 bg-gray-900/30 lg:hidden" @click="menuOpen = false" />

            <aside
                class="fixed inset-y-0 start-0 z-50 flex w-64 flex-col border-e border-gray-200 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                :class="menuOpen ? 'translate-x-0' : 'translate-x-full'"
            >
                <div class="flex items-center justify-between px-5 pt-5">
                    <Link :href="homeUrl" class="text-lg font-bold text-gray-900">{{ page.props.appName }}</Link>
                    <button type="button" class="rounded-lg p-1 text-gray-500 hover:bg-gray-100 lg:hidden" aria-label="بستن منو" @click="menuOpen = false">
                        <PanelIcon name="close" class="size-5" />
                    </button>
                </div>

                <div v-if="tenant.current" class="mx-5 mt-3 flex items-center justify-between gap-2 rounded-lg bg-violet-50 px-3 py-2 text-sm">
                    <span class="truncate font-medium text-violet-800">{{ tenant.current.name }}</span>
                    <Link
                        v-if="tenant.available.length > 1"
                        :href="route('tenants.select')"
                        class="shrink-0 text-violet-600 hover:text-violet-900"
                        :title="`تغییر ${tenant.label}`"
                        :aria-label="`تغییر ${tenant.label}`"
                    >
                        <PanelIcon name="switch" class="size-4" />
                    </Link>
                </div>

                <nav class="mt-4 flex-1 space-y-1 overflow-y-auto px-3">
                    <Link
                        v-for="link in links"
                        :key="link.url"
                        :href="link.url"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition"
                        :class="isActive(link.url) ? 'bg-violet-600 font-medium text-white' : 'text-gray-700 hover:bg-gray-100'"
                    >
                        <PanelIcon :name="link.icon" class="size-5 shrink-0" />
                        <span class="truncate">{{ link.title }}</span>
                    </Link>
                    <p v-if="!links.length" class="px-3 py-2 text-sm text-gray-500">موردی برای نمایش نیست.</p>
                </nav>

                <div class="space-y-1 border-t border-gray-200 px-3 py-3 text-sm">
                    <a v-if="page.props.auth.canAccessAdmin" href="/cpadmin" class="flex items-center gap-3 rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-100">
                        <PanelIcon name="squares" class="size-5 shrink-0" />
                        پنل مدیریت
                    </a>
                    <Link
                        :href="route('profile.edit')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2"
                        :class="isActive(route('profile.edit')) ? 'bg-violet-600 font-medium text-white' : 'text-gray-700 hover:bg-gray-100'"
                    >
                        <PanelIcon name="user" class="size-5 shrink-0" />
                        <span class="truncate">{{ user?.name }}</span>
                    </Link>
                    <button type="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800" @click="logout">
                        <PanelIcon name="logout" class="size-5 shrink-0" />
                        خروج
                    </button>
                </div>
            </aside>

            <main class="min-w-0 flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-6xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
