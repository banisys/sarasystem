<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, DialogTrigger } from 'reka-ui';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import TutorialBar from '@/Components/TutorialBar.vue';
import PanelNavigation from './PanelNavigation.vue';

const page = usePage();
const impersonator = computed(() => page.props.auth.impersonator);
const homeUrl = computed(() => page.props.panelHome ?? route('dashboard'));
const menuOpen = ref(false);

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
    <div class="min-h-screen bg-ui-canvas text-ui-ink">
        <TutorialBar />
        <div
            v-if="impersonator"
            class="flex flex-wrap items-center justify-center gap-2 bg-amber-100 px-4 py-2 text-center text-sm text-amber-950"
        >
            <span>شما ({{ impersonator.name }}) با حساب <strong>{{ page.props.auth.user?.name }}</strong> وارد شده‌اید.</span>
            <button type="button" class="min-h-10 rounded-ui px-3 font-semibold underline underline-offset-4" @click="leaveImpersonation">
                بازگشت به حساب خودم
            </button>
        </div>

        <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between border-b border-ui-line bg-white px-4 lg:hidden">
            <DialogRoot v-model:open="menuOpen">
                <DialogTrigger class="ui-button border-ui-line bg-white px-3" aria-label="باز کردن منوی پنل">
                    <PanelIcon name="menu" class="size-5" />
                </DialogTrigger>
                <DialogPortal>
                    <DialogOverlay class="fixed inset-0 z-40 bg-ui-plum/45" />
                    <DialogContent class="fixed inset-y-0 start-0 z-50 w-80 max-w-[calc(100%-2rem)] bg-white shadow-xl outline-none" :aria-describedby="undefined">
                        <DialogTitle class="sr-only">منوی پنل کاربری</DialogTitle>
                        <PanelNavigation @navigate="menuOpen = false">
                            <template #close>
                                <DialogClose class="ui-button border-ui-line bg-white px-3" aria-label="بستن منو">
                                    <PanelIcon name="close" class="size-5" />
                                </DialogClose>
                            </template>
                        </PanelNavigation>
                    </DialogContent>
                </DialogPortal>
            </DialogRoot>
            <Link :href="homeUrl" class="flex min-w-0 items-center gap-2 px-3 font-bold">
                <span class="truncate">{{ page.props.appName }}</span>
            </Link>
        </header>

        <div class="lg:flex">
            <aside class="hidden h-dvh w-64 shrink-0 border-e border-ui-line bg-white lg:sticky lg:top-0 lg:block">
                <PanelNavigation />
            </aside>
            <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-6xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
