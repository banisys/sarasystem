<script setup>
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
            <h1 class="text-xl font-bold text-gray-900">قراردادها</h1>

            <p v-if="!contracts.length" class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">قراردادی برای شما نیست.</p>

            <Link
                v-for="contract in contracts"
                :key="contract.id"
                :href="route('contracts.show', contract.id)"
                class="block rounded-2xl bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">{{ contract.title }}</h2>
                    <span class="rounded-full px-3 py-1 text-xs" :class="statusClass[contract.status]">
                        {{ contract.status_label }}
                    </span>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span v-if="contract.number">شماره: {{ contract.number }}</span>
                    <span v-if="contract.starts_on">از {{ contract.starts_on }}</span>
                    <span v-if="contract.ends_on">تا {{ contract.ends_on }}</span>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
