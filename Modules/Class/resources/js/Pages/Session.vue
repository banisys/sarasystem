<script setup>
import UiButton from '@/Components/Ui/UiButton.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FilePicker from '@/Components/FilePicker.vue';
import JalaliDateInput from '@/Components/JalaliDateInput.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// One session in «کلاس‌های من» (ADR 0026): details, what it covered,
// homework and materials, one's own attendance, and — for who may — the
// attendance, content and grading forms, each in its own tab.
const props = defineProps({
    session: { type: Object, required: true },
    role: { type: Object, required: true },
    // A guardian looking at a ward's session (ADR 0027).
    ward: { type: Object, default: null },
    myAttendance: { type: Object, default: null },
    statuses: { type: Array, required: true },
    attendance: { type: Array, default: null },
    contentForm: { type: Object, default: null },
    // Homework (ADR 0030): the person's hand-in, whether they may (still)
    // hand it in, and — for who grades — every attendee's.
    homeworkMaxScore: { type: Number, default: 20 },
    mySubmission: { type: Object, default: null },
    canSubmit: { type: Boolean, default: false },
    submissions: { type: Array, default: null },
});

const flash = computed(() => usePage().props.flash.status);
const fieldBase = 'ui-field mt-0';
const field = fieldBase;
const primaryButton = 'ui-button border-transparent bg-brand-600 text-brand-contrast hover:bg-brand-action-hover';
// Stay on the tab and keep what was typed after a save.
const keep = { preserveScroll: true, preserveState: true };

const statusBadge = {
    scheduled: 'bg-violet-50 text-violet-700 ring-violet-200',
    held: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
};

// ── Tabs
const tabs = computed(() => [
    { key: 'details', label: 'جزئیات جلسه', icon: 'document', badge: null },
    ...(props.attendance ? [{ key: 'attendance', label: 'حضور و غیاب', icon: 'users', badge: props.attendance.length }] : []),
    { key: 'content', label: 'مطالب و تکلیف', icon: 'book', badge: null },
    ...(props.submissions ? [{ key: 'submissions', label: 'تکالیف دریافتی', icon: 'clipboard', badge: `${handedIn.value}/${props.submissions.length}` }] : []),
]);
const tab = ref('details');

// ── Attendance
const attendanceForm = useForm({ rows: (props.attendance ?? []).map((row) => ({ ...row })) });
const statusTone = {
    present: { on: 'bg-emerald-600 text-white', chip: 'bg-emerald-50 text-emerald-700' },
    late: { on: 'bg-amber-500 text-white', chip: 'bg-amber-50 text-amber-700' },
    absent: { on: 'bg-red-600 text-white', chip: 'bg-red-50 text-red-700' },
    excused: { on: 'bg-gray-600 text-white', chip: 'bg-gray-100 text-gray-700' },
};
const attendanceCounts = computed(() => props.statuses.map((status) => ({
    ...status,
    count: attendanceForm.rows.filter((row) => row.status === status.value).length,
})));

function markAll(status) {
    attendanceForm.rows.forEach((row) => {
        row.status = status;
    });
}

function saveAttendance() {
    attendanceForm.post(route('my-classes.attendance', props.session.id), keep);
}

// ── Content
const content = useForm({
    covered_topic_ids: props.contentForm?.covered_topic_ids ?? [],
    summary: props.contentForm?.summary ?? '',
    homework: props.contentForm?.homework ?? '',
    homework_due_on: props.contentForm?.homework_due_on ?? '',
    homework_max_score: props.contentForm?.homework_max_score ?? 20,
    remove_media_ids: [],
    files: [],
});
// Topics grouped by plan; those finished in other sessions folded away.
const topicGroups = computed(() => {
    const groups = new Map();
    (props.contentForm?.topics ?? []).forEach((topic) => {
        const name = topic.plan ?? 'طرح درس';
        if (!groups.has(name)) {
            groups.set(name, { name, open: [], done: [] });
        }
        groups.get(name)[topic.done_elsewhere ? 'done' : 'open'].push(topic);
    });

    return [...groups.values()];
});
const showDone = ref({});

function toggleTopic(id) {
    const ids = content.covered_topic_ids;
    content.covered_topic_ids = ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
}

function toggleRemoval(id) {
    const ids = content.remove_media_ids;
    content.remove_media_ids = ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
}

