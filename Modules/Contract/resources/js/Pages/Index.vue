<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    contracts: { type: Array, required: true },
});

const statusClass = {
    issued: 'bg-amber-50 text-amber-700',
    signed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
};
</script>

<template>
    <Head title="قراردادها" />

    <AppLayout>
        <div class="space-y-4">
            <UiPageHeader title="قراردادها" icon="document" />

            <UiNotice v-if="!contracts.length">قراردادی برای شما نیست.</UiNotice>

            <Link
                v-for="contract in contracts"
                :key="contract.id"
                :href="route('contracts.show', contract.id)"
                class="ui-card-link"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="min-w-0 wrap-break-word font-semibold text-ui-ink">{{ contract.title }}</h2>
                    <span class="ui-badge" :class="statusClass[contract.status]">
                        {{ contract.status_label }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ui-muted">
                    <span v-if="contract.number">شماره: {{ contract.number }}</span>
                    <span v-if="contract.kind_line" class="text-violet-700">{{ contract.kind_line }}</span>
                    <span v-if="contract.starts_on">از {{ contract.starts_on }}</span>
                    <span v-if="contract.ends_on">تا {{ contract.ends_on }}</span>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
