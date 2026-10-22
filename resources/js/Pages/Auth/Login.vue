<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    codeLength: { type: Number, required: true },
    resendAfterSeconds: { type: Number, required: true },
});

const page = usePage();
const status = computed(() => page.props.flash.status);

// One identifier for both ways of logging in: mobile, national code or email.
const identifier = ref('');
const mode = ref('password');

const passwordForm = useForm({ password: '', remember: false });
const sendCodeForm = useForm({});
const codeForm = useForm({ code: '', remember: false });

const codeSent = ref(false);
const secondsLeft = ref(0);
let timer = null;

function startCountdown() {
    clearInterval(timer);
    secondsLeft.value = props.resendAfterSeconds;
    timer = setInterval(() => {
        secondsLeft.value -= 1;
        if (secondsLeft.value <= 0) {
            clearInterval(timer);
        }
    }, 1000);
}

onBeforeUnmount(() => clearInterval(timer));

function loginWithPassword() {
    passwordForm
        .transform((data) => ({ ...data, identifier: identifier.value }))
        .post(route('login'), { onFinish: () => passwordForm.reset('password') });
}

function sendCode() {
    sendCodeForm
        .transform(() => ({ identifier: identifier.value }))
        .post(route('login.code.send'), {
            preserveScroll: true,
            onSuccess: () => {
                codeSent.value = true;
                startCountdown();
            },
        });
}

function loginWithCode() {
    codeForm
        .transform((data) => ({ ...data, identifier: identifier.value }))
        .post(route('login.code'), { onFinish: () => codeForm.reset('code') });
}

function switchMode(next) {
    mode.value = next;
    codeSent.value = false;
    passwordForm.clearErrors();
    sendCodeForm.clearErrors();
    codeForm.clearErrors();
}

const identifierError = computed(
    () => passwordForm.errors.identifier || sendCodeForm.errors.identifier || codeForm.errors.identifier,
);
</script>

<template>
    <Head title="ورود" />

    <main class="flex min-h-screen items-center justify-center bg-gray-50 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-center text-xl font-bold text-gray-900">ورود به {{ $page.props.appName }}</h1>

            <div class="mt-6 grid grid-cols-2 rounded-lg bg-gray-100 p-1 text-sm">
                <button
                    type="button"
                    class="rounded-md py-2"
                    :class="mode === 'password' ? 'bg-white font-medium shadow-sm' : 'text-gray-600'"
                    @click="switchMode('password')"
                >
                    با رمز عبور
                </button>
                <button
                    type="button"
                    class="rounded-md py-2"
                    :class="mode === 'code' ? 'bg-white font-medium shadow-sm' : 'text-gray-600'"
                    @click="switchMode('code')"
                >
                    با کد پیامکی
                </button>
            </div>

            <div class="mt-6">
                <label for="identifier" class="block text-sm font-medium text-gray-700">موبایل، کد ملی یا ایمیل</label>
                <input
                    id="identifier"
                    v-model="identifier"
                    type="text"
                    dir="ltr"
                    autocomplete="username"
                    autofocus
                    :disabled="codeSent"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-violet-500 focus:ring-violet-500 disabled:bg-gray-100"
                />
                <p v-if="identifierError" class="mt-1 text-sm text-red-600">{{ identifierError }}</p>
            </div>

            <!-- Password -->
            <form v-if="mode === 'password'" class="mt-4 space-y-4" @submit.prevent="loginWithPassword">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">رمز عبور</label>
                    <input
                        id="password"
                        v-model="passwordForm.password"
                        type="password"
                        dir="ltr"
                        autocomplete="current-password"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-violet-500 focus:ring-violet-500"
                    />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="passwordForm.remember" type="checkbox" class="rounded border-gray-300" />
                    مرا به خاطر بسپار
                </label>

                <button
                    type="submit"
                    :disabled="passwordForm.processing"
                    class="w-full rounded-lg bg-violet-600 py-2 font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                >
                    ورود
                </button>
            </form>

            <!-- SMS code -->
            <div v-else class="mt-4 space-y-4">
                <p v-if="status && codeSent" class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ status }}</p>

                <button
                    v-if="!codeSent"
                    type="button"
                    :disabled="sendCodeForm.processing || !identifier"
                    class="w-full rounded-lg bg-violet-600 py-2 font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                    @click="sendCode"
                >
                    ارسال کد
                </button>

                <form v-else class="space-y-4" @submit.prevent="loginWithCode">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700">کد {{ codeLength }} رقمی</label>
                        <input
                            id="code"
                            v-model="codeForm.code"
                            type="text"
                            dir="ltr"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :maxlength="codeLength"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-center tracking-[0.5em] focus:border-violet-500 focus:ring-violet-500"
                        />
                        <p v-if="codeForm.errors.code" class="mt-1 text-sm text-red-600">{{ codeForm.errors.code }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="codeForm.remember" type="checkbox" class="rounded border-gray-300" />
                        مرا به خاطر بسپار
                    </label>

                    <button
                        type="submit"
                        :disabled="codeForm.processing"
                        class="w-full rounded-lg bg-violet-600 py-2 font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                    >
                        ورود
                    </button>

                    <div class="flex justify-between text-sm">
                        <button type="button" class="text-gray-600 hover:text-gray-900" @click="codeSent = false">
                            تغییر شناسه
                        </button>
                        <button
                            type="button"
                            :disabled="secondsLeft > 0 || sendCodeForm.processing"
                            class="text-violet-600 hover:text-violet-800 disabled:text-gray-400"
                            @click="sendCode"
                        >
                            {{ secondsLeft > 0 ? `ارسال دوباره تا ${secondsLeft} ثانیه` : 'ارسال دوباره' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</template>
