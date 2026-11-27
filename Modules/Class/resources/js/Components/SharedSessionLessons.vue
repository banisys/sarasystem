<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElForm, ElInput } from 'element-plus';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import PanelIcon from '@/Components/PanelIcon.vue';

const props = defineProps({
    sessionId: { type: Number, required: true },
    sharing: { type: Object, required: true },
});

const savedCodes = computed(() => props.sharing.lessons.map(({ lesson }) => lesson.code).join('\n'));
const form = useForm({ lesson_codes: savedCodes.value });
const codeError = computed(() => Object.entries(form.errors).find(([field]) => field.startsWith('lesson_codes'))?.[1]);

watch(savedCodes, (codes) => {
    form.defaults('lesson_codes', codes);
    form.reset();
});

function save() {
    const codes = form.lesson_codes.split(/\r?\n/).map((code) => code.trim()).filter(Boolean);
    form.transform(() => ({ lesson_codes: [...new Set(codes)] })).put(route('my-classes.lessons.update', props.sessionId), {
        preserveScroll: true,
        onSuccess: () => {
            form.lesson_codes = savedCodes.value;
        },
    });
}
</script>

<template>
    <section class="ui-surface min-w-0 space-y-4 p-4 sm:p-5" aria-labelledby="shared-session-lessons">
        <h2 id="shared-session-lessons" class="flex items-center gap-2 font-semibold text-ui-ink">
            <PanelIcon name="book" class="size-5 shrink-0 text-brand-700" />
            درس‌های این جلسه
        </h2>

        <ElForm v-if="sharing.can_manage" class="space-y-3" @submit.prevent="save">
            <PanelFormField label="کدهای درس" :error="codeError">
                <ElInput
                    v-model="form.lesson_codes"
                    type="textarea"
                    dir="ltr"
                    :autosize="{ minRows: 2, maxRows: 8 }"
                    aria-describedby="shared-session-lesson-code-hint"
                />
            </PanelFormField>
            <p id="shared-session-lesson-code-hint" class="text-xs leading-5 text-ui-muted">
                هر کد را در یک خط بنویسید. برای برداشتن یک درس از جلسه، کد آن را پاک کنید و ذخیره کنید.
            </p>
            <div class="flex justify-end">
                <PanelButton type="submit" :loading="form.processing" class="w-full sm:w-auto">
                    ذخیرهٔ درس‌های جلسه
                </PanelButton>
            </div>
        </ElForm>

        <div v-if="sharing.lessons.length" class="grid gap-2">
            <details v-for="{ lesson, video, audio, attachments } in sharing.lessons" :key="lesson.id" :open="sharing.lessons.length === 1"
                class="group min-w-0 rounded-ui border border-ui-line bg-ui-surface">
                <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-ui bg-ui-canvas px-3 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-brand-700 [&::-webkit-details-marker]:hidden">
                    <PanelIcon name="document" class="size-4 shrink-0 text-brand-700" />
                    <span class="min-w-0 flex-1 wrap-anywhere" dir="auto">{{ lesson.title }}</span>
                    <PanelIcon name="chevron-right" class="size-4 shrink-0 text-ui-muted transition-transform rtl:rotate-180 group-open:rotate-90 rtl:group-open:rotate-90" />
                </summary>
                <div class="grid min-w-0 gap-4 border-t border-ui-line p-3 sm:p-4">
                    <video v-if="video" :src="video" controls playsinline preload="none"
                        :aria-label="lesson.title" class="aspect-video max-h-[60svh] w-full rounded-lg bg-black object-contain" />
                    <audio v-if="audio" :src="audio" controls preload="none" :aria-label="`فایل صوتی ${lesson.title}`"
                        class="w-full min-w-0" />
                    <div v-if="lesson.bodyHtml"
                        class="min-w-0 overflow-x-auto wrap-anywhere text-sm leading-7 [&_a]:text-brand-700 [&_a]:underline [&_h2]:font-bold [&_h3]:font-bold [&_ol]:list-decimal [&_ol]:ps-6 [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:ps-6"
                        v-html="lesson.bodyHtml" />
                    <p v-else-if="lesson.body" class="whitespace-pre-wrap wrap-anywhere text-sm leading-7">{{ lesson.body }}</p>
                    <ul v-if="attachments.length" class="grid gap-2" aria-label="پیوست‌های درس">
                        <li v-for="file in attachments" :key="file.url" class="min-w-0">
                            <a :href="file.url"
                                class="flex items-center gap-2 rounded-lg border border-ui-line bg-ui-canvas px-3 py-2 text-sm text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-brand-700">
                                <PanelIcon name="paperclip" class="size-4 shrink-0" />
                                <bdi class="min-w-0 wrap-anywhere">{{ file.name }}</bdi>
                            </a>
                        </li>
                    </ul>
                    <p v-if="!video && !audio && !lesson.bodyHtml && !lesson.body && !attachments.length" class="text-sm text-ui-muted">
                        محتوایی برای این درس ثبت نشده است.
                    </p>
                </div>
            </details>
        </div>
    </section>
</template>
