<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import JalaliDateInput from '@/Components/JalaliDateInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// One session in «کلاس‌های من» (ADR 0026): details, what it covered,
// homework and materials, one's own attendance, and — for who may — the
// attendance and content forms.
const props = defineProps({
    session: { type: Object, required: true },
    role: { type: Object, required: true },
    myAttendance: { type: Object, default: null },
    statuses: { type: Array, required: true },
    attendance: { type: Array, default: null },
    contentForm: { type: Object, default: null },
});

const flash = computed(() => usePage().props.flash.status);

const attendanceForm = useForm({ rows: (props.attendance ?? []).map((row) => ({ ...row })) });
const content = useForm({
    covered_topic_ids: props.contentForm?.covered_topic_ids ?? [],
    summary: props.contentForm?.summary ?? '',
    homework: props.contentForm?.homework ?? '',
    homework_due_on: props.contentForm?.homework_due_on ?? '',
    remove_media_ids: [],
    files: [],
});

const statusColor = {
    present: 'bg-emerald-600 text-white',
    late: 'bg-amber-500 text-white',
    absent: 'bg-red-600 text-white',
    excused: 'bg-gray-500 text-white',
};

function saveAttendance() {
    attendanceForm.post(route('my-classes.attendance', props.session.id), { preserveScroll: true });
}

function saveContent() {
    content.post(route('my-classes.content', props.session.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => content.reset('files', 'remove_media_ids'),
    });
}
</script>

