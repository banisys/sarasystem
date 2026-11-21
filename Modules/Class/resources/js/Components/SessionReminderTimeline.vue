<script setup>
import { useForm } from '@inertiajs/vue3';
import { ElForm, ElInput } from 'element-plus';
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

const editorId = ref(null);
const form = useForm({ body: '' });

function closeEditor() {
    editorId.value = null;
    form.reset();
    form.clearErrors();
}

function save(targetId) {
    form.post(route('my-classes.reminders.store', { session: props.sourceId, target: targetId }), {
        preserveScroll: true,
        onSuccess: closeEditor,
    });
}
</script>

<template>
    <section aria-labelledby="session-reminders-heading" class="space-y-4 border-t border-ui-line p-4 sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="session-reminders-heading" class="flex items-center gap-2 font-semibold text-ui-ink">
                <PanelIcon name="clipboard" class="size-5 text-brand-700 panel-dark:text-brand-300" />
                یادآوری‌های جلسه
            </h2>
            <p v-if="timeline.term" class="text-sm text-ui-muted">{{ timeline.term }}</p>
        </div>

        <ol class="ms-2 space-y-4 border-s border-ui-line">
            <li v-for="entry in timeline.sessions" :key="entry.id" class="relative ps-5">
                <span
                    aria-hidden="true"
                    class="absolute -start-1.5 top-1.5 size-3 rounded-full ring-4 ring-ui-surface"
                    :class="entry.is_current ? 'bg-brand-600 panel-dark:bg-brand-300' : 'bg-ui-field'"
                />
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 space-y-0.5">
                        <h3 class="text-sm font-semibold text-ui-ink">{{ entry.is_current ? 'این جلسه' : (entry.title ?? entry.class) }}</h3>
                        <p class="text-sm text-ui-muted">
                            {{ entry.when }}
                            <template v-if="!entry.is_current && entry.title"> · {{ entry.class }}</template>
                        </p>
                    </div>
                    <PanelButton
                        v-if="!entry.is_current && editorId !== entry.id"
                        variant="outline-brand"
                        :disabled="editorId !== null"
                        @click="editorId = entry.id"
                    >
                        ثبت یادآوری
                    </PanelButton>
                </div>

                <ul v-if="entry.reminders.length" class="mt-2.5 divide-y divide-ui-line rounded-ui border border-ui-line bg-ui-canvas">
                    <li v-for="reminder in entry.reminders" :key="reminder.id" class="space-y-1 px-3 py-2.5">
                        <p class="whitespace-pre-wrap wrap-break-word text-sm text-ui-ink">{{ reminder.body }}</p>
                        <p class="text-xs text-ui-muted">
                            <template v-if="reminder.author">{{ reminder.author }} · </template>{{ reminder.recorded_at }}
                        </p>
                    </li>
                </ul>
                <p v-else-if="entry.is_current" class="mt-2 text-sm text-ui-muted">برای این جلسه یادآوری ثبت نشده است.</p>

                <ElForm v-if="editorId === entry.id" class="mt-3 space-y-2" @submit.prevent="save(entry.id)">
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
                        <PanelButton type="submit" :loading="form.processing" :disabled="!form.body.trim()">ذخیره</PanelButton>
                        <PanelButton variant="ghost" :disabled="form.processing" @click="closeEditor">انصراف</PanelButton>
                    </div>
                </ElForm>
            </li>
        </ol>
        <p v-if="timeline.sessions.length === 1" class="text-sm text-ui-muted">جلسهٔ آینده‌ای برای ثبت یادآوری وجود ندارد.</p>
    </section>
</template>
