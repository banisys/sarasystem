<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CustomFieldInput from '@/Components/CustomFieldInput.vue';
import DocumentUploads from '@/Components/DocumentUploads.vue';
import JalaliDateInput from '@/Components/JalaliDateInput.vue';
import VerifiedMobileInput from '@/Components/VerifiedMobileInput.vue';
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
    custom: {},
    documents: {},
    document_expiry: {},
    [props.honeypot]: '',
});

const isIranian = computed(() => form.nationality === 'iranian');
const selectedProgram = computed(() => props.programs.find((program) => program.id === Number(form.program_id)));

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

const inputClass = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-violet-500 focus:ring-violet-500';
const labelClass = 'block text-sm font-medium text-gray-700';
const errorClass = 'mt-1 text-sm text-red-600';
const sectionClass = 'rounded-2xl bg-white p-6 shadow-sm';
</script>

<template>
    <Head title="پیش‌ثبت‌نام" />

    <PublicLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">پیش‌ثبت‌نام</h1>
                <p class="mt-2 text-sm text-gray-600">فرم زیر را پر کنید. همکاران ما پس از بررسی با شما تماس می‌گیرند.</p>
            </div>

            <p v-if="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-700">{{ status }}</p>

            <p v-if="!programs.length" class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">
                در حال حاضر پیش‌ثبت‌نام انجام نمی‌شود.
            </p>

            <form v-else class="space-y-6" novalidate @submit.prevent="submit">
                <!-- Left empty by people; see PreregistrationController::HONEYPOT. -->
                <div class="absolute left-[-9999px]" aria-hidden="true">
                    <label :for="honeypot">وب‌سایت</label>
                    <input :id="honeypot" v-model="form[honeypot]" type="text" tabindex="-1" autocomplete="off" />
                </div>

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">برنامه‌ی آموزشی</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="program_id" :class="labelClass">پیش‌ثبت‌نام برای</label>
                            <select id="program_id" v-model="form.program_id" :class="inputClass">
                                <option value="" disabled>انتخاب کنید</option>
                                <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.title }}</option>
                            </select>
                            <p v-if="selectedProgram?.description" class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ selectedProgram.description }}</p>
                            <p v-if="form.errors.program_id" :class="errorClass">{{ form.errors.program_id }}</p>
                        </div>
                        <div v-if="asks('preferred_tenant') && options.tenants.length">
                            <label for="preferred_tenant_id" :class="labelClass">{{ labels.tenant }} مورد نظر{{ optional('preferred_tenant') }}</label>
                            <select id="preferred_tenant_id" v-model="form.preferred_tenant_id" :class="inputClass">
                                <option value="">{{ requires('preferred_tenant') ? 'انتخاب کنید' : 'فرقی نمی‌کند' }}</option>
                                <option v-for="tenant in options.tenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option>
                            </select>
                            <p v-if="form.errors.preferred_tenant_id" :class="errorClass">{{ form.errors.preferred_tenant_id }}</p>
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
                    <h2 class="font-bold text-gray-900">اطلاعات {{ labels.learner }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" :class="labelClass">نام</label>
                            <input id="first_name" v-model="form.first_name" type="text" maxlength="50" :class="inputClass" />
                            <p v-if="form.errors.first_name" :class="errorClass">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label for="last_name" :class="labelClass">نام خانوادگی</label>
                            <input id="last_name" v-model="form.last_name" type="text" maxlength="50" :class="inputClass" />
                            <p v-if="form.errors.last_name" :class="errorClass">{{ form.errors.last_name }}</p>
                        </div>
                        <div v-if="asks('father_name')">
                            <label for="father_name" :class="labelClass">نام پدر{{ optional('father_name') }}</label>
                            <input id="father_name" v-model="form.father_name" type="text" maxlength="50" :class="inputClass" />
                            <p v-if="form.errors.father_name" :class="errorClass">{{ form.errors.father_name }}</p>
                        </div>
                        <div>
                            <label for="gender" :class="labelClass">جنسیت</label>
                            <select id="gender" v-model="form.gender" :class="inputClass">
                                <option value="" disabled>انتخاب کنید</option>
                                <option v-for="option in options.genders" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.gender" :class="errorClass">{{ form.errors.gender }}</p>
                        </div>
                        <div>
                            <label for="nationality" :class="labelClass">ملیت</label>
                            <select id="nationality" v-model="form.nationality" :class="inputClass">
                                <option v-for="option in options.nationalities" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.nationality" :class="errorClass">{{ form.errors.nationality }}</p>
                        </div>
                        <div>
                            <label for="national_code" :class="labelClass">{{ isIranian ? 'کد ملی' : 'کد اتباع' }}</label>
                            <input
                                id="national_code"
                                v-model="form.national_code"
                                type="text"
                                inputmode="numeric"
                                dir="ltr"
                                :maxlength="isIranian ? 10 : 12"
                                :class="inputClass"
                            />
                            <p v-if="form.errors.national_code" :class="errorClass">{{ form.errors.national_code }}</p>
                        </div>
                        <div v-if="asks('birth_date')">
                            <label for="birth_date" :class="labelClass">تاریخ تولد{{ optional('birth_date') }}</label>
                            <JalaliDateInput
                                id="birth_date"
                                v-model="form.birth_date"
                                :min-year="limits.birthYears[0]"
                                :max-year="limits.birthYears[1]"
                                :input-class="inputClass"
                            />
                            <p v-if="form.errors.birth_date" :class="errorClass">{{ form.errors.birth_date }}</p>
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

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">اطلاعات تماس</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <VerifiedMobileInput
                            v-model="form.mobile"
                            class="sm:col-span-2"
                            label="تلفن همراه"
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
                            <input id="phone" v-model="form.phone" type="tel" dir="ltr" placeholder="02112345678" maxlength="11" :class="inputClass" />
                            <p v-if="form.errors.phone" :class="errorClass">{{ form.errors.phone }}</p>
                        </div>
                        <div v-if="asks('email')">
                            <label for="email" :class="labelClass">ایمیل{{ optional('email') }}</label>
                            <input id="email" v-model="form.email" type="email" dir="ltr" maxlength="100" :class="inputClass" />
                            <p v-if="form.errors.email" :class="errorClass">{{ form.errors.email }}</p>
                        </div>
                        <div v-if="asks('postal_code')">
                            <label for="postal_code" :class="labelClass">کد پستی{{ optional('postal_code') }}</label>
                            <input id="postal_code" v-model="form.postal_code" type="text" inputmode="numeric" dir="ltr" maxlength="10" :class="inputClass" />
                            <p v-if="form.errors.postal_code" :class="errorClass">{{ form.errors.postal_code }}</p>
                        </div>
                        <div v-if="asks('city')">
                            <label for="city" :class="labelClass">شهر{{ optional('city') }}</label>
                            <input id="city" v-model="form.city" type="text" maxlength="100" :class="inputClass" />
                            <p v-if="form.errors.city" :class="errorClass">{{ form.errors.city }}</p>
                        </div>
                        <div v-if="asks('address')" class="sm:col-span-2">
                            <label for="address" :class="labelClass">نشانی{{ optional('address') }}</label>
                            <textarea id="address" v-model="form.address" rows="2" maxlength="300" :class="inputClass" />
                            <p v-if="form.errors.address" :class="errorClass">{{ form.errors.address }}</p>
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
                    <h2 class="font-bold text-gray-900">سایر اطلاعات</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
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
                    <h2 class="font-bold text-gray-900">مدارک</h2>
                    <p class="mt-1 text-sm text-gray-500">اگر پیش‌ثبت‌نام را قبلاً فرستاده‌اید، مدارکی را که فرستاده‌اید لازم نیست دوباره بفرستید.</p>
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

                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-violet-600 px-6 py-2.5 font-medium text-white hover:bg-violet-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'در حال ارسال…' : 'ثبت پیش‌ثبت‌نام' }}
                    </button>
                    <p v-if="form.hasErrors" class="text-sm text-red-600">لطفاً خطاهای فرم را برطرف کنید.</p>
                </div>
            </form>
        </div>
    </PublicLayout>
</template>