<template>
    <Head :title="session.class" />

    <AppLayout>
        <div class="space-y-4">
            <Link :href="route('my-classes.index')" class="text-sm text-violet-700 hover:underline">‹ کلاس‌های من</Link>

            <p v-if="flash" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ flash }}</p>

            <section class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h1 class="text-xl font-bold text-gray-900">{{ session.class }}<span v-if="session.subject" class="font-normal text-gray-600"> · {{ session.subject }}</span></h1>
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-xs text-violet-700">{{ session.status_label }}</span>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-700">
                    <span>{{ session.when }}</span>
                    <span v-if="session.moved_from" class="text-amber-700">جابه‌جا از {{ session.moved_from }}</span>
                    <span v-if="session.teacher">مدرس: {{ session.teacher }}</span>
                    <span v-if="session.location">مکان: {{ session.location }}</span>
                    <span v-if="session.kind_label !== 'عادی'">{{ session.kind_label }}</span>
                    <span>نقش شما: {{ role.label }}</span>
                </div>
                <p v-if="session.cancel_reason" class="mt-2 text-sm text-red-700">دلیل لغو: {{ session.cancel_reason }}</p>
                <a
                    v-if="session.meeting_url && session.status !== 'cancelled'"
                    :href="session.meeting_url"
                    target="_blank"
                    rel="noopener"
                    class="mt-3 inline-block rounded-lg bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700"
                >
                    ورود به کلاس آنلاین
                </a>
                <p v-if="myAttendance" class="mt-3 text-sm text-gray-800">
                    حضور شما: <strong>{{ myAttendance.status }}</strong>
                    <span v-if="myAttendance.late_minutes"> ({{ myAttendance.late_minutes }} دقیقه)</span>
                    <span v-if="myAttendance.note"> · {{ myAttendance.note }}</span>
                </p>
            </section>

            <section class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900">مطالب جلسه</h2>
                <template v-if="session.content">
                    <ul v-if="session.content.topics.length" class="list-inside list-disc text-sm text-gray-800">
                        <li v-for="topic in session.content.topics" :key="topic">{{ topic }}</li>
                    </ul>
                    <p v-if="session.content.summary" class="whitespace-pre-line text-sm text-gray-700">{{ session.content.summary }}</p>
                </template>
                <p v-else-if="session.planned_topics.length" class="text-sm text-gray-600">برنامه: {{ session.planned_topics.join('، ') }}</p>
                <p v-else class="text-sm text-gray-500">هنوز ثبت نشده است.</p>

                <div v-if="session.homework" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    <div class="font-semibold">تکلیف<span v-if="session.homework_due" class="font-normal"> · مهلت: {{ session.homework_due }}</span></div>
                    <p class="mt-1 whitespace-pre-line">{{ session.homework }}</p>
                </div>

                <div v-if="session.materials.length" class="text-sm">
                    <div class="mb-1 font-semibold text-gray-800">فایل‌ها</div>
                    <a v-for="file in session.materials" :key="file.url" :href="file.url" target="_blank" rel="noopener" class="me-4 text-violet-700 hover:underline">{{ file.name }}</a>
                </div>
            </section>

            <!-- Taking attendance: who may, while it is open (AttendanceRegister). -->
            <form v-if="attendance" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm" @submit.prevent="saveAttendance">
                <h2 class="font-semibold text-gray-900">حضور و غیاب</h2>
                <p v-if="!attendance.length" class="text-sm text-gray-500">شرکت‌کننده‌ای در این جلسه نیست.</p>

                <div v-for="(row, index) in attendanceForm.rows" :key="row.user_id" class="flex flex-wrap items-center gap-2 border-b border-gray-100 pb-2 text-sm">
                    <span class="w-40 font-medium text-gray-900">{{ row.name }}</span>
                    <div class="flex overflow-hidden rounded-lg border border-gray-200">
                        <button
                            v-for="status in statuses"
                            :key="status.value"
                            type="button"
                            class="px-2.5 py-1"
                            :class="row.status === status.value ? statusColor[status.value] : 'bg-white text-gray-700 hover:bg-gray-50'"
                            @click="row.status = status.value"
                        >
                            {{ status.label }}
                        </button>
                    </div>
                    <input
                        v-if="row.status === 'late'"
                        v-model="row.late_minutes"
                        type="number"
                        min="1"
                        max="600"
                        placeholder="دقیقه"
                        class="w-20 rounded-lg border-gray-300 text-sm"
                    />
                    <input v-model="row.note" type="text" maxlength="255" placeholder="توضیح" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm" />
                    <p v-if="attendanceForm.errors[`rows.${index}.late_minutes`]" class="w-full text-xs text-red-600">{{ attendanceForm.errors[`rows.${index}.late_minutes`] }}</p>
                </div>

                <p v-if="attendanceForm.errors.form" class="text-sm text-red-600">{{ attendanceForm.errors.form }}</p>
                <button
                    v-if="attendance.length"
                    type="submit"
                    :disabled="attendanceForm.processing"
                    class="rounded-lg bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-50"
                >
                    ثبت حضور و غیاب
                </button>
            </form>

            <!-- Recording content: who may, while it is open (SessionContentBook). -->
            <form v-if="contentForm" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm" @submit.prevent="saveContent">
                <h2 class="font-semibold text-gray-900">ثبت مطالب جلسه</h2>

                <fieldset v-if="contentForm.topics.length" class="space-y-1 text-sm">
                    <legend class="mb-1 text-gray-700">سرفصل‌هایی که تمام شد <span class="text-xs text-gray-500">(سرفصل تمام‌نشده را علامت نزنید تا به جلسه‌ی بعد برود)</span></legend>
                    <label v-for="topic in contentForm.topics" :key="topic.id" class="flex items-center gap-2" :class="topic.done_elsewhere ? 'text-gray-400' : 'text-gray-800'">
                        <input v-model="content.covered_topic_ids" type="checkbox" :value="topic.id" :disabled="topic.done_elsewhere" class="rounded border-gray-300" />
                        {{ topic.title }}<span v-if="topic.done_elsewhere" class="text-xs">(در جلسه‌ی دیگری تمام شده)</span>
                    </label>
                </fieldset>

                <label class="block text-sm">
                    <span class="text-gray-700">شرح مطالب</span>
                    <textarea v-model="content.summary" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">تکلیف</span>
                    <textarea v-model="content.homework" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </label>
                <div v-if="content.homework" class="text-sm">
                    <label for="homework_due_on" class="text-gray-700">مهلت تکلیف</label>
                    <JalaliDateInput id="homework_due_on" v-model="content.homework_due_on" input-class="mt-1 w-48 rounded-lg border-gray-300 text-sm" />
                    <p v-if="content.errors.homework_due_on" class="text-xs text-red-600">{{ content.errors.homework_due_on }}</p>
                </div>

                <fieldset v-if="contentForm.materials.length" class="space-y-1 text-sm">
                    <legend class="mb-1 text-gray-700">حذف فایل‌ها</legend>
                    <label v-for="file in contentForm.materials" :key="file.id" class="flex items-center gap-2 text-gray-800">
                        <input v-model="content.remove_media_ids" type="checkbox" :value="file.id" class="rounded border-gray-300" />
                        {{ file.name }}
                    </label>
                </fieldset>
                <label class="block text-sm">
                    <span class="text-gray-700">فایل‌های جلسه (جزوه، تمرین؛ هر فایل تا ۲۰ مگابایت)</span>
                    <input type="file" multiple class="mt-1 block text-sm" @input="content.files = Array.from($event.target.files)" />
                    <p v-if="content.errors.files" class="text-xs text-red-600">{{ content.errors.files }}</p>
                </label>
                <progress v-if="content.progress" :value="content.progress.percentage" max="100" class="w-full" />

                <p v-if="content.errors.form" class="text-sm text-red-600">{{ content.errors.form }}</p>
                <button type="submit" :disabled="content.processing" class="rounded-lg bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-50">
                    ثبت مطالب
                </button>
            </form>
        </div>
    </AppLayout>
</template>
