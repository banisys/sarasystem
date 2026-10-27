<script setup>
import { useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

// A mobile confirmed by an SMS code on a public form (App\Auth\GuestMobile):
// send a code, type it back; the confirmed number is kept in the session
// (verification.verifiedMobile) and locked here. The form's own submit
// sends the number, which the server checks against the confirmed one.
const props = defineProps({
    // {verifiedMobile, codeLength, resendAfterSeconds}
    verification: { type: Object, required: true },
    sendUrl: { type: String, required: true },
    verifyUrl: { type: String, required: true },
    label: { type: String, default: 'تلفن همراه' },
    hint: { type: String, default: 'برای ثبت فرم، شماره موبایل باید با کد پیامکی تأیید شود.' },
    // The form's own error for the mobile.
    error: { type: String, default: null },
    inputClass: { type: String, default: '' },
    labelClass: { type: String, default: '' },
    errorClass: { type: String, default: '' },
});

const emit = defineEmits(['verified']);

const mobile = defineModel({ type: String, default: '' });

const verifiedMobile = ref(props.verification.verifiedMobile);
const sendCodeForm = useForm({});
const verifyForm = useForm({ code: '' });
const codeSent = ref(false);
const secondsLeft = ref(0);
let timer = null;

if (verifiedMobile.value) {
    mobile.value = verifiedMobile.value;
}

watch(
    () => props.verification.verifiedMobile,
    (confirmed) => {
        verifiedMobile.value = confirmed;
        if (confirmed) {
            mobile.value = confirmed;
            emit('verified', confirmed);
        }
        codeSent.value = false;
        verifyForm.reset();
    },
);

function sendCode() {
    sendCodeForm
        .transform(() => ({ mobile: mobile.value }))
        .post(props.sendUrl, {
            preserveScroll: true,
            onSuccess: () => {
                codeSent.value = true;
                clearInterval(timer);
                secondsLeft.value = props.verification.resendAfterSeconds;
                timer = setInterval(() => {
                    secondsLeft.value -= 1;
                    if (secondsLeft.value <= 0) clearInterval(timer);
                }, 1000);
            },
        });
}

function verifyCode() {
    verifyForm.transform((data) => ({ ...data, mobile: mobile.value })).post(props.verifyUrl, { preserveScroll: true });
}

function changeMobile() {
    codeSent.value = false;
    verifyForm.reset();
    mobile.value = '';
    // The server checks the submitted number against the confirmed one, so another number needs a new code.
    verifiedMobile.value = null;
}

onBeforeUnmount(() => clearInterval(timer));

const linkButtonClass = 'text-sm font-medium text-violet-700 hover:text-violet-900';
</script>

<template>
    <div>
        <label for="mobile" :class="labelClass">{{ label }}</label>
        <div class="flex flex-wrap items-start gap-2">
            <input
                id="mobile"
                v-model="mobile"
                type="tel"
                dir="ltr"
                placeholder="09121234567"
                :disabled="!!verifiedMobile || codeSent"
                :class="[inputClass, 'max-w-xs disabled:bg-gray-100']"
            />
            <template v-if="verifiedMobile">
                <span class="mt-3 text-sm text-green-700">✓ تأیید شد</span>
                <button type="button" :class="[linkButtonClass, 'mt-3']" @click="changeMobile">تغییر شماره</button>
            </template>
            <button
                v-else-if="!codeSent"
                type="button"
                :disabled="sendCodeForm.processing || !mobile"
                class="mt-1 rounded-lg border border-violet-600 px-4 py-2 text-sm font-medium text-violet-700 hover:bg-violet-50 disabled:opacity-60"
                @click="sendCode"
            >
                ارسال کد تأیید
            </button>
        </div>
        <p v-if="!verifiedMobile && !codeSent" class="mt-1 text-xs text-gray-500">{{ hint }}</p>
        <p v-if="sendCodeForm.errors.mobile || verifyForm.errors.mobile || error" :class="errorClass">
            {{ sendCodeForm.errors.mobile || verifyForm.errors.mobile || error }}
        </p>

        <div v-if="codeSent && !verifiedMobile" class="mt-3 rounded-lg bg-violet-50 p-3">
            <label for="code" :class="labelClass">کد پیامک‌شده به {{ mobile }}</label>
            <div class="flex flex-wrap items-start gap-2">
                <input
                    id="code"
                    v-model="verifyForm.code"
                    type="text"
                    inputmode="numeric"
                    dir="ltr"
                    autocomplete="one-time-code"
                    :maxlength="verification.codeLength"
                    :class="[inputClass, 'max-w-40']"
                    @keydown.enter.prevent="verifyCode"
                />
                <button
                    type="button"
                    :disabled="verifyForm.processing || !verifyForm.code"
                    class="mt-1 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-60"
                    @click="verifyCode"
                >
                    تأیید
                </button>
            </div>
            <p v-if="verifyForm.errors.code" :class="errorClass">{{ verifyForm.errors.code }}</p>
            <div class="mt-2 flex gap-4 text-sm">
                <span v-if="secondsLeft > 0" class="text-gray-500">ارسال دوباره تا {{ secondsLeft }} ثانیه‌ی دیگر</span>
                <button v-else type="button" :class="linkButtonClass" :disabled="sendCodeForm.processing" @click="sendCode">ارسال دوباره‌ی کد</button>
                <button type="button" :class="linkButtonClass" @click="changeMobile">تغییر شماره</button>
            </div>
        </div>
    </div>
</template>