const fileError = (form) => Object.entries(form.errors).find(([key]) => key.startsWith('files'))?.[1] ?? '';

function saveContent() {
    content.post(route('my-classes.content', props.session.id), {
        ...keep,
        forceFormData: true,
        onSuccess: () => content.reset('files', 'remove_media_ids'),
    });
}

// ── Own hand-in
const handIn = useForm({ answer: props.mySubmission?.answer ?? '', files: [], remove_media_ids: [] });

function toggleHandInRemoval(id) {
    const ids = handIn.remove_media_ids;
    handIn.remove_media_ids = ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
}

function submitHomework() {
    handIn.post(route('my-classes.homework', props.session.id), {
        ...keep,
        forceFormData: true,
        onSuccess: () => handIn.reset('files', 'remove_media_ids'),
    });
}

// ── Grading
const grading = useForm({
    grades: (props.submissions ?? [])
        .filter((row) => row.submission)
        .map((row) => ({ id: row.submission.id, score: row.submission.score ?? '', feedback: row.submission.feedback ?? '' })),
});
const gradeOf = (submission) => grading.grades.find((grade) => grade.id === submission.id);
const handedIn = computed(() => (props.submissions ?? []).filter((row) => row.submission).length);

function saveGrades() {
    grading.post(route('my-classes.grades', props.session.id), keep);
}

const initial = (name) => name?.trim()?.charAt(0) ?? '?';
</script>

