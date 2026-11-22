<script setup>
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import { ElDescriptions, ElDescriptionsItem, ElForm, ElInput, ElTag } from 'element-plus';
import 'element-plus/es/components/descriptions/style/css';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import 'element-plus/es/components/tag/style/css';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    currentPasswordRequired: { type: Boolean, required: true },
    codeLength: { type: Number, required: true },
    resendAfterSeconds: { type: Number, required: true },
});


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

function submitMobile() {
    if (codeSent.value) {
        if (mobileForm.code && !mobileForm.processing) confirmMobile();

        return;
    }

    if (mobile.value && !sendCodeForm.processing) sendMobileCode();
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

</script>

<template>
    <Head title="حساب کاربری" />

    <AppLayout>
        <div class="grid gap-4 sm:gap-5">
            <UiPageHeader title="اطلاعات کاربری" icon="user" />

            <!-- Account -->
            <PanelCard body-class="grid gap-3 [--el-card-padding:1rem] sm:[--el-card-padding:1.5rem]">
                <h2 class="text-base font-bold text-ui-ink">اطلاعات حساب</h2>
                <ElDescriptions :column="2" direction="vertical" border>
                    <ElDescriptionsItem label="نام">{{ profile.name }}</ElDescriptionsItem>
                    <ElDescriptionsItem label="کد ملی"><bdi dir="ltr">{{ profile.nationalCode ?? '—' }}</bdi></ElDescriptionsItem>
                </ElDescriptions>
                <p class="text-sm text-ui-muted">برای تغییر نام یا کد ملی با مدیر تماس بگیرید.</p>
            </PanelCard>

            <div class="grid gap-4 sm:gap-5 xl:grid-cols-2">
                <!-- Mobile -->
                <PanelCard body-class="grid content-start gap-4 [--el-card-padding:1rem] sm:[--el-card-padding:1.5rem]">
                    <div class="grid gap-2">
                        <h2 class="text-base font-bold text-ui-ink">شماره موبایل</h2>
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ui-muted">
                            شماره‌ی فعلی:
                            <bdi dir="ltr" class="font-semibold text-ui-ink">{{ profile.mobile ?? '—' }}</bdi>
                            <ElTag v-if="profile.mobile" :type="profile.mobileVerified ? 'success' : 'warning'" size="small" round>
                                {{ profile.mobileVerified ? 'تأییدشده' : 'تأییدنشده' }}
                            </ElTag>
                        </p>
                    </div>

                    <ElForm label-position="top" novalidate class="grid gap-4" @submit.prevent="submitMobile">
                        <PanelFormField label="شماره‌ی جدید" for="mobile" :error="mobileError">
                            <ElInput
                                id="mobile"
                                ref="mobileInput"
                                v-model="mobile"
                                type="tel"
                                dir="ltr"
                                autocomplete="tel-national"
                                :disabled="codeSent || sendCodeForm.processing"
                                :aria-invalid="!!mobileError"
                                :aria-describedby="mobileError ? 'mobile-error' : undefined"
                            />
                            <template #error="{ error }">
                                <div id="mobile-error" class="el-form-item__error" role="alert">{{ error }}</div>
                            </template>
                        </PanelFormField>

                        <PanelButton
                            v-if="!codeSent"
                            type="submit"
                            :disabled="!mobile || sendCodeForm.processing"
                            class="justify-self-start"
                        >
                            ارسال کد تأیید
                        </PanelButton>

                        <PanelFormField v-if="codeSent" :label="`کد ${codeLength} رقمی پیامک‌شده`" for="mobile-code" :error="mobileForm.errors.code">
                            <ElInput
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
                            />
                            <template #error="{ error }">
                                <div id="mobile-code-error" class="el-form-item__error" role="alert">{{ error }}</div>
                            </template>
                        </PanelFormField>
                        <div v-if="codeSent" class="flex flex-wrap items-center gap-2">
                            <PanelButton
                                type="submit"
                                :disabled="!mobileForm.code || mobileForm.processing"
                            >
                                تأیید و ذخیره
                            </PanelButton>
                            <PanelButton type="button" variant="ghost" @click="changeMobile">
                                تغییر شماره
                            </PanelButton>
                            <PanelButton
                                type="button"
                                :disabled="secondsLeft > 0 || sendCodeForm.processing"
                                variant="link"
                                @click="sendMobileCode"
                            >
                                {{ secondsLeft > 0 ? `ارسال دوباره تا ${secondsLeft} ثانیه` : 'ارسال دوباره' }}
                            </PanelButton>
                        </div>
                    </ElForm>
                </PanelCard>

                <!-- Email -->
                <PanelCard body-class="[--el-card-padding:1rem] sm:[--el-card-padding:1.5rem]">
                    <ElForm label-position="top" novalidate class="grid gap-4" @submit.prevent="saveEmail">
                        <div class="grid gap-2">
                            <h2 class="text-base font-bold text-ui-ink">ایمیل</h2>
                            <p class="text-sm text-ui-muted">آدرس ایمیل حساب خود را وارد یا به‌روزرسانی کنید.</p>
                        </div>
                        <PanelFormField label="ایمیل (اختیاری)" for="email" :error="emailForm.errors.email">
                            <ElInput
                                id="email"
                                v-model="emailForm.email"
                                type="email"
                                dir="ltr"
                                autocomplete="email"
                                :aria-invalid="!!emailForm.errors.email"
                                :aria-describedby="emailForm.errors.email ? 'email-error' : undefined"
                            />
                            <template #error="{ error }">
                                <div id="email-error" class="el-form-item__error" role="alert">{{ error }}</div>
                            </template>
                        </PanelFormField>
                        <PanelButton
                            type="submit"
                            :disabled="emailForm.processing || !emailForm.isDirty"
                            class="justify-self-start"
                        >
                            ذخیره
                        </PanelButton>
                    </ElForm>
                </PanelCard>
            </div>

            <!-- Password -->
            <PanelCard body-class="[--el-card-padding:1rem] sm:[--el-card-padding:1.5rem]">
                <ElForm label-position="top" novalidate class="grid gap-4" @submit.prevent="savePassword">
                    <div class="grid gap-2">
                        <h2 class="text-base font-bold text-ui-ink">رمز عبور</h2>
                        <p v-if="!currentPasswordRequired" class="text-sm text-ui-muted">
                            با کد پیامکی وارد شده‌اید؛ می‌توانید بدون رمز فعلی، رمز جدید تعیین کنید.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <PanelFormField v-if="currentPasswordRequired" label="رمز فعلی" for="current_password" :error="passwordForm.errors.current_password" class="sm:col-start-1">
                            <ElInput
                                id="current_password"
                                :aria-invalid="!!passwordForm.errors.current_password"
                                :aria-describedby="passwordForm.errors.current_password ? 'current_password-error' : undefined"
                                v-model="passwordForm.current_password"
                                type="password"
                                dir="ltr"
                                autocomplete="current-password"
                            />
                            <template #error="{ error }">
                                <div id="current_password-error" class="el-form-item__error" role="alert">{{ error }}</div>
                            </template>
                        </PanelFormField>
                        <PanelFormField label="رمز جدید" for="password" :error="passwordForm.errors.password" class="sm:col-start-1">
                            <ElInput
                                id="password"
                                :aria-invalid="!!passwordForm.errors.password"
                                :aria-describedby="passwordForm.errors.password ? 'password-error' : undefined"
                                v-model="passwordForm.password"
                                type="password"
                                dir="ltr"
                                autocomplete="new-password"
                            />
                            <template #error="{ error }">
                                <div id="password-error" class="el-form-item__error" role="alert">{{ error }}</div>
                            </template>
                        </PanelFormField>
                        <PanelFormField label="تکرار رمز جدید" for="password_confirmation">
                            <ElInput
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                dir="ltr"
                                autocomplete="new-password"
                            />
                        </PanelFormField>
                    </div>

                    <p class="text-sm text-ui-muted">با تغییر رمز، از همه‌ی دستگاه‌های دیگر خارج می‌شوید.</p>

                    <PanelButton
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="justify-self-start"
                    >
                        تغییر رمز
                    </PanelButton>
                </ElForm>
            </PanelCard>
        </div>
    </AppLayout>
</template>
