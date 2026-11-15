<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import AppLayout from '../Layouts/AppLayout.vue';

// Shown only when no module offers the panel's home (ADR 0069).
const page = usePage();
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant.current);
</script>

<template>
    <Head title="داشبورد" />

    <AppLayout>
        <div class="space-y-6">
            <UiPageHeader :title="`${user.name}، خوش آمدید`" :description="tenant.name" icon="home" />

            <div v-if="page.props.navigation?.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <PanelButton
                    v-for="link in page.props.navigation"
                    :key="link.url"
                    :href="link.url"
                    variant="outline" class="panel-row-button"
                >
                    <span class="rounded-xl bg-brand-50 p-2.5 text-brand-700">
                        <PanelIcon :name="link.icon" class="size-6" />
                    </span>
                    <span class="min-w-0 flex-1 wrap-break-word font-semibold text-ui-ink">{{ link.title }}</span>
                    <PanelIcon name="back" class="size-4 shrink-0 rotate-180 text-ui-muted ltr:rotate-0" />
                </PanelButton>
            </div>
            <PanelEmpty v-else :description="`در این ${page.props.tenant.label} هنوز بخشی برای حساب شما در دسترس نیست.`" />
            <PanelButton :href="route('profile.edit')" variant="outline">اطلاعات کاربری</PanelButton>
        </div>
    </AppLayout>
</template>
