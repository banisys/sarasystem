<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import JalaliDateInput from '@/Components/JalaliDateInput.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

// The public «همکاری با ما» form (ADR 0014); props from Modules\Employee\Http\Controllers\CareersController.
const props = defineProps({
    positions: { type: Array, required: true },
    options: { type: Object, required: true },
    limits: { type: Object, required: true },
    honeypot: { type: String, required: true },
    // {verifiedMobile, codeLength, resendAfterSeconds}: the mobile is confirmed by SMS before saving.
    mobileVerification: { type: Object, required: true },
});

const status = computed(() => usePage().props.flash.status);

const emptyEducation = () => ({
    level: '', major: '', orientation: '', average: '', rank: '', graduation_date: '', university_type: '', university: '',
});
const emptyWork = () => ({
    company_name: '', job_position: '', start_date: '', end_date: '', termination_reason: '', company_phone: '',
});

const form = useForm({
    job_position_id: '',
    first_name: '',
    last_name: '',
    father_name: '',
    nationality: 'iranian',
    national_code: '',
    birth_date: '',
    gender: '',
    military_status: '',
    mobile: '',
    phone: '',
    email: '',
    postal_code: '',
    address: '',
    education: [emptyEducation()],
    work_history: [],
    resume: null,
    [props.honeypot]: '',
});

const isIranian = computed(() => form.nationality === 'iranian');
const asksMilitaryStatus = computed(() => isIranian.value && form.gender === 'male');
const universityLevels = computed(() => props.options.educationLevels.filter((level) => level.university).map((level) => level.value));
const selectedPosition = computed(() => props.positions.find((position) => position.id === Number(form.job_position_id)));

// Mobile confirmation: send a code, type it back; the confirmed number is
// kept in the session (verifiedMobile) and locked in the form.
const verifiedMobile = ref(props.mobileVerification.verifiedMobile);
const sendCodeForm = useForm({});
const verifyForm = useForm({ code: '' });
const codeSent = ref(false);
const secondsLeft = ref(0);
let timer = null;

if (verifiedMobile.value) {
    form.mobile = verifiedMobile.value;
}

watch(
    () => props.mobileVerification.verifiedMobile,
    (mobile) => {
        verifiedMobile.value = mobile;
        if (mobile) {
            form.mobile = mobile;
            form.clearErrors('mobile');
        }
        codeSent.value = false;
        verifyForm.reset();
    },
);

function sendCode() {
    sendCodeForm
        .transform(() => ({ mobile: form.mobile }))
        .post(route('careers.mobile.code'), {
            preserveScroll: true,
            onSuccess: () => {
                codeSent.value = true;
                clearInterval(timer);
                secondsLeft.value = props.mobileVerification.resendAfterSeconds;
                timer = setInterval(() => {
                    secondsLeft.value -= 1;
                    if (secondsLeft.value <= 0) clearInterval(timer);
                }, 1000);
            },
        });
}

function verifyCode() {
    verifyForm.transform((data) => ({ ...data, mobile: form.mobile })).post(route('careers.mobile.verify'), { preserveScroll: true });
}

function changeMobile() {
    codeSent.value = false;
    verifyForm.reset();
    form.mobile = '';
    // The server checks the submitted number against the confirmed one, so another number needs a new code.
    verifiedMobile.value = null;
}

onBeforeUnmount(() => clearInterval(timer));

function onResumePicked(event) {
    form.resume = event.target.files[0] ?? null;
}

function submit() {
    form.post(route('careers.store'), {
        preserveScroll: 'errors',
        onSuccess: () => {
            form.reset();
            form.education = [emptyEducation()];
            form.work_history = [];
            document.getElementById('resume').value = '';
        },
    });
}

const inputClass = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-violet-500 focus:ring-violet-500';
const labelClass = 'block text-sm font-medium text-gray-700';
const errorClass = 'mt-1 text-sm text-red-600';
const sectionClass = 'rounded-2xl bg-white p-6 shadow-sm';
const rowClass = 'rounded-xl border border-dashed border-gray-300 p-4';
const linkButtonClass = 'text-sm font-medium text-violet-700 hover:text-violet-900';
</script>

