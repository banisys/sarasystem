<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    contract: { type: Object, required: true },
    mobile: { type: String, default: null },
    codeLength: { type: Number, required: true },
    resendAfterSeconds: { type: Number, required: true },
});

const status = computed(() => usePage().props.flash.status);

// Signing: accept the text, get a code by SMS, then send it back.
const sendCodeForm = useForm({});
const signForm = useForm({ code: '', accept: false });
const codeSent = ref(false);
const secondsLeft = ref(0);
const codeInput = ref(null);
let timer = null;

function sendCode() {
    sendCodeForm.post(route('contracts.code', props.contract.id), {
        preserveScroll: true,
        onSuccess: async () => {
            signForm.reset('code');
            signForm.clearErrors('code');
            codeSent.value = true;
            clearInterval(timer);
            secondsLeft.value = props.resendAfterSeconds;
            timer = setInterval(() => {
                secondsLeft.value -= 1;
                if (secondsLeft.value <= 0) clearInterval(timer);
            }, 1000);
            await nextTick();
            codeInput.value?.focus();
        },
    });
}

function sign() {
    signForm.post(route('contracts.sign', props.contract.id), {
        preserveScroll: true,
        onSuccess: () => {
            codeSent.value = false;
            signForm.reset();
        },
    });
}

onBeforeUnmount(() => clearInterval(timer));

</script>

<template>
    <Head :title="contract.title" />

    <AppLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <UiPageHeader :title="contract.title" :description="contract.status_label" icon="document" :back-href="route('contracts.index')" back-label="قراردادها">
                <template #actions>
                    <a :href="route('contracts.pdf', contract.id)" class="ui-button border-ui-line bg-white hover:bg-ui-canvas">دریافت PDF</a>
                </template>
            </UiPageHeader>

            <UiNotice v-if="status" tone="success">{{ status }}</UiNotice>
            <p v-if="contract.status === 'cancelled'" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
                این قرارداد لغو شده است<span v-if="contract.cancellation_reason">: {{ contract.cancellation_reason }}</span>
            </p>
            <p v-if="contract.signs_on_paper" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">
                این قرارداد به صورت کاغذی امضا می‌شود؛ برای امضا به دفتر مراجعه کنید.
            </p>

            <!-- The text is rendered on the server from the issued contract. -->
            <article class="contract ui-surface overflow-x-auto p-4 leading-8 text-gray-800 sm:p-6" v-html="contract.html" />

            <section v-if="contract.can_sign" class="ui-surface p-4 sm:p-6">
                <h2 class="font-bold text-gray-900">امضای قرارداد</h2>
                <p class="mt-1 text-sm text-gray-600">
                    برای امضا، متن را بخوانید و بپذیرید؛ کد امضا به موبایل
                    <span dir="ltr">{{ mobile }}</span>
                    پیامک می‌شود.
                </p>

                <label class="mt-4 flex items-start gap-2 text-sm text-gray-800">
                    <input v-model="signForm.accept" type="checkbox" class="mt-1 rounded border-gray-300 text-violet-600" />
                    <span>متن قرارداد را خواندم و می‌پذیرم.</span>
                </label>
                <p v-if="signForm.errors.accept" class="mt-1 text-sm text-red-600">{{ signForm.errors.accept }}</p>

                <UiButton
                    v-if="!codeSent"
                    type="button"
                    :disabled="!signForm.accept || sendCodeForm.processing"
                    class="mt-4"
                    @click="sendCode"
                >
                    ارسال کد امضا
                </UiButton>
                <p v-if="sendCodeForm.errors.code" class="mt-1 text-sm text-red-600">{{ sendCodeForm.errors.code }}</p>

                <form v-if="codeSent" class="mt-4 space-y-3" @submit.prevent="sign">
                    <div>
                        <label for="sign-code" class="block text-sm font-medium text-gray-700">کد {{ codeLength }} رقمی پیامک‌شده</label>
                        <input
                            id="sign-code"
                            ref="codeInput"
                            v-model="signForm.code"
                            type="text"
                            dir="ltr"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :maxlength="codeLength"
                            class="ui-field max-w-xs"
                            :aria-invalid="!!signForm.errors.code"
                            :aria-describedby="signForm.errors.code ? 'sign-code-error' : undefined"
                        />
                        <p v-if="signForm.errors.code" id="sign-code-error" class="ui-field-error">{{ signForm.errors.code }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <UiButton
                            type="submit"
                            :disabled="!signForm.accept || !signForm.code || signForm.processing"
                        >
                            {{ signForm.processing ? 'در حال ثبت امضا…' : 'امضا می‌کنم' }}
                        </UiButton>
                        <button
                            type="button"
                            :disabled="secondsLeft > 0 || sendCodeForm.processing"
                            class="text-sm text-violet-600 hover:text-violet-800 disabled:text-gray-400"
                            @click="sendCode"
                        >
                            {{ secondsLeft > 0 ? `ارسال دوباره تا ${secondsLeft} ثانیه` : 'ارسال دوباره' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.contract :deep(h1) {
    margin-bottom: 0.5rem;
    text-align: center;
    font-size: 1.25rem;
    font-weight: 700;
}
.contract :deep(h2),
.contract :deep(h3) {
    margin-top: 1rem;
    font-weight: 700;
}
.contract :deep(p) {
    margin-top: 0.5rem;
}
.contract :deep(table) {
    width: 100%;
    border-collapse: collapse;
}
.contract :deep(td),
.contract :deep(th) {
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.5rem;
}
.contract :deep(ul) {
    list-style: disc;
    padding-inline-start: 1.5rem;
}
.contract :deep(ol) {
    list-style: decimal;
    padding-inline-start: 1.5rem;
}
.contract :deep(.contract-number) {
    text-align: left;
    font-size: 0.8rem;
    color: #6b7280;
}
.contract :deep(.contract-signature) {
    margin-top: 2rem;
    border-top: 1px solid #e5e7eb;
    padding-top: 0.75rem;
    font-size: 0.85rem;
    color: #4b5563;
}
</style>
