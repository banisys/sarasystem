<script setup>
import { ElTag } from 'element-plus';
import 'element-plus/es/components/tag/style/css';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import PanelNotice from '@/Components/Ui/PanelNotice.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const tenant = computed(() => usePage().props.tenant);

const switching = ref(null);

function choose(slug) {
    if (switching.value) return;

    switching.value = slug;
    router.post(route('tenants.switch', slug), {}, {
        onFinish: () => { switching.value = null; },
    });
}
</script>

<template>
    <Head :title="`انتخاب ${tenant.label}`" />

    <AppLayout>
        <div class="mx-auto max-w-2xl space-y-4">
            <UiPageHeader :title="`انتخاب ${tenant.label}`" icon="building" />

            <template v-if="tenant.available.length">
                <ul class="space-y-2" :aria-busy="!!switching">
                    <li v-for="item in tenant.available" :key="item.id">
                        <PanelButton
                            type="button"
                            variant="outline" class="panel-row-button"
                            :disabled="!!switching"
                            :aria-current="tenant.current?.id === item.id ? 'true' : undefined"
                            @click="choose(item.slug)"
                        >
                            <PanelIcon name="building" class="size-5 shrink-0 text-brand-700" />
                            <span class="min-w-0 flex-1 wrap-break-word font-semibold">{{ item.name }}</span>
                            <span v-if="switching === item.slug" class="text-xs text-ui-muted" role="status">در حال ورود…</span>
                            <ElTag v-else-if="tenant.current?.id === item.id" size="small" round>فعلی</ElTag>
                        </PanelButton>
                    </li>
                </ul>
            </template>

            <PanelNotice v-else>
                حساب شما هنوز به هیچ {{ tenant.label }}ی اضافه نشده است. با مدیر {{ tenant.label }} تماس بگیرید.
            </PanelNotice>
        </div>
    </AppLayout>
</template>
