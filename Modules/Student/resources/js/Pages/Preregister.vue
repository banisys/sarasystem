<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CustomFieldInput from '@/Components/CustomFieldInput.vue';
import DocumentUploads from '@/Components/DocumentUploads.vue';
import JalaliDateInput from '@/Components/JalaliDateInput.vue';
import VerifiedMobileInput from '@/Components/VerifiedMobileInput.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

// The public «پیش‌ثبت‌نام» form (ADR 0018); props from Modules\Student\Http\Controllers\PreregistrationController.
const props = defineProps({
    // Each with its `form`: {modes, fields} as the organization set it up for that program (ADR 0017).
    programs: { type: Array, required: true },
    // The form for all programs, until one is chosen.
    form: { type: Object, required: true },
    // Documents to upload until a program is chosen; each program has its own `documents` (ADR 0019).
    documents: { type: Array, default: () => [] },
    // Section key => heading; added fields go at the end of their section.
    sections: { type: Object, required: true },
    options: { type: Object, required: true },
    // What a tenant and a learner are called in this installation.
    labels: { type: Object, required: true },
    limits: { type: Object, required: true },
    honeypot: { type: String, required: true },
    // {verifiedMobile, codeLength, resendAfterSeconds}: the mobile is confirmed by SMS before saving.
    mobileVerification: { type: Object, required: true },
});

const status = computed(() => usePage().props.flash.status);

const form = useForm({
    program_id: '',
    preferred_tenant_id: '',
    first_name: '',
    last_name: '',
    father_name: '',
    nationality: 'iranian',
    national_code: '',
    birth_date: '',
    gender: '',
    mobile: '',
    phone: '',
    email: '',
    postal_code: '',
    city: '',
    address: '',
    // A school's parents and the child's login mobile (ADR 0028).
    father: { name: '', national_code: '', mobile: '' },
    mother: { name: '', national_code: '', mobile: '' },
    learner_mobile: '',
    login_mobile_from: '',
    custom: {},
    documents: {},
    document_expiry: {},
    [props.honeypot]: '',
});

const isIranian = computed(() => form.nationality === 'iranian');
const selectedProgram = computed(() => props.programs.find((program) => program.id === Number(form.program_id)));
const availableTenants = computed(() => selectedProgram.value?.tenants ?? props.options.tenants);
watch(availableTenants, (tenants) => {
    if (!tenants.some((tenant) => tenant.id === Number(form.preferred_tenant_id))) {
        form.preferred_tenant_id = '';
    }
});

// Which built-in fields are asked, and the added fields, for the chosen program.
const currentForm = computed(() => selectedProgram.value?.form ?? props.form);
const currentDocuments = computed(() => selectedProgram.value?.documents ?? props.documents);
// Bumped after saving, to empty the file inputs.
const documentsKey = ref(0);
const asks = (key) => (currentForm.value.modes[key] ?? 'required') !== 'hidden';
const requires = (key) => (currentForm.value.modes[key] ?? 'required') === 'required';
const optional = (key) => (requires(key) ? '' : ' (اختیاری)');
const lastSection = Object.keys(props.sections).at(-1);
const fieldsIn = (section) =>
    currentForm.value.fields.filter((field) => field.section === section || (section === lastSection && !props.sections[field.section]));
const parents = [
    { key: 'father', label: 'پدر' },
    { key: 'mother', label: 'مادر' },
];
const asksParents = computed(() => ['father', 'mother', 'learner_mobile', 'login_mobile'].some(asks) || fieldsIn('parents').length > 0);
// Whose mobile the learner may sign in with, among those typed.
const loginChoices = computed(() =>
    [
        asks('learner_mobile') && form.learner_mobile ? { value: 'learner', label: `موبایل خود ${props.labels.learner}` } : null,
        asks('father') && form.father.mobile ? { value: 'father', label: 'موبایل پدر' } : null,
        asks('mother') && form.mother.mobile ? { value: 'mother', label: 'موبایل مادر' } : null,
    ].filter(Boolean),
);
watch(loginChoices, (choices) => {
    if (!choices.some((choice) => choice.value === form.login_mobile_from)) {
        form.login_mobile_from = choices.length === 1 ? choices[0].value : '';
    }
});
const customError = (field) => form.errors[`custom.${field.key}`] ?? form.errors[`custom.${field.key}.0`];

