<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const tenant = computed(() => usePage().props.tenant);

function choose(slug) {
    router.post(route('tenants.switch', slug));
}
</script>

<template>
    <Head :title="`انتخاب ${tenant.label}`" />

    <AppLayout>
        <div class="mx-auto max-w-md rounded-2xl bg-white p-6 shadow-sm">
            <h1 class="text-lg font-bold text-gray-900">انتخاب {{ tenant.label }}</h1>

            <template v-if="tenant.available.length">
                <p class="mt-1 text-sm text-gray-600">در کدام {{ tenant.label }} می‌خواهید کار کنید؟</p>

                <ul class="mt-4 space-y-2">
                    <li v-for="item in tenant.available" :key="item.id">
                        <button
                            type="button"
                            class="w-full rounded-lg border border-gray-200 px-4 py-3 text-right hover:border-violet-400 hover:bg-violet-50"
                            :class="{ 'border-violet-500 bg-violet-50': tenant.current?.id === item.id }"
                            @click="choose(item.slug)"
                        >
                            {{ item.name }}
                        </button>
                    </li>
                </ul>
            </template>

            <p v-else class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                حساب شما هنوز به هیچ {{ tenant.label }}ی اضافه نشده است. با مدیر {{ tenant.label }} تماس بگیرید.
            </p>
        </div>
    </AppLayout>
</template>
