<script setup>
import { useForm } from '@inertiajs/vue3';
import FilePicker from '@/Components/FilePicker.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';

const props = defineProps({
    sessionId: { type: Number, required: true },
    gallery: { type: Object, required: true },
});

const form = useForm({ files: [] });

function upload() {
    form.transform(({ files }) => ({ file: files[0] }))
        .post(route('my-classes.gallery.store', props.sessionId), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
}
</script>

<template>
    <PanelCard aria-label="گالری جلسه" class="space-y-5 p-4 sm:p-5">
        <form v-if="gallery.can_upload" class="space-y-3" @submit.prevent="upload">
            <FilePicker
                v-model="form.files"
                :multiple="false"
                :accept="gallery.accept"
                :hint="`عکس یا ویدیو، حداکثر ${gallery.max_mb.toLocaleString('fa-IR')} مگابایت برای هر فایل`"
                :disabled="form.processing"
                :error="form.errors.file"
                @update:model-value="form.clearErrors('file')"
            />
            <div class="flex flex-wrap items-center gap-3">
                <PanelButton type="submit" :loading="form.processing" :disabled="!form.files.length">
                    بارگذاری
                </PanelButton>
                <progress
                    v-if="form.progress"
                    :value="form.progress.percentage"
                    max="100"
                    aria-label="پیشرفت بارگذاری"
                    class="w-32 accent-brand-600"
                />
            </div>
        </form>

        <ul v-if="gallery.files.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="file in gallery.files" :key="file.id" class="overflow-hidden rounded-lg border border-ui-line">
                <video
                    v-if="file.is_video"
                    :src="file.url"
                    :aria-label="file.name"
                    controls
                    playsinline
                    preload="none"
                    class="aspect-video w-full bg-ui-canvas object-contain"
                />
                <a v-else :href="file.url" target="_blank" rel="noopener" class="block">
                    <img :src="file.url" :alt="file.name" loading="lazy" class="aspect-video w-full bg-ui-canvas object-contain" />
                </a>
                <p dir="auto" class="wrap-break-word px-3 py-2 text-sm text-ui-muted">{{ file.name }}</p>
            </li>
        </ul>
        <PanelEmpty v-else description="هنوز عکس یا ویدیویی برای این جلسه ثبت نشده است." />
    </PanelCard>
</template>
