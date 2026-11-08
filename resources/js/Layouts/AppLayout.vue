<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
const canSwitchTenant = computed(() => tenant.value.available.length > 1);

function isActive(url) {
    const target = new URL(url, window.location.origin);
    const current = new URL(page.url, window.location.origin);
    const samePath = current.pathname === target.pathname || current.pathname.startsWith(`${target.pathname.replace(/\/$/, '')}/`);

    // A guardian's «کلاس‌های …» share the path and differ by `for`.
    return samePath && current.searchParams.get('for') === target.searchParams.get('for');
}

// Escape closes the sliding menu on small screens.
function closeOnEscape(event) {
    if (event.key === 'Escape') {
        menuOpen.value = false;
    }
}
onMounted(() => window.addEventListener('keydown', closeOnEscape));
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape));

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
            <Link :href="homeUrl" class="flex items-center gap-2 font-bold text-gray-900">
                <span class="flex size-7 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-violet-700 text-sm text-white">{{ page.props.appName?.charAt(0) }}</span>
                {{ page.props.appName }}
            </Link>
            <span class="size-9" />
        </header>

        <div class="lg:flex">
            <!-- Backdrop of the sliding menu. -->
            <div v-if="menuOpen" class="fixed inset-0 z-40 bg-gray-900/40 backdrop-blur-[1px] lg:hidden" @click="menuOpen = false" />

            <aside
                class="fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-gray-200 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:translate-x-0"
                :class="menuOpen ? 'translate-x-0 shadow-2xl' : 'translate-x-full'"
                aria-label="منوی پنل کاربری"
            >
                <!-- Brand -->
                <div class="flex items-center justify-between gap-2 px-4 pt-5 pb-4">
                    <Link :href="homeUrl" class="flex min-w-0 items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-violet-700 text-lg font-bold text-white shadow-sm">
                            {{ page.props.appName?.charAt(0) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate font-bold text-gray-900">{{ page.props.appName }}</span>
                            <span class="block text-xs text-gray-500">پنل کاربری</span>
                        </span>
                    </Link>
                    <button type="button" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 lg:hidden" aria-label="بستن منو" @click="menuOpen = false">
                        <PanelIcon name="close" class="size-5" />
                    </button>
                </div>

                <!-- Current tenant; a switch when there are others. -->
                <div v-if="tenant.current" class="px-3">
                    <component
                        :is="canSwitchTenant ? Link : 'div'"
                        :href="canSwitchTenant ? route('tenants.select') : undefined"
                        class="group flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5"
                        :class="canSwitchTenant ? 'transition hover:border-violet-300 hover:bg-violet-50/50' : ''"
                        :title="canSwitchTenant ? `تغییر ${tenant.label}` : undefined"
                    >
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                            <PanelIcon name="building" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] text-gray-500">{{ tenant.label }}</span>
                            <span class="block truncate text-sm font-medium text-gray-900">{{ tenant.current.name }}</span>
                        </span>
                        <PanelIcon v-if="canSwitchTenant" name="chevrons" class="size-4 shrink-0 text-gray-400 group-hover:text-violet-600" />
                    </component>
                </div>

                <nav class="mt-5 flex-1 overflow-y-auto px-3">
                    <p class="mb-2 px-3 text-[11px] font-medium text-gray-400">منو</p>
                    <ul class="space-y-0.5">
                        <li v-for="link in links" :key="link.url">
                            <Link
                                :href="link.url"
                                class="group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition"
                                :class="isActive(link.url) ? 'bg-violet-50 font-semibold text-violet-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                                :aria-current="isActive(link.url) ? 'page' : undefined"
                            >
                                <span v-if="isActive(link.url)" class="absolute inset-y-2 start-0 w-1 rounded-full bg-violet-600" aria-hidden="true" />
                                <PanelIcon
                                    :name="link.icon"
                                    class="size-5 shrink-0 transition"
                                    :class="isActive(link.url) ? 'text-violet-600' : 'text-gray-400 group-hover:text-gray-600'"
                                />
                                <span class="truncate">{{ link.title }}</span>
                            </Link>
                        </li>
                    </ul>
                    <p v-if="!links.length" class="px-3 py-2 text-sm text-gray-500">موردی برای نمایش نیست.</p>

                    <template v-if="page.props.auth.canAccessAdmin">
                        <p class="mt-6 mb-2 px-3 text-[11px] font-medium text-gray-400">مدیریت</p>
                        <a href="/cpadmin" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-600 transition hover:bg-gray-50 hover:text-gray-900">
                            <PanelIcon name="squares" class="size-5 shrink-0 text-gray-400 group-hover:text-gray-600" />
                            پنل مدیریت
                        </a>
                    </template>
                </nav>

                <!-- The account: profile and logout. -->
                <div class="border-t border-gray-100 p-3">
                    <div class="flex items-center gap-1 rounded-xl p-1.5" :class="isActive(route('profile.edit')) ? 'bg-violet-50' : 'hover:bg-gray-50'">
                        <Link :href="route('profile.edit')" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1" title="پروفایل">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700">
                                {{ user?.name?.trim()?.charAt(0) }}
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-900">{{ user?.name }}</span>
                                <span class="block truncate text-xs text-gray-500" dir="ltr">{{ user?.mobile || user?.email }}</span>
                            </span>
                        </Link>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg p-2 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                            title="خروج"
                            aria-label="خروج"
                            @click="logout"
                        >
                            <PanelIcon name="logout" class="size-5" />
                        </button>
                    </div>
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
