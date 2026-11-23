<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { ElAside, ElConfigProvider, ElContainer, ElDrawer, ElHeader, ElMain, ElNotification } from 'element-plus';
import fa from 'element-plus/es/locale/lang/fa';
import 'element-plus/es/components/container/style/css';
import 'element-plus/es/components/drawer/style/css';
import 'element-plus/es/components/notification/style/css';
import '../../css/profile.css';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import TutorialBar from '@/Components/TutorialBar.vue';
import PanelNavigation from './PanelNavigation.vue';
import { useProfileTheme } from './useProfileTheme.js';

defineProps({ fullWidth: Boolean });

const page = usePage();
const impersonator = computed(() => page.props.auth.impersonator);
const homeUrl = computed(() => page.props.panelHome ?? route('dashboard'));
const menuOpen = ref(false);
const overlays = useTemplateRef('profile-overlays');
const { theme, toggleTheme } = useProfileTheme();

watch([overlays, () => page.flash], ([container, flash]) => {
    if (!container || !flash?.status) return;

    ElNotification.success({
        title: flash.status,
        appendTo: container,
        position: getComputedStyle(container).direction === 'rtl' ? 'top-left' : 'top-right',
        duration: 6000,
        customClass: 'profile-status-notification',
    });
}, { flush: 'post' });

let desktop;
function closeDesktopMenu(event) {
    if (event.matches) menuOpen.value = false;
}

onMounted(() => {
    desktop = window.matchMedia('(min-width: 64rem)');
    desktop.addEventListener('change', closeDesktopMenu);
});
onBeforeUnmount(() => {
    desktop?.removeEventListener('change', closeDesktopMenu);
    ElNotification.closeAll();
});

function leaveImpersonation() {
    router.post(route('impersonation.leave'));
}
</script>

<template>
    <ElConfigProvider :locale="fa" size="large">
        <div class="profile-panel min-h-screen bg-ui-canvas text-ui-ink" :data-profile-theme="theme">
            <div id="profile-overlays" ref="profile-overlays" class="relative" />
            <TutorialBar :action="PanelButton" />
            <div
                v-if="impersonator"
                class="flex flex-wrap items-center justify-center gap-2 bg-amber-100 panel-dark:bg-amber-950/45 px-4 py-2 text-center text-sm text-amber-950 panel-dark:text-amber-200"
            >
                <span>شما ({{ impersonator.name }}) با حساب <strong>{{ page.props.auth.user?.name }}</strong> وارد شده‌اید.</span>
                <PanelButton variant="ghost" @click="leaveImpersonation">
                    بازگشت به حساب خودم
                </PanelButton>
            </div>

            <ElDrawer
                v-model="menuOpen" title="منوی پنل کاربری" direction="rtl"
                size="min(20rem, calc(100% - 1.5rem))" :with-header="false" destroy-on-close
            >
                <PanelNavigation @navigate="menuOpen = false">
                    <template #close>
                        <PanelButton variant="ghost" aria-label="بستن منو" @click="menuOpen = false">
                            <PanelIcon name="close" class="size-5" />
                        </PanelButton>
                    </template>
                </PanelNavigation>
            </ElDrawer>

            <ElContainer direction="vertical">
                <ElHeader height="auto" class="sticky top-0 z-30 flex min-h-16 items-center justify-between border-b border-ui-line bg-ui-surface px-4! lg:hidden">
                    <PanelButton variant="outline" aria-haspopup="dialog" :aria-expanded="menuOpen" aria-label="باز کردن منوی پنل" @click="menuOpen = true">
                        <PanelIcon name="menu" class="size-5" />
                    </PanelButton>
                    <PanelButton :href="homeUrl" variant="ghost" class="min-w-0 truncate px-3 font-bold">
                        <span class="truncate">{{ page.props.appName }}</span>
                    </PanelButton>
                    <PanelButton variant="outline" aria-label="تم تیره" title="تم تیره" :aria-pressed="theme === 'dark'" @click="toggleTheme">
                        <PanelIcon :name="theme === 'dark' ? 'sun' : 'moon'" class="size-5" />
                    </PanelButton>
                </ElHeader>

                <ElContainer class="mx-auto w-full items-start px-4 py-6 sm:px-6 lg:gap-6 lg:px-8 lg:py-8"
                    :class="{ 'max-w-7xl': !fullWidth }">
                    <ElAside class="hidden overflow-visible! [--el-aside-width:16rem] lg:sticky lg:top-8 lg:block xl:[--el-aside-width:17.5rem]">
                        <PanelCard class="h-[min(48rem,calc(100dvh-4rem))] [--panel-card-overflow:hidden]" body-class="h-full">
                            <PanelNavigation>
                                <template #appearance>
                                    <PanelButton variant="ghost" class="panel-row-button" :aria-pressed="theme === 'dark'" @click="toggleTheme">
                                        <PanelIcon :name="theme === 'dark' ? 'sun' : 'moon'" class="size-5" />
                                        تم تیره
                                    </PanelButton>
                                </template>
                            </PanelNavigation>
                        </PanelCard>
                    </ElAside>
                    <ElMain class="min-w-0 overflow-visible! p-0!">
                        <!-- Element Plus menus need their teleport target before the page mounts. -->
                        <slot v-if="overlays" />
                    </ElMain>
                </ElContainer>
            </ElContainer>
        </div>
    </ElConfigProvider>
</template>
