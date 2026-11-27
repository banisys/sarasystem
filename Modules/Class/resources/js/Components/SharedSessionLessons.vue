<script setup>
import { nextTick } from 'vue';
import { ElInput } from 'element-plus';
import 'element-plus/es/components/input/style/css';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import PanelIcon from '@/Components/PanelIcon.vue';

defineProps({
    sharing: { type: Object, required: true },
    error: { type: String, default: '' },
    processing: Boolean,
});

const codes = defineModel('codes', { type: Array, default: () => [''] });

async function addCode() {
    codes.value = [...codes.value, ''];
    await nextTick();
    document.getElementById(`session-lesson-code-${codes.value.length - 1}`)?.focus();
}
</script>

<template>
    <section class="ui-surface min-w-0 space-y-4 p-4 sm:p-5" aria-labelledby="shared-session-lessons">
        <h2 id="shared-session-lessons" class="flex items-center gap-2.5 font-semibold text-ui-ink">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700">
                <PanelIcon name="book" class="size-5" />
            </span>
            درس‌های خودآموز این جلسه
        </h2>

        <fieldset v-if="sharing.can_manage" class="min-w-0 space-y-2 rounded-xl border border-ui-line bg-ui-canvas p-3 sm:p-4">
            <legend class="px-1 text-sm font-medium text-ui-ink">کدهای درس</legend>
            <div v-for="(_, index) in codes" :key="index" class="grid max-w-xl grid-cols-[minmax(0,1fr)_2.5rem] items-start gap-2">
                <PanelFormField class="min-w-0">
                    <ElInput
                        :id="`session-lesson-code-${index}`"
                        v-model="codes[index]"
                        dir="ltr"
                        maxlength="64"
                        placeholder="LSN-…"
                        :aria-label="`کد درس ${(index + 1).toLocaleString('fa-IR')}`"
                        aria-describedby="shared-session-lesson-code-hint"
                        :disabled="processing"
                        clearable
                    />
                </PanelFormField>
                <PanelButton v-if="index === codes.length - 1" type="button" variant="outline-brand" class="w-10 px-0!"
                    aria-label="افزودن کد درس" :disabled="processing || codes.length >= 50" @click="addCode">
                    <PanelIcon name="plus" class="size-5" />
                </PanelButton>
            </div>
            <p id="shared-session-lesson-code-hint" class="text-xs leading-5 text-ui-muted">برای برداشتن درس، کد آن را پاک کنید.</p>
            <p v-if="error" role="alert" class="text-sm text-red-700 panel-dark:text-red-300">{{ error }}</p>
        </fieldset>

        <div v-if="sharing.lessons.length" class="grid gap-2.5">
            <details v-for="{ lesson, video, audio, attachments } in sharing.lessons" :key="lesson.id" :open="sharing.lessons.length === 1"
                class="group min-w-0 rounded-ui border border-ui-line bg-ui-surface">
                <summary class="flex cursor-pointer list-none items-center gap-3 rounded-ui px-3 py-3 hover:bg-ui-canvas focus-visible:outline-2 focus-visible:outline-brand-700 group-open:rounded-b-none group-open:bg-ui-canvas sm:px-4 [&::-webkit-details-marker]:hidden">
                    <PanelIcon name="document" class="size-5 shrink-0 text-brand-700" />
                    <span class="min-w-0 flex-1">
                        <span class="block wrap-anywhere text-sm font-semibold leading-6 text-ui-ink" dir="auto">{{ lesson.title }}</span>
                        <span v-if="sharing.can_manage || video || audio || attachments.length" class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs leading-5 text-ui-muted">
                            <bdi v-if="sharing.can_manage" class="wrap-anywhere font-mono">{{ lesson.code }}</bdi>
                            <span v-if="video">ویدئو</span>
                            <span v-if="audio">صوت</span>
                            <span v-if="attachments.length">{{ attachments.length.toLocaleString('fa-IR') }} پیوست</span>
                        </span>
                    </span>
                    <PanelIcon name="chevron-right" class="size-4 shrink-0 text-ui-muted motion-safe:transition-transform rtl:rotate-180 group-open:rotate-90 rtl:group-open:rotate-90" />
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