<template>
    <Head :title="session.class" />

    <AppLayout>
        <div class="space-y-5">
            <UiPageHeader
                :title="session.class"
                :description="session.subject"
                icon="calendar"
                tone="plum"
                :back-href="route('my-classes.index', ward ? { for: ward.id } : {})"
                :back-label="ward ? `کلاس‌های ${ward.name}` : 'کلاس‌های من'"
            />
            <UiNotice v-if="flash" tone="success">{{ flash }}</UiNotice>

            <nav class="ui-surface flex flex-wrap gap-1 p-2" aria-label="بخش‌های جلسه">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    :aria-pressed="tab === item.key"
                    class="ui-button border-transparent"
                    :class="tab === item.key ? 'bg-violet-600 font-medium text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
                    @click="tab = item.key"
                >
                    <PanelIcon :name="item.icon" class="size-5" />
                    {{ item.label }}
                    <span
                        v-if="item.badge !== null"
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="tab === item.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'"
                    >{{ item.badge }}</span>
                </button>
            </nav>

            <section v-if="tab === 'details'" class="ui-surface overflow-hidden" aria-label="جزئیات جلسه">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ui-line px-4 py-3">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span v-if="session.kind_label !== 'عادی'" class="rounded-full bg-sky-50 px-3 py-1 font-medium text-sky-700 ring-1 ring-sky-200">{{ session.kind_label }}</span>
                        <span class="rounded-full px-3 py-1 font-medium ring-1" :class="statusBadge[session.status]">{{ session.status_label }}</span>
                    </div>
                    <UiButton v-if="session.exams_url" :href="session.exams_url" variant="outline-brand" class="border-2">
                        <PanelIcon name="clipboard" class="size-5" />
                        آزمون‌های جلسه
                    </UiButton>
                </div>

                <dl class="grid gap-px bg-gray-100 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr]">
                    <div class="flex items-start gap-3 bg-white p-4">
                        <PanelIcon name="clock" class="mt-0.5 size-5 shrink-0 text-violet-600" />
                        <div class="min-w-0">
                            <dt class="text-xs text-gray-500">زمان</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ session.when }}</dd>
                            <dd v-if="session.moved_from" class="text-xs text-amber-700">جابه‌جا از {{ session.moved_from }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 bg-white p-4">
                        <PanelIcon name="user" class="mt-0.5 size-5 shrink-0 text-violet-600" />
                        <div class="min-w-0">
                            <dt class="text-xs text-gray-500">مدرس</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ session.teacher || '—' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 bg-white p-4">
                        <PanelIcon name="pin" class="mt-0.5 size-5 shrink-0 text-violet-600" />
                        <div class="min-w-0">
                            <dt class="text-xs text-gray-500">مکان</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ session.location || (session.online_platform ? `آنلاین (${session.online_platform})` : '—') }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 bg-white p-4">
                        <PanelIcon name="academic" class="mt-0.5 size-5 shrink-0 text-violet-600" />
                        <div class="min-w-0">
                            <dt class="text-xs text-gray-500">{{ ward ? `نقش ${ward.name}` : 'نقش شما' }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ role.label }}</dd>
                        </div>
                    </div>
                </dl>

                <div
                    v-if="session.cancel_reason || myAttendance || session.join_url"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4"
                >
                    <div class="space-y-1 text-sm">
                        <p v-if="session.cancel_reason" class="text-red-700">دلیل لغو: {{ session.cancel_reason }}</p>
                        <p v-if="myAttendance" class="text-gray-800">
                            {{ ward ? `حضور ${ward.name}` : 'حضور شما' }}:
                            <strong>{{ myAttendance.status }}</strong>
                            <span v-if="myAttendance.late_minutes"> ({{ myAttendance.late_minutes }} دقیقه)</span>
                            <span v-if="myAttendance.note" class="text-gray-600"> · {{ myAttendance.note }}</span>
                        </p>
                    </div>
                    <a
                        v-if="session.join_url"
                        :href="session.join_url"
                        target="_blank"
                        rel="noopener"
                        :class="primaryButton"
                    >
                        <PanelIcon name="video" class="size-5" />
                        ورود به کلاس آنلاین
                    </a>
                </div>
            </section>

            <!-- Attendance -->
            <form v-if="attendance && tab === 'attendance'" class="ui-surface" @submit.prevent="saveAttendance">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span v-for="status in attendanceCounts" :key="status.value" class="rounded-full px-3 py-1 font-medium" :class="statusTone[status.value].chip">
                            {{ status.label }}: {{ status.count }}
                        </span>
                    </div>
                    <button v-if="attendance.length" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50" @click="markAll('present')">
                        <PanelIcon name="check" class="size-4 text-emerald-600" />
                        همه حاضر
                    </button>
                </div>

                <p v-if="!attendance.length" class="p-6 text-center text-sm text-gray-500">شرکت‌کننده‌ای در این جلسه نیست.</p>

                <ul class="divide-y divide-gray-100">
                    <li v-for="(row, index) in attendanceForm.rows" :key="row.user_id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                        <div class="flex min-w-44 flex-1 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700">{{ initial(row.name) }}</span>
                            <span class="font-medium text-gray-900">{{ row.name }}</span>
                        </div>
                        <div class="flex max-w-full flex-wrap gap-1 rounded-ui border border-ui-line p-1" role="group" :aria-label="`حضور ${row.name}`">
                            <button
                                v-for="status in statuses"
                                :key="status.value"
                                type="button"
                                :aria-pressed="row.status === status.value"
                                class="ui-button border-transparent px-3"
                                :class="row.status === status.value ? statusTone[status.value].on : 'bg-white text-gray-600 hover:bg-gray-50'"
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
                            placeholder="چند دقیقه؟"
                            :aria-label="`دقیقهٔ تأخیر ${row.name}`"
                            :class="[fieldBase, 'max-w-28']"
                        />
                        <input v-model="row.note" type="text" maxlength="255" placeholder="توضیح (اختیاری)" :aria-label="`توضیح حضور ${row.name}`" :class="[fieldBase, 'min-w-40 flex-1 basis-40']" />
                        <p v-if="attendanceForm.errors[`rows.${index}.late_minutes`]" class="w-full text-xs text-red-600">{{ attendanceForm.errors[`rows.${index}.late_minutes`] }}</p>
                    </li>
                </ul>

                <div v-if="attendance.length" class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-b-2xl border-t border-gray-100 bg-white/95 p-4 backdrop-blur">
                    <p class="text-sm text-red-600">{{ attendanceForm.errors.form }}</p>
                    <button type="submit" :disabled="attendanceForm.processing" :class="primaryButton">
                        <PanelIcon name="check" class="size-5" />
                        ثبت حضور و غیاب
                    </button>
                </div>
            </form>

            <!-- Content: the form for who may record it… -->
            <form v-if="contentForm && tab === 'content'" class="space-y-6 ui-surface p-4 sm:p-5" @submit.prevent="saveContent">
                <div v-if="!session.content && session.planned_topics.length" class="rounded-xl bg-violet-50 px-4 py-3 text-sm text-violet-900">
                    <span class="font-medium">طبق طرح درس، این جلسه:</span> {{ session.planned_topics.join('، ') }}
                </div>

                <fieldset v-if="topicGroups.length" class="space-y-3">
                    <legend class="mb-1 font-semibold text-gray-900">سرفصل‌هایی که تمام شد</legend>
                    <p class="-mt-2 text-xs text-gray-500">سرفصل تمام‌نشده را انتخاب نکنید تا به جلسه‌ی بعد برود.</p>
                    <div v-for="group in topicGroups" :key="group.name" class="space-y-2">
                        <div v-if="topicGroups.length > 1" class="text-xs font-medium text-gray-500" dir="auto">{{ group.name }}</div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="topic in group.open"
                                :key="topic.id"
                                type="button"
                                :aria-pressed="content.covered_topic_ids.includes(topic.id)"
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm ring-1 transition"
                                :class="content.covered_topic_ids.includes(topic.id) ? 'bg-violet-600 text-white ring-violet-600' : 'bg-white text-gray-700 ring-gray-300 hover:ring-violet-400'"
                                @click="toggleTopic(topic.id)"
                            >
                                <PanelIcon v-if="content.covered_topic_ids.includes(topic.id)" name="check" class="size-4" />
                                <span dir="auto">{{ topic.title }}</span>
                            </button>
                            <button
                                v-if="group.done.length"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-100"
                                @click="showDone[group.name] = !showDone[group.name]"
                            >
                                {{ showDone[group.name] ? 'پنهان کردن تمام‌شده‌ها' : `${group.done.length} سرفصل تمام‌شده در جلسه‌های دیگر` }}
                            </button>
                        </div>
                        <div v-if="showDone[group.name]" class="flex flex-wrap gap-2">
                            <span v-for="topic in group.done" :key="topic.id" class="rounded-full bg-gray-100 px-3 py-1.5 text-sm text-gray-400 line-through" dir="auto">{{ topic.title }}</span>
                        </div>
                    </div>
                </fieldset>

                <label class="block space-y-1.5">
                    <span class="font-semibold text-gray-900">شرح مطالب</span>
                    <textarea v-model="content.summary" rows="4" placeholder="در این جلسه چه گفته شد؟" :class="field" />
                    <p v-if="content.errors.summary" class="text-xs text-red-600">{{ content.errors.summary }}</p>
                </label>

                <div class="space-y-3 rounded-xl bg-amber-50/60 p-4 ring-1 ring-amber-100">
                    <label class="block space-y-1.5">
                        <span class="font-semibold text-gray-900">تکلیف</span>
                        <textarea v-model="content.homework" rows="3" placeholder="اگر تکلیفی دارد، اینجا بنویسید." :class="field" />
                    </label>
                    <div v-if="content.homework" class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5 text-sm">
                            <label for="homework_due_on" class="text-gray-700">مهلت تحویل</label>
                            <JalaliDateInput id="homework_due_on" v-model="content.homework_due_on" :input-class="field" />
                            <p v-if="content.errors.homework_due_on" class="text-xs text-red-600">{{ content.errors.homework_due_on }}</p>
                        </div>
                        <label class="block space-y-1.5 text-sm">
                            <span class="text-gray-700">نمره‌ی تکلیف از</span>
                            <input v-model="content.homework_max_score" type="number" min="1" max="100" :class="field" />
                        </label>
                    </div>
                </div>

                <div class="space-y-2">
                    <span class="font-semibold text-gray-900">فایل‌های جلسه</span>
                    <ul v-if="contentForm.materials.length" class="space-y-1">
                        <li
                            v-for="file in contentForm.materials"
                            :key="file.id"
                            class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm ring-1"
                            :class="content.remove_media_ids.includes(file.id) ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-gray-50 text-gray-800 ring-gray-200'"
                        >
                            <span class="flex min-w-0 items-center gap-2" :class="{ 'line-through': content.remove_media_ids.includes(file.id) }">
                                <PanelIcon name="document" class="size-4 shrink-0" />
                                <span class="truncate" dir="auto">{{ file.name }}</span>
                            </span>
                            <button type="button" class="shrink-0 text-xs hover:underline" @click="toggleRemoval(file.id)">
                                {{ content.remove_media_ids.includes(file.id) ? 'نگه‌داشتن' : 'حذف' }}
                            </button>
                        </li>
                    </ul>
                    <FilePicker v-model="content.files" hint="جزوه، تمرین، اسلاید؛ هر فایل تا ۲۰ مگابایت" :error="fileError(content)" />
                    <progress v-if="content.progress" :value="content.progress.percentage" max="100" class="w-full" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                    <p class="text-sm text-red-600">{{ content.errors.form }}</p>
                    <button type="submit" :disabled="content.processing" :class="primaryButton">
                        <PanelIcon name="check" class="size-5" />
                        ثبت مطالب
                    </button>
                </div>
            </form>

            <!-- …or what was recorded, to read. -->
            <section v-if="!contentForm && tab === 'content'" class="space-y-4 ui-surface p-4 sm:p-5">
                <h2 class="flex items-center gap-2 font-semibold text-gray-900">
                    <PanelIcon name="book" class="size-5 text-violet-600" />
                    مطالب جلسه
                </h2>
                <template v-if="session.content">
                    <div v-if="session.content.topics.length" class="flex flex-wrap gap-2">
                        <span v-for="topic in session.content.topics" :key="topic" class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-3 py-1 text-sm text-violet-800">
                            <PanelIcon name="check" class="size-4" />
                            <span dir="auto">{{ topic }}</span>
                        </span>
                    </div>
                    <p v-if="session.content.summary" class="whitespace-pre-line wrap-break-word text-sm leading-7 text-gray-700">{{ session.content.summary }}</p>
                </template>
                <p v-else-if="session.planned_topics.length" class="text-sm text-gray-600">طبق طرح درس: {{ session.planned_topics.join('، ') }}</p>
                <p v-else class="text-sm text-gray-500">هنوز ثبت نشده است.</p>

                <div v-if="session.homework" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-100">
                    <div class="flex flex-wrap items-center justify-between gap-2 font-semibold">
                        تکلیف
                        <span v-if="session.homework_due" class="rounded-full bg-white/70 px-2.5 py-0.5 text-xs font-normal">مهلت: {{ session.homework_due }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-line wrap-break-word leading-7">{{ session.homework }}</p>
                </div>

                <div v-if="session.materials.length" class="flex flex-wrap gap-2">
                    <a
                        v-for="file in session.materials"
                        :key="file.url"
                        :href="file.url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-2 text-sm text-violet-700 ring-1 ring-gray-200 hover:bg-violet-50"
                    >
                        <PanelIcon name="document" class="size-4" />
                        <span dir="auto">{{ file.name }}</span>
                    </a>
                </div>
            </section>

            <!-- The person's own hand-in, or a ward's (read only). -->
            <section v-if="tab === 'content' && session.homework && (mySubmission || canSubmit)" class="space-y-4 ui-surface p-4 sm:p-5">
                <h2 class="flex items-center gap-2 font-semibold text-gray-900">
                    <PanelIcon name="pencil" class="size-5 text-violet-600" />
                    {{ ward ? `تکلیف ${ward.name}` : 'تکلیف شما' }}
                </h2>
                <div v-if="mySubmission" class="space-y-2 text-sm text-gray-700">
                    <p>
                        تحویل: {{ mySubmission.submitted_at }}
                        <span v-if="mySubmission.is_late" class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">با تأخیر</span>
                    </p>
                    <div v-if="mySubmission.is_graded" class="rounded-xl bg-emerald-50 p-3 text-emerald-900 ring-1 ring-emerald-100">
                        <p v-if="mySubmission.score !== null">نمره: <strong class="text-lg">{{ mySubmission.score }}</strong> از {{ homeworkMaxScore }}</p>
                        <p v-if="mySubmission.feedback" class="mt-1 whitespace-pre-line wrap-break-word">بازخورد مدرس: {{ mySubmission.feedback }}</p>
                    </div>
                    <p v-if="!canSubmit && mySubmission.answer" class="whitespace-pre-line wrap-break-word rounded-lg bg-gray-50 p-3">{{ mySubmission.answer }}</p>
                    <div v-if="!canSubmit && mySubmission.files.length" class="flex flex-wrap gap-2">
                        <a v-for="file in mySubmission.files" :key="file.id" :href="file.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-2 text-violet-700 ring-1 ring-gray-200 hover:bg-violet-50">
                            <PanelIcon name="document" class="size-4" />
                            <span dir="auto">{{ file.name }}</span>
                        </a>
                    </div>
                </div>

                <form v-if="canSubmit" class="space-y-4" @submit.prevent="submitHomework">
                    <label class="block space-y-1.5 text-sm">
                        <span class="font-medium text-gray-800">پاسخ</span>
                        <textarea v-model="handIn.answer" rows="5" placeholder="پاسخ خود را بنویسید…" :class="field" />
                        <p v-if="handIn.errors.answer" class="text-xs text-red-600">{{ handIn.errors.answer }}</p>
                    </label>
                    <ul v-if="mySubmission?.files.length" class="space-y-1">
                        <li
                            v-for="file in mySubmission.files"
                            :key="file.id"
                            class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm ring-1"
                            :class="handIn.remove_media_ids.includes(file.id) ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-gray-50 text-gray-800 ring-gray-200'"
                        >
                            <a :href="file.url" target="_blank" rel="noopener" class="truncate hover:underline" :class="{ 'line-through': handIn.remove_media_ids.includes(file.id) }" dir="auto">{{ file.name }}</a>
                            <button type="button" class="shrink-0 text-xs hover:underline" @click="toggleHandInRemoval(file.id)">
                                {{ handIn.remove_media_ids.includes(file.id) ? 'نگه‌داشتن' : 'حذف' }}
                            </button>
                        </li>
                    </ul>
                    <FilePicker v-model="handIn.files" hint="تا ۵ فایل، هر کدام تا ۲۰ مگابایت" :error="fileError(handIn)" />
                    <progress v-if="handIn.progress" :value="handIn.progress.percentage" max="100" class="w-full" />
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-red-600">{{ handIn.errors.form }}</p>
                        <button type="submit" :disabled="handIn.processing" :class="primaryButton">
                            {{ mySubmission ? 'به‌روزرسانی تکلیف' : 'تحویل تکلیف' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Grading: who may (HomeworkBook::canGrade), at any time. -->
            <form v-if="submissions && tab === 'submissions'" class="ui-surface" @submit.prevent="saveGrades">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 p-4 text-sm">
                    <span class="text-gray-700">تحویل داده‌اند: <strong>{{ handedIn }}</strong> از {{ submissions.length }}</span>
                    <span class="text-gray-500">نمره از {{ homeworkMaxScore }}</span>
                </div>
                <p v-if="!submissions.length" class="p-6 text-center text-sm text-gray-500">شرکت‌کننده‌ای در این جلسه نیست.</p>
                <ul class="divide-y divide-gray-100">
                    <li v-for="row in submissions" :key="row.name" class="space-y-3 p-4 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-violet-100 font-bold text-violet-700">{{ initial(row.name) }}</span>
                                <span class="font-medium text-gray-900">{{ row.name }}</span>
                            </div>
                            <span v-if="!row.submission" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-500">تحویل نداده</span>
                            <span v-else class="text-xs text-gray-500">
                                تحویل: {{ row.submission.submitted_at }}
                                <span v-if="row.submission.is_late" class="rounded-full bg-red-50 px-2 py-0.5 text-red-700">با تأخیر</span>
                            </span>
                        </div>
                        <template v-if="row.submission">
                            <p v-if="row.submission.answer" class="whitespace-pre-line wrap-break-word rounded-lg bg-gray-50 p-3 leading-7 text-gray-800">{{ row.submission.answer }}</p>
                            <div v-if="row.submission.files.length" class="flex flex-wrap gap-2">
                                <a v-for="file in row.submission.files" :key="file.id" :href="file.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-1.5 text-violet-700 ring-1 ring-gray-200 hover:bg-violet-50">
                                    <PanelIcon name="document" class="size-4" />
                                    <span dir="auto">{{ file.name }}</span>
                                </a>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <input v-model="gradeOf(row.submission).score" type="number" min="0" :max="homeworkMaxScore" step="0.25" placeholder="نمره" :aria-label="`نمرهٔ ${row.name}`" :class="[fieldBase, 'max-w-28']" />
                                <input v-model="gradeOf(row.submission).feedback" type="text" maxlength="1000" placeholder="بازخورد (اختیاری)" :aria-label="`بازخورد برای ${row.name}`" :class="[fieldBase, 'min-w-48 flex-1']" />
                            </div>
                        </template>
                    </li>
                </ul>
                <div v-if="grading.grades.length || grading.errors.form" class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-b-2xl border-t border-gray-100 bg-white/95 p-4 backdrop-blur">
                    <p class="text-sm text-red-600">{{ grading.errors.form }}</p>
                    <button v-if="grading.grades.length" type="submit" :disabled="grading.processing" :class="primaryButton">
                        <PanelIcon name="check" class="size-5" />
                        ذخیره‌ی نمره‌ها
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
