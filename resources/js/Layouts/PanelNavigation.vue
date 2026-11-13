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
const canSwitchTenant = computed(() => tenant.value.available.length > 1);

function isActive(url) {
    const target = new URL(url, window.location.origin);
    const current = new URL(page.url, window.location.origin);
    const samePath = current.pathname === target.pathname || current.pathname.startsWith(`${target.pathname.replace(/\/$/, '')}/`);

    // A guardian's «کلاس‌های …» share the path and differ by `for`.
    return samePath && current.searchParams.get('for') === target.searchParams.get('for');
}

const rowClass = 'flex min-h-12 w-full min-w-0 items-center gap-3 rounded-s-none rounded-e-lg border-s-2 border-transparent px-4 py-2 text-start text-sm text-ui-ink transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-blue-600 aria-[current=page]:border-s-blue-700 aria-[current=page]:bg-blue-50 aria-[current=page]:font-semibold aria-[current=page]:text-blue-800';

defineEmits(['navigate']);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="flex items-center justify-between gap-2 border-b border-ui-line px-4 py-3.5">
            <div class="flex min-w-0 items-center gap-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-ui-ink">
                    <PanelIcon name="user" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ user?.name }}</p>
                    <bdi v-if="user?.mobile || user?.email" dir="ltr" class="block truncate text-xs text-ui-muted">
                        {{ user.mobile || user.email }}
                    </bdi>
                </div>
            </div>
            <slot name="close" />
        </div>

        <nav aria-label="منوی پنل کاربری" class="min-h-0 flex-1 overflow-y-auto px-4 py-2">
            <ul class="space-y-1">
                <li v-if="tenant.current">
                    <component
                        :is="canSwitchTenant ? Link : 'div'"
                        :href="canSwitchTenant ? route('tenants.select') : undefined"
                        :class="rowClass"
                        :aria-current="page.component === 'Tenants/Select' && canSwitchTenant ? 'page' : undefined"
                        :title="canSwitchTenant ? `تغییر ${tenant.label}` : undefined"
                        @click="canSwitchTenant && $emit('navigate')"
                    >
                        <PanelIcon name="building" class="size-5 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">{{ tenant.current.name }}</span>
                        <PanelIcon v-if="canSwitchTenant" name="chevrons" class="size-4 shrink-0" />
                    </component>
                </li>
                <li v-for="link in links" :key="link.url">
                    <Link :href="link.url" :class="rowClass" :aria-current="isActive(link.url) ? 'page' : undefined" @click="$emit('navigate')">
                        <PanelIcon :name="link.icon" class="size-5 shrink-0" />
                        <span class="min-w-0 wrap-break-word">{{ link.title }}</span>
                    </Link>
                </li>
                <li>
                    <Link
                        :href="route('profile.edit')"
                        :class="rowClass"
                        :aria-current="page.component === 'Profile/Edit' ? 'page' : undefined"
                        @click="$emit('navigate')"
                    >
                        <PanelIcon name="user" class="size-5 shrink-0" />
                        اطلاعات کاربری
                    </Link>
                </li>
                <li v-if="page.props.auth.canAccessAdmin">
                    <a href="/cpadmin" :class="rowClass">
                        <PanelIcon name="squares" class="size-5 shrink-0" />
                        پنل مدیریت
                    </a>
                </li>
            </ul>
        </nav>

        <div class="shrink-0 px-4 py-3">
            <button type="button" :class="rowClass" class="rounded-lg hover:bg-rose-50 hover:text-rose-800" @click="logout">
                <PanelIcon name="logout" class="size-5 shrink-0" />
                خروج از حساب کاربری
            </button>
        </div>
    </div>
</template>
