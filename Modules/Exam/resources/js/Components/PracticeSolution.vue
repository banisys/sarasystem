<script setup>
import PanelButton from '@/Components/Ui/PanelButton.vue';
import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    url: { type: String, required: true },
    type: { type: String, required: true },
});

const request = useHttp();
const solution = ref();
const visible = ref(false);
const failed = ref(false);

async function toggle() {
    if (visible.value) {
        visible.value = false;
        return;
    }

    failed.value = false;
    try {
        if (solution.value === undefined) {
            const response = await request.get(props.url);
            solution.value = response.solution;
        }
        visible.value = true;
    } catch {
        failed.value = true;
    }
}
</script>

<template>
    <div class="mt-3 grid justify-items-start gap-2">
        <PanelButton variant="link" :loading="request.processing" :aria-expanded="visible" @click="toggle">
            {{ visible ? 'پنهان کردن پاسخ' : type === 'descriptive' ? 'نمایش پاسخ نمونه' : 'نمایش پاسخ درست' }}
        </PanelButton>
        <p v-if="visible" role="status" class="whitespace-pre-wrap wrap-break-word rounded-ui bg-ui-canvas px-3 py-2 text-sm text-ui-ink">
            {{ solution || 'پاسخی برای نمایش ثبت نشده است.' }}
        </p>
        <p v-if="failed" role="alert" class="text-sm text-rose-700 panel-dark:text-rose-300">دریافت پاسخ ممکن نشد. دوباره تلاش کنید.</p>
    </div>
</template>
