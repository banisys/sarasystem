<script setup>
import { ElTag } from 'element-plus';
import 'element-plus/es/components/tag/style/css';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    contracts: { type: Array, required: true },
});

const statusTypes = { issued: 'warning', signed: 'success', cancelled: 'danger' };
</script>

<template>
    <Head title="قراردادها" />

    <AppLayout>
        <div class="space-y-4">
            <UiPageHeader title="قراردادها" icon="document" />

            <PanelNotice v-if="!contracts.length">قراردادی برای شما نیست.</PanelNotice>

            <Link
                v-for="contract in contracts"
                :key="contract.id"
                :href="route('contracts.show', contract.id)"
                class="ui-card-link"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="min-w-0 wrap-break-word font-semibold text-ui-ink">{{ contract.title }}</h2>
                    <ElTag :type="statusTypes[contract.status]" round>
                        {{ contract.status_label }}
                    </ElTag>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ui-muted">
                    <span v-if="contract.number">شماره: {{ contract.number }}</span>
                    <span v-if="contract.kind_line" class="text-violet-700 panel-dark:text-brand-700">{{ contract.kind_line }}</span>
                    <span v-if="contract.starts_on">از {{ contract.starts_on }}</span>
                    <span v-if="contract.ends_on">تا {{ contract.ends_on }}</span>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
