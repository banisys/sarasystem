<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
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
const codeInput = ref(null);
const mobileInput = ref(null);
const mobileError = computed(() => sendCodeForm.errors.mobile || mobileForm.errors.mobile);
let timer = null;

function sendMobileCode() {
    sendCodeForm
        .transform(() => ({ mobile: mobile.value }))
        .post(route('profile.mobile.code'), {
            preserveScroll: true,
            onSuccess: async () => {
                mobileForm.resetAndClearErrors();
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

function confirmMobile() {
    mobileForm
        .transform((data) => ({ ...data, mobile: mobile.value }))
        .put(route('profile.mobile'), {
            preserveScroll: true,
            onSuccess: () => {
                codeSent.value = false;
                clearInterval(timer);
                mobile.value = '';
                mobileForm.reset();
            },
        });
}

async function changeMobile() {
    codeSent.value = false;
    mobileForm.resetAndClearErrors();
    sendCodeForm.clearErrors();
    clearInterval(timer);
    await nextTick();
    mobileInput.value?.focus();
}

onBeforeUnmount(() => clearInterval(timer));

// Password
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

function savePassword() {
    passwordForm.put(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

const inputClass = 'ui-field';
</script>

<template>
    <Head title="حساب کاربری" />

    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-5">
            <UiPageHeader title="اطلاعات کاربری" icon="user" />
            <UiNotice v-if="status" tone="success">{{ status }}</UiNotice>

            <!-- Account -->
            <section class="ui-surface p-4 sm:p-6">
                <h2 class="font-bold text-gray-900">اطلاعات حساب</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">نام</dt>
                        <dd class="mt-1 text-gray-900">{{ profile.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">کد ملی</dt>
                        <dd class="mt-1 text-start text-ui-ink"><bdi dir="ltr">{{ profile.nationalCode ?? '—' }}</bdi></dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">برای تغییر نام یا کد ملی با مدیر تماس بگیرید.</p>
            </section>

            <!-- Mobile -->
            <section class="ui-surface p-4 sm:p-6">
                <h2 class="font-bold text-gray-900">شماره موبایل</h2>
                <p class="mt-1 text-sm text-gray-600">
                    شماره‌ی فعلی:
                    <span dir="ltr">{{ profile.mobile ?? '—' }}</span>
                    <span v-if="profile.mobile" :class="profile.mobileVerified ? 'text-green-600' : 'text-amber-600'">
                        ({{ profile.mobileVerified ? 'تأییدشده' : 'تأییدنشده' }})
                    </span>
                </p>

                <div class="mt-4">
                    <label for="mobile" class="ui-label">شماره‌ی جدید</label>
                    <input
                        id="mobile"
                        ref="mobileInput"
                        v-model="mobile"
                        type="tel"
                        dir="ltr"
                        autocomplete="tel-national"
                        :disabled="codeSent || sendCodeForm.processing"
                        :aria-invalid="!!mobileError"
                        :aria-describedby="mobileError ? 'mobile-error' : undefined"
                        :class="inputClass"
                        @keydown.enter.prevent="mobile && !codeSent && !sendCodeForm.processing && sendMobileCode()"
                    />
                    <p v-if="mobileError" id="mobile-error" class="ui-field-error">
                        {{ mobileError }}
                    </p>
                </div>

                <UiButton
                    v-if="!codeSent"
                    type="button"
                    :disabled="!mobile || sendCodeForm.processing"
                    class="mt-4"
                    @click="sendMobileCode"
                >
                    ارسال کد تأیید
                </UiButton>

                <form v-else class="mt-4 space-y-3" @submit.prevent="confirmMobile">
                    <div>
                        <label for="mobile-code" class="ui-label">کد {{ codeLength }} رقمی پیامک‌شده</label>
                        <input
                            id="mobile-code"
                            :aria-invalid="!!mobileForm.errors.code"
                            :aria-describedby="mobileForm.errors.code ? 'mobile-code-error' : undefined"
                            ref="codeInput"
                            v-model="mobileForm.code"
                            type="text"
                            dir="ltr"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :maxlength="codeLength"
                            :class="inputClass"
                        />
                        <p v-if="mobileForm.errors.code" id="mobile-code-error" class="ui-field-error">{{ mobileForm.errors.code }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <UiButton
                            type="submit"
                            :disabled="!mobileForm.code || mobileForm.processing"
                        >
                            تأیید و ذخیره
                        </UiButton>
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="changeMobile">
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
            <form class="ui-surface p-4 sm:p-6" @submit.prevent="saveEmail">
                <h2 class="font-bold text-gray-900">ایمیل</h2>
                <div class="mt-4">
                    <label for="email" class="ui-label">ایمیل (اختیاری)</label>
                    <input
                        id="email"
                        v-model="emailForm.email"
                        type="email"
                        dir="ltr"
                        autocomplete="email"
                        :aria-invalid="!!emailForm.errors.email"
                        :aria-describedby="emailForm.errors.email ? 'email-error' : undefined"
                        :class="inputClass"
                    />
                    <p v-if="emailForm.errors.email" id="email-error" class="ui-field-error">{{ emailForm.errors.email }}</p>
                </div>
                <UiButton
                    type="submit"
                    :disabled="emailForm.processing || !emailForm.isDirty"
                    class="mt-4"
                >
                    ذخیره
                </UiButton>
            </form>

            <!-- Password -->
            <form class="ui-surface p-4 sm:p-6" @submit.prevent="savePassword">
                <h2 class="font-bold text-gray-900">رمز عبور</h2>
                <p v-if="!currentPasswordRequired" class="mt-1 text-sm text-gray-600">
                    با کد پیامکی وارد شده‌اید؛ می‌توانید بدون رمز فعلی، رمز جدید تعیین کنید.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div v-if="currentPasswordRequired" class="sm:col-span-2">
                        <label for="current_password" class="ui-label">رمز فعلی</label>
                        <input
                            id="current_password"
                            :aria-invalid="!!passwordForm.errors.current_password"
                            :aria-describedby="passwordForm.errors.current_password ? 'current_password-error' : undefined"
                            v-model="passwordForm.current_password"
                            type="password"
                            dir="ltr"
                            autocomplete="current-password"
                            :class="inputClass"
                        />
                        <p v-if="passwordForm.errors.current_password" id="current_password-error" class="ui-field-error">
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>
                    <div>
                        <label for="password" class="ui-label">رمز جدید</label>
                        <input
                            id="password"
                            :aria-invalid="!!passwordForm.errors.password"
                            :aria-describedby="passwordForm.errors.password ? 'password-error' : undefined"
                            v-model="passwordForm.password"
                            type="password"
                            dir="ltr"
                            autocomplete="new-password"
                            :class="inputClass"
                        />
                        <p v-if="passwordForm.errors.password" id="password-error" class="ui-field-error">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="ui-label">تکرار رمز جدید</label>
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

                <UiButton
                    type="submit"
                    :disabled="passwordForm.processing"
                    class="mt-4"
                >
                    تغییر رمز
                </UiButton>
            </form>
        </div>
    </AppLayout>
</template>
