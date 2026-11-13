<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
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
                        <button
                            type="button"
                            class="ui-card-link flex w-full items-center gap-3 text-start disabled:opacity-60"
                            :disabled="!!switching"
                            :aria-current="tenant.current?.id === item.id ? 'true' : undefined"
                            @click="choose(item.slug)"
                        >
                            <PanelIcon name="building" class="size-5 shrink-0 text-brand-700" />
                            <span class="min-w-0 flex-1 wrap-break-word font-semibold">{{ item.name }}</span>
                            <span v-if="switching === item.slug" class="text-xs text-ui-muted" role="status">در حال ورود…</span>
                            <span v-else-if="tenant.current?.id === item.id" class="ui-badge bg-brand-50 text-brand-700">فعلی</span>
                        </button>
                    </li>
                </ul>
            </template>

            <UiNotice v-else>
                حساب شما هنوز به هیچ {{ tenant.label }}ی اضافه نشده است. با مدیر {{ tenant.label }} تماس بگیرید.
            </UiNotice>
        </div>
    </AppLayout>
</template>
