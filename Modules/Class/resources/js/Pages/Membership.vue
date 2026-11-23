<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    classroom: { type: Object, required: true },
});
</script>

<template>
    <Head :title="classroom.title" />

    <AppLayout>
        <div class="grid min-w-0 gap-3 sm:gap-4">
            <Link :href="route('my-classes.memberships')" class="w-fit text-sm font-semibold text-brand-700 hover:underline">
                بازگشت به کلاس‌های من
            </Link>

            <UiPageHeader :title="classroom.title" icon="users" />

            <PanelCard
                v-if="classroom.description || classroom.term || classroom.location"
                body-class="grid gap-3 [--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]"
            >
                <div v-if="classroom.term || classroom.location" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-ui-muted">
                    <span v-if="classroom.term">{{ classroom.term }}</span>
                    <span v-if="classroom.location">{{ classroom.location }}</span>
                </div>
                <p v-if="classroom.description" class="wrap-break-word text-sm whitespace-pre-line text-ui-ink">{{ classroom.description }}</p>
            </PanelCard>

            <PanelCard body-class="grid gap-3 [--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]">
                <h2 class="font-bold text-ui-ink">اعضای کلاس</h2>
                <ul class="divide-y divide-ui-line border-t border-ui-line text-sm">
                    <li v-for="member in classroom.members" :key="member.id" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3 last:pb-0">
                        <span class="min-w-0 wrap-break-word text-ui-ink">{{ member.name }}</span>
                        <span class="min-w-0 wrap-break-word text-ui-muted">{{ member.roles }}</span>
                    </li>
                </ul>
            </PanelCard>
        </div>
    </AppLayout>
</template>
