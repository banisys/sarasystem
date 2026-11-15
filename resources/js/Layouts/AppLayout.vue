<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { ElConfigProvider, ElDrawer } from 'element-plus';
import fa from 'element-plus/es/locale/lang/fa';
import 'element-plus/es/components/drawer/style/css';
import '../../css/profile.css';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import TutorialBar from '@/Components/TutorialBar.vue';
import PanelNavigation from './PanelNavigation.vue';
import { useProfileTheme } from './useProfileTheme.js';

const page = usePage();
const impersonator = computed(() => page.props.auth.impersonator);
const homeUrl = computed(() => page.props.panelHome ?? route('dashboard'));
const menuOpen = ref(false);
const { theme, toggleTheme } = useProfileTheme();

let desktop;
function closeDesktopMenu(event) {
    if (event.matches) menuOpen.value = false;
}

onMounted(() => {
    desktop = window.matchMedia('(min-width: 64rem)');
    desktop.addEventListener('change', closeDesktopMenu);
});
onBeforeUnmount(() => desktop?.removeEventListener('change', closeDesktopMenu));

function leaveImpersonation() {
    router.post(route('impersonation.leave'));
}
</script>

<template>
    <ElConfigProvider :locale="fa" size="large">
        <div class="profile-panel min-h-screen bg-ui-canvas text-ui-ink" :data-profile-theme="theme">
            <div id="profile-overlays" class="relative" />
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

            <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between border-b border-ui-line bg-ui-surface px-4 lg:hidden">
                <PanelButton variant="outline" aria-haspopup="dialog" :aria-expanded="menuOpen" aria-label="باز کردن منوی پنل" @click="menuOpen = true">
                    <PanelIcon name="menu" class="size-5" />
                </PanelButton>
                <PanelButton :href="homeUrl" variant="ghost" class="min-w-0 truncate px-3 font-bold">
                    <span class="truncate">{{ page.props.appName }}</span>
                </PanelButton>
                <PanelButton variant="outline" aria-label="تم تیره" title="تم تیره" :aria-pressed="theme === 'dark'" @click="toggleTheme">
                    <PanelIcon :name="theme === 'dark' ? 'sun' : 'moon'" class="size-5" />
                </PanelButton>
            </header>
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

            <div class="mx-auto max-w-[90rem] px-4 py-6 sm:px-6 lg:flex lg:items-start lg:gap-6 lg:px-8 lg:py-8">
                <PanelCard role="complementary" class="hidden h-[min(48rem,calc(100dvh-4rem))] w-64 shrink-0 [--panel-card-overflow:hidden] lg:sticky lg:top-8 lg:block xl:w-70" body-class="h-full">
                    <PanelNavigation>
                        <template #appearance>
                            <PanelButton variant="ghost" class="panel-row-button" :aria-pressed="theme === 'dark'" @click="toggleTheme">
                                <PanelIcon :name="theme === 'dark' ? 'sun' : 'moon'" class="size-5" />
                                تم تیره
                            </PanelButton>
                        </template>
                    </PanelNavigation>
                </PanelCard>
                <main class="min-w-0 flex-1">
                    <div class="mx-auto max-w-6xl">
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </ElConfigProvider>
</template>