function prepareForm() {
    for (const field of currentForm.value.fields) {
        if (!(field.key in form.custom)) {
            form.custom[field.key] = field.type === 'multi_select' ? [] : field.type === 'checkbox' ? false : '';
        }
    }
}

prepareForm();
watch(currentForm, prepareForm);

function submit() {
    form.post(route('preregister.store'), {
        preserveScroll: 'errors',
        onSuccess: () => {
            form.reset();
            form.custom = {};
            form.documents = {};
            form.document_expiry = {};
            documentsKey.value += 1;
            prepareForm();
        },
    });
}

const inputClass = 'ui-field';
const labelClass = 'ui-label';
const errorClass = 'ui-field-error';
const sectionClass = 'ui-surface p-5 sm:p-7';
const headingClass = 'mb-5 border-b border-ui-line pb-3 text-lg font-bold text-ui-ink';
</script>

<template>
    <Head title="پیش‌ثبت‌نام" />

    <PublicLayout>
        <div class="mx-auto max-w-3xl space-y-5">
            <header class="relative overflow-hidden rounded-ui border border-ui-line bg-brand-50 px-5 py-7 sm:px-8 sm:py-10">
                <span class="pointer-events-none absolute -end-14 -top-24 size-72 rounded-full border-[28px] border-brand-100 opacity-60" aria-hidden="true" />
                <div class="relative">
                    <span class="mb-4 grid size-11 place-items-center rounded-ui bg-brand-600 text-brand-contrast">
                        <PanelIcon name="clipboard" class="size-6" />
                    </span>
                    <h1 class="text-3xl font-extrabold text-ui-ink sm:text-4xl">پیش‌ثبت‌نام</h1>
                    <p class="mt-3 max-w-md text-sm leading-7 text-ui-muted">فرم زیر را پر کنید. همکاران ما پس از بررسی با شما تماس می‌گیرند.</p>
                </div>
            </header>

            <UiNotice v-if="status" tone="success">{{ status }}</UiNotice>

            <UiNotice v-if="!programs.length">
                در حال حاضر پیش‌ثبت‌نام انجام نمی‌شود.
            </UiNotice>

            <form v-else class="space-y-5" novalidate @submit.prevent="submit">
                <!-- Left empty by people; see PreregistrationController::HONEYPOT. -->
                <div class="sr-only" aria-hidden="true">
                    <label :for="honeypot">وب‌سایت</label>
                    <input :id="honeypot" v-model="form[honeypot]" type="text" tabindex="-1" autocomplete="off" />
                </div>

                <section :class="sectionClass">
                    <h2 :class="headingClass">برنامه‌ی آموزشی</h2>
                    <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="program_id" :class="labelClass">پیش‌ثبت‌نام برای</label>
                            <select id="program_id" :aria-invalid="!!form.errors.program_id" :aria-describedby="form.errors.program_id ? 'program_id-error' : undefined" v-model="form.program_id" :class="inputClass">
                                <option value="" disabled>انتخاب کنید</option>
                                <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.title }}</option>
                            </select>
                            <p v-if="selectedProgram?.description" class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ selectedProgram.description }}</p>
                            <p v-if="form.errors.program_id" id="program_id-error" :class="errorClass">{{ form.errors.program_id }}</p>
                        </div>
                        <div v-if="asks('preferred_tenant') && availableTenants.length">
                            <label for="preferred_tenant_id" :class="labelClass">{{ labels.tenant }} مورد نظر{{ optional('preferred_tenant') }}</label>
                            <select id="preferred_tenant_id" :aria-invalid="!!form.errors.preferred_tenant_id" :aria-describedby="form.errors.preferred_tenant_id ? 'preferred_tenant_id-error' : undefined" v-model="form.preferred_tenant_id" :class="inputClass">
                                <option value="">{{ requires('preferred_tenant') ? 'انتخاب کنید' : 'فرقی نمی‌کند' }}</option>
                                <option v-for="tenant in availableTenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option>
                            </select>
                            <p v-if="form.errors.preferred_tenant_id" id="preferred_tenant_id-error" :class="errorClass">{{ form.errors.preferred_tenant_id }}</p>
                        </div>
                        <CustomFieldInput
                            v-for="field in fieldsIn('program')"
                            :key="field.key"
                            v-model="form.custom[field.key]"
                            :field="field"
                            :error="customError(field)"
                            :input-class="inputClass"
                            :label-class="labelClass"
                            :error-class="errorClass"
                        />
                    </div>
                </section>

                <section :class="sectionClass">
                    <h2 :class="headingClass">اطلاعات {{ labels.learner }}</h2>
                    <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" :class="labelClass">نام</label>
                            <input id="first_name" :aria-invalid="!!form.errors.first_name" :aria-describedby="form.errors.first_name ? 'first_name-error' : undefined" v-model="form.first_name" type="text" maxlength="50" :class="inputClass" />
                            <p v-if="form.errors.first_name" id="first_name-error" :class="errorClass">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label for="last_name" :class="labelClass">نام خانوادگی</label>
                            <input id="last_name" :aria-invalid="!!form.errors.last_name" :aria-describedby="form.errors.last_name ? 'last_name-error' : undefined" v-model="form.last_name" type="text" maxlength="50" :class="inputClass" />
                            <p v-if="form.errors.last_name" id="last_name-error" :class="errorClass">{{ form.errors.last_name }}</p>
                        </div>
                        <div v-if="asks('father_name')">
                            <label for="father_name" :class="labelClass">نام پدر{{ optional('father_name') }}</label>
                            <input id="father_name" :aria-invalid="!!form.errors.father_name" :aria-describedby="form.errors.father_name ? 'father_name-error' : undefined" v-model="form.father_name" type="text" maxlength="50" :class="inputClass" />
                            <p v-if="form.errors.father_name" id="father_name-error" :class="errorClass">{{ form.errors.father_name }}</p>
                        </div>
                        <div>
                            <label for="gender" :class="labelClass">جنسیت</label>
                            <select id="gender" :aria-invalid="!!form.errors.gender" :aria-describedby="form.errors.gender ? 'gender-error' : undefined" v-model="form.gender" :class="inputClass">
                                <option value="" disabled>انتخاب کنید</option>
                                <option v-for="option in options.genders" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.gender" id="gender-error" :class="errorClass">{{ form.errors.gender }}</p>
                        </div>
                        <div>
                            <label for="nationality" :class="labelClass">ملیت</label>
                            <select id="nationality" :aria-invalid="!!form.errors.nationality" :aria-describedby="form.errors.nationality ? 'nationality-error' : undefined" v-model="form.nationality" :class="inputClass">
                                <option v-for="option in options.nationalities" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.nationality" id="nationality-error" :class="errorClass">{{ form.errors.nationality }}</p>
                        </div>
                        <div>
                            <label for="national_code" :class="labelClass">{{ isIranian ? 'کد ملی' : 'کد اتباع' }}</label>
                            <input
                                id="national_code"
                                :aria-invalid="!!form.errors.national_code" :aria-describedby="form.errors.national_code ? 'national_code-error' : undefined"
                                v-model="form.national_code"
                                type="text"
                                inputmode="numeric"
                                dir="ltr"
                                :maxlength="isIranian ? 10 : 12"
                                :class="inputClass"
                            />
                            <p v-if="form.errors.national_code" id="national_code-error" :class="errorClass">{{ form.errors.national_code }}</p>
                        </div>
                        <div v-if="asks('birth_date')">
                            <label for="birth_date" :class="labelClass">تاریخ تولد{{ optional('birth_date') }}</label>
                            <JalaliDateInput
                                id="birth_date"
                                :aria-invalid="!!form.errors.birth_date" :aria-describedby="form.errors.birth_date ? 'birth_date-error' : undefined"
                                v-model="form.birth_date"
                                :min-year="limits.birthYears[0]"
                                :max-year="limits.birthYears[1]"
                                :input-class="inputClass"
                            />
                            <p v-if="form.errors.birth_date" id="birth_date-error" :class="errorClass">{{ form.errors.birth_date }}</p>
                        </div>
                        <CustomFieldInput
                            v-for="field in fieldsIn('personal')"
                            :key="field.key"
                            v-model="form.custom[field.key]"
                            :field="field"
                            :error="customError(field)"
                            :input-class="inputClass"
                            :label-class="labelClass"
                            :error-class="errorClass"
                        />
                    </div>
                </section>

                <section v-if="asksParents" :class="sectionClass">
                    <h2 :class="headingClass">والدین</h2>
                    <p v-if="form.errors.father" :class="errorClass">{{ form.errors.father }}</p>
                    <template v-for="parent in parents" :key="parent.key">
                        <fieldset v-if="asks(parent.key)" class="mt-4 grid min-w-0 gap-4 rounded-ui bg-ui-canvas p-4 sm:grid-cols-3">
                            <legend class="mb-1 text-sm font-semibold text-gray-800">{{ parent.label }}{{ optional(parent.key) }}</legend>
                            <div>
                                <label :for="`${parent.key}_name`" :class="labelClass">نام و نام خانوادگی</label>
                                <input :id="`${parent.key}_name`" v-model="form[parent.key].name" type="text" maxlength="100" :class="inputClass" />
                                <p v-if="form.errors[`${parent.key}.name`]" :class="errorClass">{{ form.errors[`${parent.key}.name`] }}</p>
                            </div>
                            <div>
                                <label :for="`${parent.key}_national_code`" :class="labelClass">کد ملی</label>
                                <input
                                    :id="`${parent.key}_national_code`"
                                    v-model="form[parent.key].national_code"
                                    type="text"
                                    inputmode="numeric"
                                    dir="ltr"
                                    maxlength="10"
                                    :class="inputClass"
                                />
                                <p v-if="form.errors[`${parent.key}.national_code`]" :class="errorClass">{{ form.errors[`${parent.key}.national_code`] }}</p>
                            </div>
                            <div>
                                <label :for="`${parent.key}_mobile`" :class="labelClass">موبایل</label>
                                <input :id="`${parent.key}_mobile`" v-model="form[parent.key].mobile" type="tel" dir="ltr" placeholder="09121234567" maxlength="11" :class="inputClass" />
                                <p v-if="form.errors[`${parent.key}.mobile`]" :class="errorClass">{{ form.errors[`${parent.key}.mobile`] }}</p>
                            </div>
                        </fieldset>
                    </template>

                    <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        <div v-if="asks('learner_mobile')">
                            <label for="learner_mobile" :class="labelClass">موبایل خود {{ labels.learner }}{{ optional('learner_mobile') }}</label>
                            <input id="learner_mobile" :aria-invalid="!!form.errors.learner_mobile" :aria-describedby="form.errors.learner_mobile ? 'learner_mobile-error' : undefined" v-model="form.learner_mobile" type="tel" dir="ltr" placeholder="09121234567" maxlength="11" :class="inputClass" />
                            <p v-if="form.errors.learner_mobile" id="learner_mobile-error" :class="errorClass">{{ form.errors.learner_mobile }}</p>
                        </div>
                        <div v-if="asks('login_mobile')" class="sm:col-span-2">
                            <span :class="labelClass">{{ labels.learner }} با کدام موبایل وارد سامانه شود؟</span>
                            <p v-if="!loginChoices.length" class="mt-1 text-sm text-gray-500">ابتدا موبایل {{ labels.learner }} یا یکی از والدین را وارد کنید.</p>
                            <div class="mt-2 flex flex-wrap gap-4">
                                <label v-for="choice in loginChoices" :key="choice.value" class="flex items-center gap-2 text-sm text-gray-800">
                                    <input v-model="form.login_mobile_from" type="radio" :value="choice.value" class="text-brand-600" />
                                    {{ choice.label }}
                                </label>
                            </div>
                            <p v-if="form.errors.login_mobile_from" :class="errorClass">{{ form.errors.login_mobile_from }}</p>
                        </div>
                        <CustomFieldInput
                            v-for="field in fieldsIn('parents')"
                            :key="field.key"
                            v-model="form.custom[field.key]"
                            :field="field"
                            :error="customError(field)"
                            :input-class="inputClass"
                            :label-class="labelClass"
                            :error-class="errorClass"
                        />
                    </div>
                </section>

                <section :class="sectionClass">
                    <h2 :class="headingClass">اطلاعات تماس</h2>
                    <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        <VerifiedMobileInput
                            v-model="form.mobile"
                            class="sm:col-span-2"
                            :label="asks('father') || asks('mother') ? 'تلفن همراه برای پیگیری پیش‌ثبت‌نام' : 'تلفن همراه'"
                            :verification="mobileVerification"
                            :send-url="route('preregister.mobile.code')"
                            :verify-url="route('preregister.mobile.verify')"
                            hint="برای کودکان و نوجوانان، موبایل پدر، مادر یا سرپرست. برای ثبت پیش‌ثبت‌نام، شماره باید با کد پیامکی تأیید شود."
                            :error="form.errors.mobile"
                            :input-class="inputClass"
                            :label-class="labelClass"
                            :error-class="errorClass"
                            @verified="form.clearErrors('mobile')"
                        />
                        <div v-if="asks('phone')">
                            <label for="phone" :class="labelClass">تلفن ثابت{{ optional('phone') }}</label>
                            <input id="phone" :aria-invalid="!!form.errors.phone" :aria-describedby="form.errors.phone ? 'phone-error' : undefined" v-model="form.phone" type="tel" dir="ltr" placeholder="02112345678" maxlength="11" :class="inputClass" />
                            <p v-if="form.errors.phone" id="phone-error" :class="errorClass">{{ form.errors.phone }}</p>
                        </div>
                        <div v-if="asks('email')">
                            <label for="email" :class="labelClass">ایمیل{{ optional('email') }}</label>
                            <input id="email" :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'email-error' : undefined" v-model="form.email" type="email" dir="ltr" maxlength="100" :class="inputClass" />
                            <p v-if="form.errors.email" id="email-error" :class="errorClass">{{ form.errors.email }}</p>
                        </div>
                        <div v-if="asks('postal_code')">
                            <label for="postal_code" :class="labelClass">کد پستی{{ optional('postal_code') }}</label>
                            <input id="postal_code" :aria-invalid="!!form.errors.postal_code" :aria-describedby="form.errors.postal_code ? 'postal_code-error' : undefined" v-model="form.postal_code" type="text" inputmode="numeric" dir="ltr" maxlength="10" :class="inputClass" />
                            <p v-if="form.errors.postal_code" id="postal_code-error" :class="errorClass">{{ form.errors.postal_code }}</p>
                        </div>
                        <div v-if="asks('city')">
                            <label for="city" :class="labelClass">شهر{{ optional('city') }}</label>
                            <input id="city" :aria-invalid="!!form.errors.city" :aria-describedby="form.errors.city ? 'city-error' : undefined" v-model="form.city" type="text" maxlength="100" :class="inputClass" />
                            <p v-if="form.errors.city" id="city-error" :class="errorClass">{{ form.errors.city }}</p>
                        </div>
                        <div v-if="asks('address')" class="sm:col-span-2">
                            <label for="address" :class="labelClass">نشانی{{ optional('address') }}</label>
                            <textarea id="address" :aria-invalid="!!form.errors.address" :aria-describedby="form.errors.address ? 'address-error' : undefined" v-model="form.address" rows="2" maxlength="300" :class="inputClass" />
                            <p v-if="form.errors.address" id="address-error" :class="errorClass">{{ form.errors.address }}</p>
                        </div>
                        <CustomFieldInput
                            v-for="field in fieldsIn('contact')"
                            :key="field.key"
                            v-model="form.custom[field.key]"
                            :field="field"
                            :error="customError(field)"
                            :input-class="inputClass"
                            :label-class="labelClass"
                            :error-class="errorClass"
                        />
                    </div>
                </section>

                <section v-if="fieldsIn('other').length" :class="sectionClass">
                    <h2 :class="headingClass">سایر اطلاعات</h2>
                    <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        <CustomFieldInput
                            v-for="field in fieldsIn('other')"
                            :key="field.key"
                            v-model="form.custom[field.key]"
                            :field="field"
                            :error="customError(field)"
                            :input-class="inputClass"
                            :label-class="labelClass"
                            :error-class="errorClass"
                        />
                    </div>
                </section>

                <section v-if="currentDocuments.length" :class="sectionClass">
                    <h2 :class="headingClass">مدارک</h2>
                    <p class="text-sm text-ui-muted">اگر پیش‌ثبت‌نام را قبلاً فرستاده‌اید، مدارکی را که فرستاده‌اید لازم نیست دوباره بفرستید.</p>
                    <DocumentUploads
                        :key="documentsKey"
                        v-model:files="form.documents"
                        v-model:expiry="form.document_expiry"
                        class="mt-4"
                        :fields="currentDocuments"
                        :errors="form.errors"
                        :input-class="inputClass"
                        :label-class="labelClass"
                        :error-class="errorClass"
                    />
                </section>

                <div class="ui-surface flex flex-wrap items-center gap-3 p-4 sm:p-5">
                    <UiButton type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                        {{ form.processing ? 'در حال ارسال…' : 'ثبت پیش‌ثبت‌نام' }}
                    </UiButton>
                    <p v-if="form.hasErrors" class="text-sm text-rose-700" role="alert">لطفاً خطاهای فرم را برطرف کنید.</p>
                </div>
            </form>
        </div>
    </PublicLayout>
</template>
