<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ElCollapse, ElCollapseItem, ElForm, ElInput } from 'element-plus';
import 'element-plus/es/components/collapse/style/css';
import 'element-plus/es/components/collapse-item/style/css';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import { ref } from 'vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import PanelIcon from '@/Components/PanelIcon.vue';

const props = defineProps({
    sourceId: { type: Number, required: true },
    timeline: { type: Object, required: true },
});

const openSession = ref('');
const loadingDirection = ref(null);
const loadError = ref(false);
const form = useForm({ body: '' });

function loadSessions(direction, count) {
    const showLoadError = () => {
        loadError.value = true;
        return false;
    };

    loadingDirection.value = direction;
    loadError.value = false;
    router.get(route('my-classes.session', props.sourceId), {
        ...props.timeline.counts,
        [direction]: count,
    }, {
        only: ['reminderTimeline'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onHttpException: showLoadError,
        onNetworkError: showLoadError,
        onFinish: () => { loadingDirection.value = null; },
    });
}

function resetForm() {
    form.reset();
    form.clearErrors();
}

function closeEditor() {
    openSession.value = '';
    resetForm();
}

function save(targetId) {
    form.post(route('my-classes.reminders.store', {
        session: props.sourceId,
        target: targetId,
        ...props.timeline.counts,
    }), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
}
</script>

<template>
    <section aria-label="یادآوری‌های جلسه" class="space-y-4 p-4 sm:p-5">
        <p v-if="timeline.term" class="text-sm text-ui-muted">{{ timeline.term }}</p>

        <PanelButton
            v-if="timeline.more_previous"
            variant="outline-brand"
            size="small"
            :loading="loadingDirection === 'previous_reminders'"
            :disabled="form.processing || loadingDirection !== null"
            @click="loadSessions('previous_reminders', timeline.more_previous)"
        >
            نمایش جلسه‌های قبلی
        </PanelButton>

        <ElCollapse v-model="openSession" accordion class="reminder-accordion overflow-hidden rounded-lg border border-ui-line" @change="resetForm">
            <ElCollapseItem v-for="entry in timeline.sessions" :key="entry.id" :name="entry.id" :disabled="form.processing">
                <template #title>
                    <span class="flex items-center gap-2 text-sm font-normal text-ui-muted">
                        <span
                            v-if="entry.is_current"
                            aria-hidden="true"
                            class="size-2 shrink-0 rounded-full bg-brand-600 panel-dark:bg-brand-300"
                        />
                        {{ entry.date }}
                    </span>
                </template>

                <div class="px-4 sm:px-5">
                    <ul v-if="entry.reminders.length" class="divide-y divide-ui-line">
                        <li v-for="reminder in entry.reminders" :key="reminder.id" class="flex items-start gap-2.5 py-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full border border-ui-line text-ui-muted">
                                <PanelIcon name="document" class="size-3.5" />
                            </span>
                            <div class="grid min-w-0 flex-1 gap-1.5 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-4">
                                <p class="whitespace-pre-wrap wrap-break-word text-sm font-normal leading-6 text-ui-muted">{{ reminder.body }}</p>
                                <p class="flex flex-wrap gap-x-2 text-xs leading-6 text-ui-muted sm:justify-end">
                                    <span v-if="reminder.author">{{ reminder.author }}</span>
                                    <span class="whitespace-nowrap tabular-nums">{{ reminder.recorded_at }}</span>
                                </p>
                            </div>
                        </li>
                    </ul>
                    <p v-else-if="!entry.can_add" class="py-3 text-sm text-ui-muted">برای این جلسه یادآوری ثبت نشده است.</p>

                    <ElForm v-if="openSession === entry.id && entry.can_add" class="grid gap-4 py-4" :class="{ 'border-t border-ui-line': entry.reminders.length }" @submit.prevent="save(entry.id)">
                        <PanelFormField label="متن یادآوری" :error="form.errors.body">
                            <ElInput
                                v-model="form.body"
                                type="textarea"
                                :rows="3"
                                :maxlength="timeline.max_length"
                                :disabled="form.processing"
                                :aria-invalid="!!form.errors.body"
                            />
                        </PanelFormField>
                        <div class="flex flex-wrap gap-2">
                            <PanelButton type="submit" size="small" :loading="form.processing" :disabled="!form.body.trim() || loadingDirection !== null">ذخیره</PanelButton>
                            <PanelButton variant="ghost" size="small" :disabled="form.processing" @click="closeEditor">انصراف</PanelButton>
                        </div>
                    </ElForm>
                </div>
            </ElCollapseItem>
        </ElCollapse>
        <PanelButton
            v-if="timeline.more_upcoming"
            variant="outline-brand"
            size="small"
            :loading="loadingDirection === 'upcoming_reminders'"
            :disabled="form.processing || loadingDirection !== null"
            @click="loadSessions('upcoming_reminders', timeline.more_upcoming)"
        >
            نمایش جلسه‌های بعدی بیشتر
        </PanelButton>
        <p v-if="!timeline.has_upcoming" class="text-sm text-ui-muted">جلسهٔ آینده‌ای برای ثبت یادآوری وجود ندارد.</p>
        <p v-if="loadError" role="alert" class="text-sm text-ui-muted">بارگیری جلسه‌ها انجام نشد. دوباره تلاش کنید.</p>
    </section>
</template>

<style scoped>
.reminder-accordion :deep(.el-collapse-item__header) {
    min-height: 3.375rem;
    padding-inline: 1rem;
    background-color: color-mix(in srgb, var(--color-ui-surface) 35%, var(--color-ui-canvas));
}

.reminder-accordion :deep(.el-collapse-item__header.is-active) {
    border-bottom-color: var(--color-ui-line);
}

.reminder-accordion :deep(.el-collapse-item__header:focus-visible) {
    outline: 2px solid var(--color-brand-500);
    outline-offset: -2px;
}

.reminder-accordion :deep(.el-collapse-item__arrow) {
    margin-inline: auto 0;
    color: var(--color-ui-ink);
    transform: rotate(90deg);
}

.reminder-accordion :deep(.el-collapse-item__arrow.is-active) {
    transform: rotate(-90deg);
}

.reminder-accordion :deep(.el-collapse-item__wrap) {
    background-color: var(--color-ui-surface);
}

.reminder-accordion :deep(.el-collapse-item__content) {
    padding-bottom: 0;
}
</style>
