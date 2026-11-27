<script setup>
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import { ElForm, ElInput, ElLink, ElProgress, ElTabPane, ElTabs, ElTag } from 'element-plus';
import 'element-plus/es/components/progress/style/css';
import 'element-plus/es/components/tabs/style/css';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import 'element-plus/es/components/tag/style/css';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import 'element-plus/es/components/link/style/css';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelPrintPreview from '@/Components/Ui/PanelPrintPreview.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, useTemplateRef, watch } from 'vue';
import FilePicker from '@/Components/FilePicker.vue';
import PanelJalaliDateInput from '@/Components/Ui/PanelJalaliDateInput.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ExamSeatingEditor from '../Components/ExamSeatingEditor.vue';
import SessionReminderTimeline from '../Components/SessionReminderTimeline.vue';
import SessionGallery from '../Components/SessionGallery.vue';
import SharedSessionLessons from '../Components/SharedSessionLessons.vue';

// One session in «تقویم آموزشی» (ADR 0026): details, what it covered,
// homework and materials, one's own attendance, and — for who may — the
// attendance, content and grading forms, each in its own tab.
const props = defineProps({
    session: { type: Object, required: true },
    role: { type: Object, required: true },
    // A guardian looking at a ward's session (ADR 0027).
    ward: { type: Object, default: null },
    seating: { type: Object, default: null },
    reminderTimeline: { type: Object, default: null },
    gallery: { type: Object, required: true },
    myAttendance: { type: Object, default: null },
    statuses: { type: Array, required: true },
    attendance: { type: Array, default: null },
    contentForm: { type: Object, default: null },
    sharedLessons: { type: Object, default: null },
    // Homework (ADR 0030): the person's hand-in, whether they may (still)
    // hand it in, and — for who grades — every attendee's.
    homeworkMaxScore: { type: Number, default: 20 },
    mySubmission: { type: Object, default: null },
    canSubmit: { type: Boolean, default: false },
    submissions: { type: Array, default: null },
});

// Stay on the tab and keep what was typed after a save.
const keep = { preserveScroll: true, preserveState: true };

const statusBadge = {
    scheduled: 'primary',
    held: 'success',
    cancelled: 'danger',
};

// ── Tabs
const tabs = computed(() => [
    { key: 'details', label: 'جزئیات', icon: 'document', badge: null },
    ...(props.reminderTimeline ? [{ key: 'reminders', label: 'یادآوری‌ها', icon: 'clipboard', badge: null }] : []),
    { key: 'gallery', label: 'گالری', icon: 'video', badge: null },
    ...(props.attendance ? [{ key: 'attendance', label: 'حضور و غیاب', icon: 'users', badge: props.attendance.length }] : []),
    ...(!props.session.is_exam ? [{ key: 'content', label: 'مطالب و تکلیف', icon: 'book', badge: null }] : []),
    ...(props.submissions ? [{ key: 'submissions', label: 'تکالیف دریافتی', icon: 'clipboard', badge: `${handedIn.value}/${props.submissions.length}` }] : []),
]);
const page = usePage();
const sessionUrl = computed(() => new URL(page.url, 'http://localhost'));
const tab = computed({
    get() {
        const selected = sessionUrl.value.searchParams.get('tab');
        return tabs.value.some(({ key }) => key === selected) ? selected : 'details';
    },
    set(selected) {
        const url = new URL(sessionUrl.value);
        if (url.searchParams.get('tab') === selected) return;

        url.searchParams.set('tab', selected);
        router.replace({
            url: url.pathname + url.search + url.hash,
            preserveState: true,
            preserveScroll: true,
        });
    },
});
const sessionTabs = useTemplateRef('session-tabs');

function beforeTabChange(nextTab) {
    if (nextTab === 'exams' && props.session.exams_url) {
        router.visit(props.session.exams_url);
        return false;
    }

    return true;
}

watch(sessionTabs, (tabs, previousTabs, onCleanup) => {
    const nav = tabs?.tabNavRef;
    const viewport = nav?.tabListRef?.parentElement;
    if (!viewport) return;

    // Element Plus can add the scroll controls after its initial active-tab scroll.
    const observer = new ResizeObserver(() => nav.scrollToActiveTab());
    observer.observe(viewport);
    onCleanup(() => observer.disconnect());
}, { flush: 'post' });

