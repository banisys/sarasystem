<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ElAvatar } from 'element-plus';
import 'element-plus/es/components/avatar/style/css';
import PanelButton from '@/Components/Ui/PanelButton.vue';
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

defineEmits(['navigate']);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="grid gap-2 border-b border-ui-line px-4 py-3.5">
            <div class="flex min-w-0 items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-3">
                    <ElAvatar :size="32" class="shrink-0 panel-account-avatar">
                        <PanelIcon name="user" class="size-5" />
                    </ElAvatar>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ user?.name }}</p>
                        <bdi v-if="user?.mobile || user?.email" dir="ltr" class="block truncate text-xs text-ui-muted">
                            {{ user.mobile || user.email }}
                        </bdi>
                    </div>
                </div>
                <slot name="close" />
            </div>
            <p v-if="page.props.navigationSubtitle" class="wrap-break-word text-xs font-medium text-ui-muted">
                {{ page.props.navigationSubtitle }}
            </p>
        </div>

        <nav aria-label="منوی پنل کاربری" class="min-h-0 flex-1 overflow-y-auto px-4 py-2">
            <ul class="space-y-1">
                <li v-if="tenant.current">
                    <PanelButton
                        v-if="canSwitchTenant"
                        :href="route('tenants.select')"
                        variant="navigation"
                        :aria-current="page.component === 'Tenants/Select' ? 'page' : undefined"
                        :title="`تغییر ${tenant.label}`"
                        @click="$emit('navigate')"
                    >
                        <PanelIcon name="building" class="size-5 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">{{ tenant.current.name }}</span>
                        <PanelIcon name="chevrons" class="size-4 shrink-0" />
                    </PanelButton>
                    <div v-else class="flex min-h-12 items-center gap-3 px-4 py-2 text-sm">
                        <PanelIcon name="building" class="size-5 shrink-0" />
                        <span class="min-w-0 truncate">{{ tenant.current.name }}</span>
                    </div>
                </li>
                <li v-for="link in links" :key="link.url">
                    <PanelButton :href="link.url" variant="navigation" :aria-current="isActive(link.url) ? 'page' : undefined" @click="$emit('navigate')">
                        <PanelIcon :name="link.icon" class="size-5 shrink-0" />
                        <span class="min-w-0 wrap-break-word">{{ link.title }}</span>
                    </PanelButton>
                </li>
                <li>
                    <PanelButton
                        :href="route('profile.edit')"
                        variant="navigation"
                        :aria-current="page.component === 'Profile/Edit' ? 'page' : undefined"
                        @click="$emit('navigate')"
                    >
                        <PanelIcon name="user" class="size-5 shrink-0" />
                        اطلاعات کاربری
                    </PanelButton>
                </li>
                <li v-if="page.props.auth.canAccessAdmin">
                    <PanelButton href="/cpadmin" full-page variant="navigation">
                        <PanelIcon name="squares" class="size-5 shrink-0" />
                        پنل مدیریت
                    </PanelButton>
                </li>
            </ul>
        </nav>

        <div class="shrink-0 space-y-1 border-t border-ui-line px-4 py-2">
            <slot name="appearance" />
            <PanelButton variant="danger-link" class="panel-row-button" @click="logout">
                <PanelIcon name="logout" class="size-5 shrink-0" />
                خروج از حساب کاربری
            </PanelButton>
        </div>
    </div>
</template>
