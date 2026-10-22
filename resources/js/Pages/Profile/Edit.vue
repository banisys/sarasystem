<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    currentPasswordRequired: { type: Boolean, required: true },
    codeLength: { type: Number, required: true },
    resendAfterSeconds: { type: Number, required: true },
});

const status = computed(() => usePage().props.flash.status);

// Email
const emailForm = useForm({ email: props.profile.email ?? '' });

function saveEmail() {
    emailForm.put(route('profile.email'), { preserveScroll: true });
}

// Mobile: send a code to the new number, then confirm it.
const mobile = ref('');
const sendCodeForm = useForm({});
const mobileForm = useForm({ code: '' });
const codeSent = ref(false);
const secondsLeft = ref(0);
let timer = null;

function sendMobileCode() {
    sendCodeForm
        .transform(() => ({ mobile: mobile.value }))
        .post(route('profile.mobile.code'), {
            preserveScroll: true,
            onSuccess: () => {
                codeSent.value = true;
                clearInterval(timer);
                secondsLeft.value = props.resendAfterSeconds;
                timer = setInterval(() => {
                    secondsLeft.value -= 1;
                    if (secondsLeft.value <= 0) clearInterval(timer);
                }, 1000);
            },
        });
}

function confirmMobile() {
    mobileForm
        .transform((data) => ({ ...data, mobile: mobile.value }))
        .put(route('profile.mobile'), {
            preserveScroll: true,
            onSuccess: () => {
                codeSent.value = false;
                mobile.value = '';
                mobileForm.reset();
            },
        });
}

onBeforeUnmount(() => clearInterval(timer));

// Password
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

function savePassword() {
    passwordForm.put(route('profile.password'), {
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
}

const inputClass =
    'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-violet-500 focus:ring-violet-500 disabled:bg-gray-100';
</script>

<template>
    <Head title="حساب کاربری" />

    <AppLayout>
        <div class="mx-auto max-w-2xl space-y-6">
            <p v-if="status" class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ status }}</p>

            <!-- Account -->
            <section class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="font-bold text-gray-900">اطلاعات حساب</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">نام</dt>
                        <dd class="mt-1 text-gray-900">{{ profile.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">کد ملی</dt>
                        <dd class="mt-1 text-gray-900" dir="ltr">{{ profile.nationalCode ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">برای تغییر نام یا کد ملی با مدیر تماس بگیرید.</p>
            </section>

            <!-- Mobile -->
            <section class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="font-bold text-gray-900">شماره موبایل</h2>
                <p class="mt-1 text-sm text-gray-600">
                    شماره‌ی فعلی:
                    <span dir="ltr">{{ profile.mobile ?? '—' }}</span>
                    <span v-if="profile.mobile" :class="profile.mobileVerified ? 'text-green-600' : 'text-amber-600'">
                        ({{ profile.mobileVerified ? 'تأییدشده' : 'تأییدنشده' }})
                    </span>
                </p>

                <div class="mt-4">
                    <label for="mobile" class="block text-sm font-medium text-gray-700">شماره‌ی جدید</label>
                    <input id="mobile" v-model="mobile" type="tel" dir="ltr" :disabled="codeSent" :class="inputClass" />
                    <p v-if="sendCodeForm.errors.mobile || mobileForm.errors.mobile" class="mt-1 text-sm text-red-600">
                        {{ sendCodeForm.errors.mobile || mobileForm.errors.mobile }}
                    </p>
                </div>

                <button
                    v-if="!codeSent"
                    type="button"
                    :disabled="!mobile || sendCodeForm.processing"
                    class="mt-4 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                    @click="sendMobileCode"
                >
                    ارسال کد تأیید
                </button>

                <form v-else class="mt-4 space-y-3" @submit.prevent="confirmMobile">
                    <div>
                        <label for="mobile-code" class="block text-sm font-medium text-gray-700">کد {{ codeLength }} رقمی پیامک‌شده</label>
                        <input
                            id="mobile-code"
                            v-model="mobileForm.code"
                            type="text"
                            dir="ltr"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :maxlength="codeLength"
                            :class="inputClass"
                        />
                        <p v-if="mobileForm.errors.code" class="mt-1 text-sm text-red-600">{{ mobileForm.errors.code }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button
                            type="submit"
                            :disabled="mobileForm.processing"
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            تأیید و ذخیره
                        </button>
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="codeSent = false">
                            تغییر شماره
                        </button>
                        <button
                            type="button"
                            :disabled="secondsLeft > 0 || sendCodeForm.processing"
                            class="text-sm text-violet-600 hover:text-violet-800 disabled:text-gray-400"
                            @click="sendMobileCode"
                        >
                            {{ secondsLeft > 0 ? `ارسال دوباره تا ${secondsLeft} ثانیه` : 'ارسال دوباره' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Email -->
            <form class="rounded-2xl bg-white p-6 shadow-sm" @submit.prevent="saveEmail">
                <h2 class="font-bold text-gray-900">ایمیل</h2>
                <div class="mt-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">ایمیل (اختیاری)</label>
                    <input id="email" v-model="emailForm.email" type="email" dir="ltr" autocomplete="email" :class="inputClass" />
                    <p v-if="emailForm.errors.email" class="mt-1 text-sm text-red-600">{{ emailForm.errors.email }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="emailForm.processing || !emailForm.isDirty"
                    class="mt-4 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                >
                    ذخیره
                </button>
            </form>

            <!-- Password -->
            <form class="rounded-2xl bg-white p-6 shadow-sm" @submit.prevent="savePassword">
                <h2 class="font-bold text-gray-900">رمز عبور</h2>
                <p v-if="!currentPasswordRequired" class="mt-1 text-sm text-gray-600">
                    با کد پیامکی وارد شده‌اید؛ می‌توانید بدون رمز فعلی، رمز جدید تعیین کنید.
                </p>

                <div class="mt-4 space-y-4">
                    <div v-if="currentPasswordRequired">
                        <label for="current_password" class="block text-sm font-medium text-gray-700">رمز فعلی</label>
                        <input
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            dir="ltr"
                            autocomplete="current-password"
                            :class="inputClass"
                        />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">رمز جدید</label>
                        <input
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            dir="ltr"
                            autocomplete="new-password"
                            :class="inputClass"
                        />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">تکرار رمز جدید</label>
                        <input
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            dir="ltr"
                            autocomplete="new-password"
                            :class="inputClass"
                        />
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-500">با تغییر رمز، از همه‌ی دستگاه‌های دیگر خارج می‌شوید.</p>

                <button
                    type="submit"
                    :disabled="passwordForm.processing"
                    class="mt-4 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                >
                    تغییر رمز
                </button>
            </form>
        </div>
    </AppLayout>
</template>
