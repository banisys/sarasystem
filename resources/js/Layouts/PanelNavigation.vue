<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';

// The user panel's frame (ADR 0069): a side menu at the start (the right in
// Persian) with the modules' links (UserPanel::navigationUsing()), the
// tenant and the account; on small screens it slides in from a top bar.
const page = usePage();
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);

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

defineEmits(['navigate']);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <!-- Brand -->
        <div class="flex items-center justify-between gap-2 px-4 pt-5 pb-4">
            <Link :href="homeUrl" @click="$emit('navigate')" class="flex min-w-0 items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-ui bg-brand-600 text-lg font-bold text-brand-contrast">
                    {{ page.props.appName?.charAt(0) }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate font-bold text-ui-ink">{{ page.props.appName }}</span>
                    <span class="block text-xs text-gray-500">پنل کاربری</span>
                </span>
            </Link>
            <slot name="close" />
        </div>

        <!-- Current tenant; a switch when there are others. -->
        <div v-if="tenant.current" class="px-3">
            <component
                :is="canSwitchTenant ? Link : 'div'"
                :href="canSwitchTenant ? route('tenants.select') : undefined"
                @click="canSwitchTenant && $emit('navigate')"
                class="group flex items-center gap-3 rounded-ui border border-ui-line px-3 py-2.5"
                :class="canSwitchTenant ? 'transition hover:border-brand-300 hover:bg-brand-50/50' : ''"
                :title="canSwitchTenant ? `تغییر ${tenant.label}` : undefined"
            >
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <PanelIcon name="building" class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[11px] text-gray-500">{{ tenant.label }}</span>
                    <span class="block truncate text-sm font-medium text-ui-ink">{{ tenant.current.name }}</span>
                </span>
                <PanelIcon v-if="canSwitchTenant" name="chevrons" class="size-4 shrink-0 text-gray-400 group-hover:text-brand-600" />
            </component>
        </div>

        <nav aria-label="منوی پنل کاربری" class="mt-5 min-h-0 flex-1 overflow-y-auto px-3">
            <ul class="space-y-0.5">
                <li v-for="link in links" :key="link.url">
                    <Link
                        :href="link.url"
                        @click="$emit('navigate')"
                        class="group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition"
                        :class="isActive(link.url) ? 'bg-brand-50 font-bold text-brand-900' : 'text-ui-muted hover:bg-ui-canvas hover:text-ui-ink'"
                        :aria-current="isActive(link.url) ? 'page' : undefined"
                    >
                        <span v-if="isActive(link.url)" class="absolute inset-y-2 start-0 w-1 rounded-full bg-brand-600" aria-hidden="true" />
                        <PanelIcon
                            :name="link.icon"
                            class="size-5 shrink-0 transition"
                            :class="isActive(link.url) ? 'text-brand-600' : 'text-gray-400 group-hover:text-ui-muted'"
                        />
                        <span class="truncate">{{ link.title }}</span>
                    </Link>
                </li>
            </ul>
            <p v-if="!links.length" class="px-3 py-2 text-sm text-gray-500">موردی برای نمایش نیست.</p>

            <template v-if="page.props.auth.canAccessAdmin">
                <a href="/cpadmin" class="group mt-4 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-ui-muted transition hover:bg-ui-canvas hover:text-ui-ink">
                    <PanelIcon name="squares" class="size-5 shrink-0 text-gray-400 group-hover:text-ui-muted" />
                    پنل مدیریت
                </a>
            </template>
        </nav>

        <!-- The account: profile and logout. -->
        <div class="border-t border-gray-100 p-3">
            <div class="flex items-center gap-1 rounded-ui p-1.5" :class="page.component === 'Profile/Edit' ? 'bg-brand-50' : 'hover:bg-ui-canvas'">
                <Link :href="route('profile.edit')" @click="$emit('navigate')" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1" title="پروفایل">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                        {{ user?.name?.trim()?.charAt(0) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-ui-ink">{{ user?.name }}</span>
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
    </div>
</template>