<template>
    <Head title="همکاری با ما" />

    <PublicLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">همکاری با ما</h1>
                <p class="mt-2 text-sm text-gray-600">برای همکاری با ما فرم زیر را پر کنید. همکاران ما پس از بررسی با شما تماس می‌گیرند.</p>
            </div>

            <p v-if="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-700">{{ status }}</p>

            <p v-if="!positions.length" class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">
                در حال حاضر موقعیت شغلی خالی نداریم.
            </p>

            <form v-else class="space-y-6" novalidate @submit.prevent="submit">
                <!-- Left empty by people; see CareersController::HONEYPOT. -->
                <div class="absolute left-[-9999px]" aria-hidden="true">
                    <label :for="honeypot">وب‌سایت</label>
                    <input :id="honeypot" v-model="form[honeypot]" type="text" tabindex="-1" autocomplete="off" />
                </div>

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">شغل درخواستی</h2>
                    <div class="mt-4">
                        <label for="job_position_id" :class="labelClass">شغل درخواستی</label>
                        <select id="job_position_id" v-model="form.job_position_id" :class="inputClass">
                            <option value="" disabled>انتخاب کنید</option>
                            <option v-for="position in positions" :key="position.id" :value="position.id">{{ position.title }}</option>
                        </select>
                        <p v-if="selectedPosition?.description" class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ selectedPosition.description }}</p>
                        <p v-if="form.errors.job_position_id" :class="errorClass">{{ form.errors.job_position_id }}</p>
                    </div>
                </section>

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">اطلاعات فردی</h2>
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
                        <div>
                            <label for="father_name" :class="labelClass">نام پدر</label>
                            <input id="father_name" v-model="form.father_name" type="text" maxlength="50" :class="inputClass" />
                            <p v-if="form.errors.father_name" :class="errorClass">{{ form.errors.father_name }}</p>
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
                        <div>
                            <label for="birth_date" :class="labelClass">تاریخ تولد</label>
                            <JalaliDateInput
                                id="birth_date"
                                v-model="form.birth_date"
                                :min-year="limits.birthYears[0]"
                                :max-year="limits.birthYears[1]"
                                :input-class="inputClass"
                            />
                            <p v-if="form.errors.birth_date" :class="errorClass">{{ form.errors.birth_date }}</p>
                        </div>
                        <div>
                            <label for="gender" :class="labelClass">جنسیت</label>
                            <select id="gender" v-model="form.gender" :class="inputClass">
                                <option value="" disabled>انتخاب کنید</option>
                                <option v-for="option in options.genders" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.gender" :class="errorClass">{{ form.errors.gender }}</p>
                        </div>
                        <div v-if="asksMilitaryStatus">
                            <label for="military_status" :class="labelClass">وضعیت نظام وظیفه</label>
                            <select id="military_status" v-model="form.military_status" :class="inputClass">
                                <option value="" disabled>انتخاب کنید</option>
                                <option v-for="option in options.militaryStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.military_status" :class="errorClass">{{ form.errors.military_status }}</p>
                        </div>
                    </div>
                </section>

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">اطلاعات تماس</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="mobile" :class="labelClass">تلفن همراه</label>
                            <div class="flex flex-wrap items-start gap-2">
                                <input
                                    id="mobile"
                                    v-model="form.mobile"
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
                                    :disabled="sendCodeForm.processing || !form.mobile"
                                    class="mt-1 rounded-lg border border-violet-600 px-4 py-2 text-sm font-medium text-violet-700 hover:bg-violet-50 disabled:opacity-60"
                                    @click="sendCode"
                                >
                                    ارسال کد تأیید
                                </button>
                            </div>
                            <p v-if="!verifiedMobile && !codeSent" class="mt-1 text-xs text-gray-500">برای ثبت درخواست، شماره موبایل باید با کد پیامکی تأیید شود.</p>
                            <p v-if="sendCodeForm.errors.mobile || verifyForm.errors.mobile || form.errors.mobile" :class="errorClass">
                                {{ sendCodeForm.errors.mobile || verifyForm.errors.mobile || form.errors.mobile }}
                            </p>

                            <div v-if="codeSent && !verifiedMobile" class="mt-3 rounded-lg bg-violet-50 p-3">
                                <label for="code" :class="labelClass">کد پیامک‌شده به {{ form.mobile }}</label>
                                <div class="flex flex-wrap items-start gap-2">
                                    <input
                                        id="code"
                                        v-model="verifyForm.code"
                                        type="text"
                                        inputmode="numeric"
                                        dir="ltr"
                                        autocomplete="one-time-code"
                                        :maxlength="mobileVerification.codeLength"
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
                        <div>
                            <label for="phone" :class="labelClass">تلفن ثابت (اختیاری)</label>
                            <input id="phone" v-model="form.phone" type="tel" dir="ltr" placeholder="02112345678" maxlength="11" :class="inputClass" />
                            <p v-if="form.errors.phone" :class="errorClass">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label for="email" :class="labelClass">ایمیل</label>
                            <input id="email" v-model="form.email" type="email" dir="ltr" maxlength="100" :class="inputClass" />
                            <p v-if="form.errors.email" :class="errorClass">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label for="postal_code" :class="labelClass">کد پستی</label>
                            <input id="postal_code" v-model="form.postal_code" type="text" inputmode="numeric" dir="ltr" maxlength="10" :class="inputClass" />
                            <p v-if="form.errors.postal_code" :class="errorClass">{{ form.errors.postal_code }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address" :class="labelClass">نشانی</label>
                            <textarea id="address" v-model="form.address" rows="2" maxlength="300" :class="inputClass" />
                            <p v-if="form.errors.address" :class="errorClass">{{ form.errors.address }}</p>
                        </div>
                    </div>
                </section>

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">سوابق تحصیلی</h2>
                    <p v-if="form.errors.education" :class="errorClass">{{ form.errors.education }}</p>
                    <div class="mt-4 space-y-4">
                        <div v-for="(row, index) in form.education" :key="index" :class="rowClass">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label :for="`education-${index}-level`" :class="labelClass">مقطع</label>
                                    <select :id="`education-${index}-level`" v-model="row.level" :class="inputClass">
                                        <option value="" disabled>انتخاب کنید</option>
                                        <option v-for="option in options.educationLevels" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <p v-if="form.errors[`education.${index}.level`]" :class="errorClass">{{ form.errors[`education.${index}.level`] }}</p>
                                </div>
                                <div>
                                    <label :for="`education-${index}-major`" :class="labelClass">رشته</label>
                                    <input :id="`education-${index}-major`" v-model="row.major" type="text" maxlength="100" :class="inputClass" />
                                    <p v-if="form.errors[`education.${index}.major`]" :class="errorClass">{{ form.errors[`education.${index}.major`] }}</p>
                                </div>
                                <div>
                                    <label :for="`education-${index}-orientation`" :class="labelClass">گرایش (اختیاری)</label>
                                    <input :id="`education-${index}-orientation`" v-model="row.orientation" type="text" maxlength="100" :class="inputClass" />
                                    <p v-if="form.errors[`education.${index}.orientation`]" :class="errorClass">{{ form.errors[`education.${index}.orientation`] }}</p>
                                </div>
                                <div>
                                    <label :for="`education-${index}-average`" :class="labelClass">معدل</label>
                                    <input :id="`education-${index}-average`" v-model="row.average" type="text" inputmode="decimal" dir="ltr" placeholder="17.25" :class="inputClass" />
                                    <p v-if="form.errors[`education.${index}.average`]" :class="errorClass">{{ form.errors[`education.${index}.average`] }}</p>
                                </div>
                                <div>
                                    <label :for="`education-${index}-rank`" :class="labelClass">رتبه کنکور (اختیاری)</label>
                                    <input :id="`education-${index}-rank`" v-model="row.rank" type="text" inputmode="numeric" dir="ltr" :class="inputClass" />
                                    <p v-if="form.errors[`education.${index}.rank`]" :class="errorClass">{{ form.errors[`education.${index}.rank`] }}</p>
                                </div>
                                <div>
                                    <label :for="`education-${index}-graduation_date`" :class="labelClass">تاریخ اخذ مدرک</label>
                                    <JalaliDateInput :id="`education-${index}-graduation_date`" v-model="row.graduation_date" placeholder="1395/04/31" :input-class="inputClass" />
                                    <p v-if="form.errors[`education.${index}.graduation_date`]" :class="errorClass">{{ form.errors[`education.${index}.graduation_date`] }}</p>
                                </div>
                                <template v-if="universityLevels.includes(row.level)">
                                    <div>
                                        <label :for="`education-${index}-university_type`" :class="labelClass">نوع دانشگاه</label>
                                        <select :id="`education-${index}-university_type`" v-model="row.university_type" :class="inputClass">
                                            <option value="" disabled>انتخاب کنید</option>
                                            <option v-for="option in options.universityTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                        <p v-if="form.errors[`education.${index}.university_type`]" :class="errorClass">{{ form.errors[`education.${index}.university_type`] }}</p>
                                    </div>
                                    <div>
                                        <label :for="`education-${index}-university`" :class="labelClass">نام دانشگاه</label>
                                        <input :id="`education-${index}-university`" v-model="row.university" type="text" maxlength="150" :class="inputClass" />
                                        <p v-if="form.errors[`education.${index}.university`]" :class="errorClass">{{ form.errors[`education.${index}.university`] }}</p>
                                    </div>
                                </template>
                            </div>
                            <button v-if="form.education.length > 1" type="button" class="mt-3 text-sm text-red-600 hover:text-red-800" @click="form.education.splice(index, 1)">
                                حذف این مدرک
                            </button>
                        </div>
                    </div>
                    <button
                        v-if="form.education.length < limits.educationRows"
                        type="button"
                        :class="[linkButtonClass, 'mt-4']"
                        @click="form.education.push(emptyEducation())"
                    >
                        + افزودن مدرک دیگر
                    </button>
                </section>

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">سوابق کاری (اختیاری)</h2>
                    <p v-if="!form.work_history.length" class="mt-2 text-sm text-gray-500">اگر سابقه‌ی کار دارید، اضافه کنید.</p>
                    <p v-if="form.errors.work_history" :class="errorClass">{{ form.errors.work_history }}</p>
                    <div class="mt-4 space-y-4">
                        <div v-for="(row, index) in form.work_history" :key="index" :class="rowClass">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label :for="`work-${index}-company_name`" :class="labelClass">محل کار</label>
                                    <input :id="`work-${index}-company_name`" v-model="row.company_name" type="text" maxlength="150" :class="inputClass" />
                                    <p v-if="form.errors[`work_history.${index}.company_name`]" :class="errorClass">{{ form.errors[`work_history.${index}.company_name`] }}</p>
                                </div>
                                <div>
                                    <label :for="`work-${index}-job_position`" :class="labelClass">سمت</label>
                                    <input :id="`work-${index}-job_position`" v-model="row.job_position" type="text" maxlength="150" :class="inputClass" />
                                    <p v-if="form.errors[`work_history.${index}.job_position`]" :class="errorClass">{{ form.errors[`work_history.${index}.job_position`] }}</p>
                                </div>
                                <div>
                                    <label :for="`work-${index}-start_date`" :class="labelClass">تاریخ شروع همکاری</label>
                                    <JalaliDateInput :id="`work-${index}-start_date`" v-model="row.start_date" placeholder="1398/07/01" :input-class="inputClass" />
                                    <p v-if="form.errors[`work_history.${index}.start_date`]" :class="errorClass">{{ form.errors[`work_history.${index}.start_date`] }}</p>
                                </div>
                                <div>
                                    <label :for="`work-${index}-end_date`" :class="labelClass">تاریخ پایان همکاری (اگر هنوز آنجا کار می‌کنید خالی بگذارید)</label>
                                    <JalaliDateInput :id="`work-${index}-end_date`" v-model="row.end_date" placeholder="1402/06/31" :input-class="inputClass" />
                                    <p v-if="form.errors[`work_history.${index}.end_date`]" :class="errorClass">{{ form.errors[`work_history.${index}.end_date`] }}</p>
                                </div>
                                <div>
                                    <label :for="`work-${index}-company_phone`" :class="labelClass">تلفن محل کار (اختیاری)</label>
                                    <input :id="`work-${index}-company_phone`" v-model="row.company_phone" type="tel" dir="ltr" maxlength="20" :class="inputClass" />
                                    <p v-if="form.errors[`work_history.${index}.company_phone`]" :class="errorClass">{{ form.errors[`work_history.${index}.company_phone`] }}</p>
                                </div>
                                <div>
                                    <label :for="`work-${index}-termination_reason`" :class="labelClass">علت پایان همکاری (اختیاری)</label>
                                    <input :id="`work-${index}-termination_reason`" v-model="row.termination_reason" type="text" maxlength="300" :class="inputClass" />
                                    <p v-if="form.errors[`work_history.${index}.termination_reason`]" :class="errorClass">{{ form.errors[`work_history.${index}.termination_reason`] }}</p>
                                </div>
                            </div>
                            <button type="button" class="mt-3 text-sm text-red-600 hover:text-red-800" @click="form.work_history.splice(index, 1)">
                                حذف این سابقه
                            </button>
                        </div>
                    </div>
                    <button
                        v-if="form.work_history.length < limits.workRows"
                        type="button"
                        :class="[linkButtonClass, 'mt-4']"
                        @click="form.work_history.push(emptyWork())"
                    >
                        + افزودن سابقه‌ی کاری
                    </button>
                </section>

                <section :class="sectionClass">
                    <h2 class="font-bold text-gray-900">رزومه</h2>
                    <div class="mt-4">
                        <label for="resume" :class="labelClass">فایل رزومه (PDF، حداکثر {{ limits.resumeMegabytes }} مگابایت)</label>
                        <input id="resume" type="file" accept="application/pdf,.pdf" :class="inputClass" @change="onResumePicked" />
                        <p v-if="form.errors.resume" :class="errorClass">{{ form.errors.resume }}</p>
                    </div>
                </section>

                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-violet-600 px-6 py-2.5 font-medium text-white hover:bg-violet-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'در حال ارسال…' : 'ثبت درخواست' }}
                    </button>
                    <span v-if="form.progress" class="text-sm text-gray-500">{{ form.progress.percentage }}٪</span>
                    <p v-if="form.hasErrors" class="text-sm text-red-600">لطفاً خطاهای فرم را برطرف کنید.</p>
                </div>
            </form>
        </div>
    </PublicLayout>
</template>