// ── Attendance
const attendanceForm = useForm({ rows: (props.attendance ?? []).map((row) => ({ ...row })) });
const statusTone = {
    present: { tag: 'success', color: '#059669' },
    late: { tag: 'warning', color: '#a16207' },
    absent: { tag: 'danger', color: '#dc2626' },
    excused: { tag: 'info', color: '#4b5563' },
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
const showSharedLessons = computed(() => Boolean(props.sharedLessons?.can_manage || props.sharedLessons?.lessons.length));
const showSharedLessonReader = computed(() => !props.contentForm && showSharedLessons.value);
const showHomework = computed(() => Boolean(props.session.homework && (!props.contentForm || props.mySubmission || props.canSubmit)));
const showSessionWriteUp = computed(() => !props.contentForm && (
    !props.sharedLessons?.lessons.length
    || props.session.content?.topics.length
    || props.session.content?.summary
    || (!props.session.content && props.session.planned_topics.length)
    || props.session.materials.length
));
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
const savedLessonCodes = computed(() => props.sharedLessons?.lessons.map(({ lesson }) => lesson.code) ?? []);
const lessonCodes = ref(savedLessonCodes.value.length ? [...savedLessonCodes.value] : ['']);
const lessonCodeError = computed(() => Object.entries(content.errors).find(([key]) => key.startsWith('lesson_codes'))?.[1]);
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
    const codes = [...new Set(lessonCodes.value.map((code) => code.trim()).filter(Boolean))];
    const updateLessons = props.sharedLessons?.can_manage && (
        codes.length !== savedLessonCodes.value.length || codes.some((code, index) => code !== savedLessonCodes.value[index])
    );

    content.transform((data) => {
        if (!updateLessons) return data;

        // An empty array has no multipart entries. A blank item explicitly clears the saved codes.
        return { ...(content.isDirty ? data : {}), lesson_codes: codes.length ? codes : [''] };
    }).post(route('my-classes.content', props.session.id), {
        ...keep,
        forceFormData: true,
        onSuccess: () => {
            content.reset('files', 'remove_media_ids');
            lessonCodes.value = savedLessonCodes.value.length ? [...savedLessonCodes.value] : [''];
        },
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
                :back-href="route('my-classes.index', ward ? { for: ward.id } : {})"
                :back-label="ward ? `تقویم آموزشی ${ward.name}` : 'تقویم آموزشی'"
            />

            <ElTabs ref="session-tabs" v-model="tab" :before-leave="beforeTabChange" class="session-tabs pt-4" aria-label="بخش‌های جلسه">
                <ElTabPane v-for="item in tabs" :key="item.key" :name="item.key" :label="item.label" lazy>
                    <template #label>
                        <PanelIcon :name="item.icon" class="size-4 shrink-0" />
                        {{ item.label }}
                        <ElTag v-if="item.badge !== null" size="small" round>{{ item.badge }}</ElTag>
                    </template>
                    <PanelCard v-if="item.key === 'details'" aria-label="جزئیات جلسه">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ui-line px-4 py-3">
                            <div class="flex flex-wrap gap-2 text-xs">
                                <ElTag v-if="session.kind_label !== 'عادی'" type="info" round>{{ session.kind_label }}</ElTag>
                                <ElTag :type="statusBadge[session.status]" round>{{ session.status_label }}</ElTag>
                            </div>
                            <div v-if="session.exam_paper_url || seating" class="flex flex-wrap items-center gap-2">
                                <PanelPrintPreview v-if="session.exam_paper_url" :url="session.exam_paper_url"
                                    title="برگهٔ آزمون جلسه" print-label="چاپ آزمون" />
                                <ExamSeatingEditor v-if="seating" :seating="seating" :url="route('my-classes.seating.update', session.id)" />
                            </div>
                        </div>

                        <dl class="grid gap-px bg-gray-100 panel-dark:bg-white/8 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr]">
                            <div class="flex items-start gap-3 bg-ui-surface p-4">
                                <PanelIcon name="clock" class="mt-0.5 size-5 shrink-0 text-brand-700" />
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 panel-dark:text-ui-muted">زمان</dt>
                                    <dd class="text-sm font-medium text-gray-900 panel-dark:text-ui-ink">{{ session.when }}</dd>
                                    <dd v-if="session.moved_from" class="text-xs text-amber-700 panel-dark:text-amber-300">جابه‌جا از {{ session.moved_from }}</dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-ui-surface p-4">
                                <PanelIcon name="user" class="mt-0.5 size-5 shrink-0 text-brand-700" />
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 panel-dark:text-ui-muted">مدرس</dt>
                                    <dd class="text-sm font-medium text-gray-900 panel-dark:text-ui-ink">{{ session.teacher || '—' }}</dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-ui-surface p-4">
                                <PanelIcon name="pin" class="mt-0.5 size-5 shrink-0 text-brand-700" />
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 panel-dark:text-ui-muted">روش برگزاری</dt>
                                    <dd class="text-sm font-medium text-gray-900 panel-dark:text-ui-ink">{{ [session.mode_label, session.location, session.online_platform].filter(Boolean).join(' · ') }}</dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-ui-surface p-4">
                                <PanelIcon name="academic" class="mt-0.5 size-5 shrink-0 text-brand-700" />
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 panel-dark:text-ui-muted">{{ ward ? `نقش ${ward.name}` : 'نقش شما' }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 panel-dark:text-ui-ink">{{ role.label }}</dd>
                                </div>
                            </div>
                        </dl>

                        <div
                            v-if="session.cancel_reason || myAttendance || session.join_url"
                            class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 panel-dark:border-ui-line p-4"
                        >
                            <div class="space-y-1 text-sm">
                                <p v-if="session.cancel_reason" class="text-red-700 panel-dark:text-red-300">دلیل لغو: {{ session.cancel_reason }}</p>
                                <p v-if="myAttendance" class="text-gray-800 panel-dark:text-ui-ink">
                                    {{ ward ? `حضور ${ward.name}` : 'حضور شما' }}:
                                    <strong>{{ myAttendance.status }}</strong>
                                    <span v-if="myAttendance.late_minutes"> ({{ myAttendance.late_minutes }} دقیقه)</span>
                                    <span v-if="myAttendance.note" class="text-gray-600 panel-dark:text-ui-muted"> · {{ myAttendance.note }}</span>
                                </p>
                            </div>
                            <PanelButton
                                v-if="session.join_url"
                                :href="session.join_url"
                                full-page
                                target="_blank"
                                rel="noopener"
                            >
                                <PanelIcon name="video" class="size-5" />
                                ورود به کلاس آنلاین
                            </PanelButton>
                        </div>
                    </PanelCard>

                    <PanelCard v-if="item.key === 'reminders' && reminderTimeline">
                        <SessionReminderTimeline :source-id="session.id" :timeline="reminderTimeline" />
                    </PanelCard>

                    <SessionGallery v-if="item.key === 'gallery'" :session-id="session.id" :gallery="gallery" />

                    <!-- Attendance -->
                    <ElForm v-if="item.key === 'attendance'" class="ui-surface" @submit.prevent="saveAttendance">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 panel-dark:border-ui-line p-4">
                            <div class="flex flex-wrap gap-2 text-xs">
                                <ElTag v-for="status in attendanceCounts" :key="status.value" :type="statusTone[status.value].tag" round>
                                    {{ status.label }}: {{ status.count }}
                                </ElTag>
                            </div>
                            <PanelButton v-if="attendance.length" variant="outline" @click="markAll('present')">
                                <PanelIcon name="check" class="size-4 text-emerald-600 panel-dark:text-emerald-300" />
                                همه حاضر
                            </PanelButton>
                        </div>

                        <PanelEmpty v-if="!attendance.length" description="شرکت‌کننده‌ای در این جلسه نیست." />

                        <ul class="divide-y divide-gray-100 panel-dark:divide-ui-line">
                            <li v-for="(row, index) in attendanceForm.rows" :key="row.user_id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                                <div class="flex min-w-44 flex-1 items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">{{ initial(row.name) }}</span>
                                    <span class="font-medium text-gray-900 panel-dark:text-ui-ink">{{ row.name }}</span>
                                </div>
                                <div class="flex max-w-full flex-wrap gap-1 rounded-ui border border-ui-line p-1" role="group" :aria-label="`حضور ${row.name}`">
                                    <PanelButton
                                        v-for="status in statuses"
                                        :key="status.value"
                                        type="button"
                                        :aria-pressed="row.status === status.value"
                                        size="default" :variant="row.status === status.value ? 'primary' : 'ghost'"
                                        :color="row.status === status.value ? statusTone[status.value].color : undefined"
                                        @click="row.status = status.value"
                                    >
                                        {{ status.label }}
                                    </PanelButton>
                                </div>
                                <ElInput
                                    v-if="row.status === 'late'"
                                    v-model.number="row.late_minutes"
                                    type="number"
                                    min="1"
                                    max="600"
                                    placeholder="چند دقیقه؟"
                                    :aria-label="`دقیقهٔ تأخیر ${row.name}`"
                                    class="max-w-28"
                                />
                                <ElInput v-model="row.note" type="text" maxlength="255" placeholder="توضیح (اختیاری)" :aria-label="`توضیح حضور ${row.name}`" class="min-w-40 flex-1 basis-40" />
                                <p v-if="attendanceForm.errors[`rows.${index}.late_minutes`]" class="w-full text-xs text-red-600 panel-dark:text-red-300">{{ attendanceForm.errors[`rows.${index}.late_minutes`] }}</p>
                            </li>
                        </ul>

                        <div v-if="attendance.length" class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-b-2xl border-t border-gray-100 panel-dark:border-ui-line bg-ui-surface/95 p-4 backdrop-blur">
                            <p class="text-sm text-red-600 panel-dark:text-red-300">{{ attendanceForm.errors.form }}</p>
                            <PanelButton type="submit" :disabled="attendanceForm.processing">
                                <PanelIcon name="check" class="size-5" />
                                ثبت حضور و غیاب
                            </PanelButton>
                        </div>
                    </ElForm>

                    <div v-if="item.key === 'content' && tab === 'content'" class="@container space-y-4">
                        <ElForm v-if="contentForm" class="space-y-4" @submit.prevent="saveContent">
                            <div class="grid grid-cols-1 gap-4">
                                <section class="ui-surface min-w-0 space-y-4 p-4 sm:p-5">
                                    <h2 class="flex items-center gap-2 font-semibold text-ui-ink">
                                        <PanelIcon name="book" class="size-5 shrink-0 text-brand-700" />
                                        مطالب جلسه
                                    </h2>
                                    <div v-if="!session.content && session.planned_topics.length" class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2.5 text-sm leading-6 text-brand-700">
                                        <span class="font-medium">طبق طرح درس، این جلسه:</span> {{ session.planned_topics.join('، ') }}
                                    </div>

                                    <fieldset v-if="topicGroups.length" class="min-w-0 space-y-3">
                                        <legend class="mb-2 text-sm font-semibold text-ui-ink">سرفصل‌هایی که تمام شد</legend>
                                        <p class="text-xs leading-5 text-ui-muted">سرفصل تمام‌نشده را انتخاب نکنید تا به جلسه‌ی بعد برود.</p>
                                        <div v-for="group in topicGroups" :key="group.name" class="space-y-2">
                                            <div v-if="topicGroups.length > 1" class="text-xs font-medium text-ui-muted" dir="auto">{{ group.name }}</div>
                                            <div class="flex flex-wrap gap-2">
                                                <PanelButton
                                                    v-for="topic in group.open"
                                                    :key="topic.id"
                                                    type="button"
                                                    :aria-pressed="content.covered_topic_ids.includes(topic.id)"
                                                    round :variant="content.covered_topic_ids.includes(topic.id) ? 'primary' : 'outline-brand'" class="panel-wrap-button"
                                                    @click="toggleTopic(topic.id)"
                                                >
                                                    <PanelIcon v-if="content.covered_topic_ids.includes(topic.id)" name="check" class="size-4" />
                                                    <span class="min-w-0 wrap-anywhere text-start" dir="auto">{{ topic.title }}</span>
                                                </PanelButton>
                                                <PanelButton
                                                    v-if="group.done.length"
                                                    type="button"
                                                    variant="ghost" class="panel-wrap-button"
                                                    :aria-expanded="Boolean(showDone[group.name])"
                                                    @click="showDone[group.name] = !showDone[group.name]"
                                                >
                                                    {{ showDone[group.name] ? 'پنهان کردن تمام‌شده‌ها' : `${group.done.length} سرفصل تمام‌شده در جلسه‌های دیگر` }}
                                                </PanelButton>
                                            </div>
                                            <div v-if="showDone[group.name]" class="flex flex-wrap gap-2">
                                                <span v-for="topic in group.done" :key="topic.id" class="rounded-full bg-ui-canvas px-3 py-1.5 text-sm text-ui-muted line-through wrap-anywhere" dir="auto">{{ topic.title }}</span>
                                            </div>
                                        </div>
                                        <p v-if="content.errors.covered_topic_ids" class="text-sm text-red-700 panel-dark:text-red-300">{{ content.errors.covered_topic_ids }}</p>
                                    </fieldset>

                                    <PanelFormField label="شرح مطالب" :error="content.errors.summary">
                                        <ElInput type="textarea" v-model="content.summary" :autosize="{ minRows: 4, maxRows: 12 }" placeholder="در این جلسه چه گفته شد؟" />
                                    </PanelFormField>
                                </section>

                                <div class="min-w-0 space-y-4">
                                    <section class="ui-surface space-y-4 p-4">
                                        <h2 class="flex items-center gap-2 font-semibold text-ui-ink">
                                            <PanelIcon name="clipboard" class="size-5 shrink-0 text-brand-700" />
                                            تکلیف
                                        </h2>
                                        <PanelFormField :error="content.errors.homework">
                                            <ElInput type="textarea" v-model="content.homework" :autosize="{ minRows: 3, maxRows: 8 }" aria-label="شرح تکلیف" placeholder="اگر تکلیفی دارد، اینجا بنویسید." />
                                        </PanelFormField>
                                        <div v-if="content.homework" class="grid grid-cols-1 gap-3">
                                            <PanelFormField label="مهلت تحویل" for="homework_due_on" :error="content.errors.homework_due_on">
                                                <PanelJalaliDateInput id="homework_due_on" v-model="content.homework_due_on" />
                                            </PanelFormField>
                                            <PanelFormField label="نمرهٔ تکلیف از" :error="content.errors.homework_max_score">
                                                <ElInput v-model.number="content.homework_max_score" type="number" min="1" max="100" />
                                            </PanelFormField>
                                        </div>
                                    </section>

                                    <section class="ui-surface space-y-3 p-4">
                                        <h2 class="flex items-center gap-2 font-semibold text-ui-ink">
                                            <PanelIcon name="paperclip" class="size-5 shrink-0 text-brand-700" />
                                            فایل‌های جلسه
                                        </h2>
                                        <ul v-if="contentForm.materials.length" class="space-y-1">
                                            <li
                                                v-for="file in contentForm.materials"
                                                :key="file.id"
                                                class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm ring-1"
                                                :class="content.remove_media_ids.includes(file.id) ? 'bg-red-50 panel-dark:bg-red-950/50 text-red-700 panel-dark:text-red-300 ring-red-200 panel-dark:ring-red-800/55' : 'bg-gray-50 panel-dark:bg-ui-canvas text-gray-800 panel-dark:text-ui-ink ring-gray-200 panel-dark:ring-ui-line'"
                                            >
                                                <span class="flex min-w-0 items-center gap-2" :class="{ 'line-through': content.remove_media_ids.includes(file.id) }">
                                                    <PanelIcon name="document" class="size-4 shrink-0" />
                                                    <span class="wrap-anywhere" dir="auto">{{ file.name }}</span>
                                                </span>
                                                <PanelButton type="button" size="small" :variant="content.remove_media_ids.includes(file.id) ? 'link' : 'danger-link'" @click="toggleRemoval(file.id)">
                                                    {{ content.remove_media_ids.includes(file.id) ? 'نگه‌داشتن' : 'حذف' }}
                                                </PanelButton>
                                            </li>
                                        </ul>
                                        <FilePicker v-model="content.files" hint="جزوه، تمرین، اسلاید؛ هر فایل تا ۲۰ مگابایت" :error="fileError(content)" />
                                        <ElProgress v-if="content.progress" :percentage="content.progress.percentage ?? 0" aria-label="پیشرفت بارگذاری فایل‌ها" />
                                    </section>
                                </div>
                            </div>
                            <SharedSessionLessons
                                v-if="showSharedLessons"
                                v-model:codes="lessonCodes"
                                :sharing="sharedLessons"
                                :error="lessonCodeError"
                                :processing="content.processing"
                            />
                            <div class="flex flex-col gap-3 border-t border-ui-line pt-3 @lg:flex-row @lg:items-center @lg:justify-end">
                                <p v-if="content.errors.form" role="alert" class="me-auto text-sm text-red-700 panel-dark:text-red-300">{{ content.errors.form }}</p>
                                <PanelButton type="submit" variant="outline-brand" :loading="content.processing">
                                    ذخیرهٔ مطالب جلسه
                                </PanelButton>
                            </div>
                        </ElForm>

                        <div
                            v-if="showSessionWriteUp || showSharedLessonReader || showHomework"
                            class="grid grid-cols-1 gap-4"
                        >
                            <div v-if="showSessionWriteUp || showSharedLessonReader" class="min-w-0 space-y-4">
                                <section v-if="showSessionWriteUp" class="ui-surface min-w-0 space-y-4 p-4 sm:p-5">
                                    <h2 class="flex items-center gap-2 font-semibold text-ui-ink">
                                        <PanelIcon name="book" class="size-5 shrink-0 text-brand-700" />
                                        مطالب جلسه
                                    </h2>
                                    <template v-if="session.content?.topics.length || session.content?.summary">
                                        <div v-if="session.content.topics.length" class="flex flex-wrap gap-2">
                                            <span v-for="topic in session.content.topics" :key="topic" class="inline-flex max-w-full items-center gap-1.5 rounded-lg bg-brand-50 px-3 py-1 text-sm text-brand-700">
                                                <PanelIcon name="check" class="size-4 shrink-0" />
                                                <span class="min-w-0 wrap-anywhere" dir="auto">{{ topic }}</span>
                                            </span>
                                        </div>
                                        <p v-if="session.content.summary" class="whitespace-pre-line wrap-anywhere text-sm leading-7 text-ui-ink">{{ session.content.summary }}</p>
                                    </template>
                                    <p v-else-if="!session.content && session.planned_topics.length" class="wrap-anywhere text-sm leading-7 text-ui-muted">طبق طرح درس: {{ session.planned_topics.join('، ') }}</p>
                                    <p v-else class="text-sm text-ui-muted">هنوز ثبت نشده است.</p>

                                    <section v-if="session.materials.length" class="space-y-3 border-t border-ui-line pt-4">
                                        <h3 class="flex items-center gap-2 text-sm font-semibold text-ui-ink">
                                            <PanelIcon name="paperclip" class="size-4 shrink-0 text-brand-700" />
                                            فایل‌های جلسه
                                        </h3>
                                        <ul class="grid gap-2">
                                            <li v-for="file in session.materials" :key="file.url" class="min-w-0">
                                                <ElLink type="primary"
                                                    :href="file.url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="panel-file-link w-full justify-start! rounded-lg border border-ui-line bg-ui-canvas px-3! py-2!"
                                                >
                                                    <PanelIcon name="document" class="size-4 shrink-0" />
                                                    <span class="min-w-0 wrap-anywhere text-start" dir="auto">{{ file.name }}</span>
                                                </ElLink>
                                            </li>
                                        </ul>
                                    </section>
                                </section>

                                <SharedSessionLessons
                                    v-if="showSharedLessonReader"
                                    :key="session.id"
                                    :sharing="sharedLessons"
                                />
                            </div>

                            <div v-if="showHomework" class="min-w-0 space-y-4">
                                <section v-if="!contentForm" class="ui-surface min-w-0 space-y-3 p-4 sm:p-5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h2 class="flex items-center gap-2 font-semibold text-ui-ink">
                                            <PanelIcon name="clipboard" class="size-5 shrink-0 text-brand-700" />
                                            تکلیف
                                        </h2>
                                        <span v-if="session.homework_due" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1 text-xs text-amber-900 panel-dark:bg-amber-950/45 panel-dark:text-amber-200">
                                            <PanelIcon name="clock" class="size-4 shrink-0" />
                                            مهلت تحویل {{ session.homework_due }}
                                        </span>
                                    </div>
                                    <p class="whitespace-pre-line wrap-anywhere text-sm leading-7 text-ui-ink">{{ session.homework }}</p>
                                </section>

                                <section v-if="mySubmission || canSubmit" class="ui-surface min-w-0 space-y-4 p-4 sm:p-5">
                                    <h2 class="flex items-center gap-2 font-semibold text-ui-ink">
                                        <PanelIcon name="pencil" class="size-5 shrink-0 text-brand-700" />
                                        {{ ward ? `تکلیف ${ward.name}` : 'تکلیف شما' }}
                                    </h2>
                                    <div v-if="mySubmission" class="space-y-3 text-sm text-ui-ink">
                                        <p class="flex flex-wrap items-center gap-2 text-ui-muted">
                                            <span>زمان تحویل {{ mySubmission.submitted_at }}</span>
                                            <span v-if="mySubmission.is_late" class="rounded-full bg-red-50 panel-dark:bg-red-950/50 px-2 py-0.5 text-xs text-red-700 panel-dark:text-red-300">با تأخیر</span>
                                        </p>
                                        <div v-if="mySubmission.is_graded" class="rounded-xl bg-emerald-50 panel-dark:bg-emerald-950/50 p-3 text-emerald-900 panel-dark:text-emerald-200 ring-1 ring-emerald-100 panel-dark:ring-emerald-800/55">
                                            <p v-if="mySubmission.score !== null">نمره: <strong class="text-lg">{{ mySubmission.score }}</strong> از {{ homeworkMaxScore }}</p>
                                            <p v-if="mySubmission.feedback" class="mt-1 whitespace-pre-line wrap-anywhere">بازخورد مدرس: {{ mySubmission.feedback }}</p>
                                        </div>
                                        <p v-if="!canSubmit && mySubmission.answer" class="whitespace-pre-line wrap-anywhere rounded-lg bg-ui-canvas p-3">{{ mySubmission.answer }}</p>
                                        <div v-if="!canSubmit && mySubmission.files.length" class="flex flex-wrap gap-2">
                                            <ElLink type="primary" v-for="file in mySubmission.files" :key="file.id" :href="file.url" target="_blank" rel="noopener" class="panel-file-link rounded-lg bg-ui-canvas px-3! py-2! ring-1 ring-ui-line">
                                                <PanelIcon name="document" class="size-4 shrink-0" />
                                                <span class="min-w-0 wrap-anywhere text-start" dir="auto">{{ file.name }}</span>
                                            </ElLink>
                                        </div>
                                    </div>

                                    <ElForm v-if="canSubmit" class="space-y-4" @submit.prevent="submitHomework">
                                        <PanelFormField label="پاسخ" :error="handIn.errors.answer">
                                            <ElInput type="textarea" v-model="handIn.answer" :autosize="{ minRows: 3, maxRows: 10 }" placeholder="پاسخ خود را بنویسید…" />
                                        </PanelFormField>
                                        <ul v-if="mySubmission?.files.length" class="space-y-1">
                                            <li
                                                v-for="file in mySubmission.files"
                                                :key="file.id"
                                                class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm ring-1"
                                                :class="handIn.remove_media_ids.includes(file.id) ? 'bg-red-50 panel-dark:bg-red-950/50 text-red-700 panel-dark:text-red-300 ring-red-200 panel-dark:ring-red-800/55' : 'bg-gray-50 panel-dark:bg-ui-canvas text-gray-800 panel-dark:text-ui-ink ring-gray-200 panel-dark:ring-ui-line'"
                                            >
                                                <ElLink type="primary" :href="file.url" target="_blank" rel="noopener" class="min-w-0 wrap-anywhere text-start hover:underline" :class="{ 'line-through': handIn.remove_media_ids.includes(file.id) }" dir="auto">{{ file.name }}</ElLink>
                                                <PanelButton type="button" variant="link" @click="toggleHandInRemoval(file.id)">
                                                    {{ handIn.remove_media_ids.includes(file.id) ? 'نگه‌داشتن' : 'حذف' }}
                                                </PanelButton>
                                            </li>
                                        </ul>
                                        <FilePicker v-model="handIn.files" hint="تا ۵ فایل، هر کدام تا ۲۰ مگابایت" :error="fileError(handIn)" />
                                        <ElProgress v-if="handIn.progress" :percentage="handIn.progress.percentage ?? 0" aria-label="پیشرفت بارگذاری فایل‌ها" />
                                        <div class="flex flex-col gap-3 border-t border-ui-line pt-3 @lg:flex-row @lg:items-center @lg:justify-end">
                                            <p v-if="handIn.errors.form" role="alert" class="me-auto text-sm text-red-700 panel-dark:text-red-300">{{ handIn.errors.form }}</p>
                                            <PanelButton type="submit" :loading="handIn.processing">
                                                {{ mySubmission ? 'به‌روزرسانی تکلیف' : 'تحویل تکلیف' }}
                                            </PanelButton>
                                        </div>
                                    </ElForm>
                                </section>
                            </div>
                        </div>
                    </div>

                    <!-- Grading: who may (HomeworkBook::canGrade), at any time. -->
                    <ElForm v-if="item.key === 'submissions'" class="ui-surface" @submit.prevent="saveGrades">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 panel-dark:border-ui-line p-4 text-sm">
                            <span class="text-gray-700 panel-dark:text-ui-ink">تحویل داده‌اند: <strong>{{ handedIn }}</strong> از {{ submissions.length }}</span>
                            <span class="text-gray-500 panel-dark:text-ui-muted">نمره از {{ homeworkMaxScore }}</span>
                        </div>
                        <PanelEmpty v-if="!submissions.length" description="شرکت‌کننده‌ای در این جلسه نیست." />
                        <ul class="divide-y divide-gray-100 panel-dark:divide-ui-line">
                            <li v-for="row in submissions" :key="row.name" class="space-y-3 p-4 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 font-bold text-brand-700">{{ initial(row.name) }}</span>
                                        <span class="font-medium text-gray-900 panel-dark:text-ui-ink">{{ row.name }}</span>
                                    </div>
                                    <span v-if="!row.submission" class="rounded-full bg-gray-100 panel-dark:bg-white/8 px-2.5 py-0.5 text-xs text-gray-500 panel-dark:text-ui-muted">تحویل نداده</span>
                                    <span v-else class="text-xs text-gray-500 panel-dark:text-ui-muted">
                                        تحویل: {{ row.submission.submitted_at }}
                                        <span v-if="row.submission.is_late" class="rounded-full bg-red-50 panel-dark:bg-red-950/50 px-2 py-0.5 text-red-700 panel-dark:text-red-300">با تأخیر</span>
                                    </span>
                                </div>
                                <template v-if="row.submission">
                                    <p v-if="row.submission.answer" class="whitespace-pre-line wrap-break-word rounded-lg bg-gray-50 panel-dark:bg-ui-canvas p-3 leading-7 text-gray-800 panel-dark:text-ui-ink">{{ row.submission.answer }}</p>
                                    <div v-if="row.submission.files.length" class="flex flex-wrap gap-2">
                                        <ElLink type="primary" v-for="file in row.submission.files" :key="file.id" :href="file.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 panel-dark:bg-ui-canvas px-3 py-1.5 text-brand-700 ring-1 ring-gray-200 panel-dark:ring-ui-line hover:bg-brand-50">
                                            <PanelIcon name="document" class="size-4" />
                                            <span dir="auto">{{ file.name }}</span>
                                        </ElLink>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <ElInput v-model.number="gradeOf(row.submission).score" type="number" min="0" :max="homeworkMaxScore" step="0.25" placeholder="نمره" :aria-label="`نمرهٔ ${row.name}`" class="max-w-28" />
                                        <ElInput v-model="gradeOf(row.submission).feedback" type="text" maxlength="1000" placeholder="بازخورد (اختیاری)" :aria-label="`بازخورد برای ${row.name}`" class="min-w-0 flex-1" />
                                    </div>
                                </template>
                            </li>
                        </ul>
                        <div v-if="grading.grades.length || grading.errors.form" class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-b-2xl border-t border-gray-100 panel-dark:border-ui-line bg-ui-surface/95 p-4 backdrop-blur">
                            <p class="text-sm text-red-600 panel-dark:text-red-300">{{ grading.errors.form }}</p>
                            <PanelButton v-if="grading.grades.length" type="submit" :disabled="grading.processing">
                                <PanelIcon name="check" class="size-5" />
                                ذخیره‌ی نمره‌ها
                            </PanelButton>
                        </div>
                    </ElForm>
                </ElTabPane>
                <ElTabPane v-if="session.exams_url" name="exams" label="آزمون‌ها" lazy>
                    <template #label>
                        <PanelIcon name="clipboard" class="size-4 shrink-0" />
                        آزمون‌ها
                    </template>
                </ElTabPane>
            </ElTabs>
        </div>
    </AppLayout>
</template>

<style scoped>
.session-tabs :deep(.el-tabs__nav) {
    column-gap: 0.75rem;
}

@media (min-width: 40rem) {
    .session-tabs :deep(.el-tabs__nav) {
        column-gap: 1rem;
    }
}

.session-tabs :deep(.el-tabs__nav-wrap:not(.is-scrollable) .el-tabs__nav) {
    float: inline-start;
}
</style>
