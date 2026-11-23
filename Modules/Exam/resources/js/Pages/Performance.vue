<script setup>
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelEmpty from '@/Components/Ui/PanelEmpty.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ElOption, ElSelect } from 'element-plus';
import 'element-plus/es/components/select/style/css';
import { ref } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    selectedGroup: { type: Number, default: null },
    subjects: { type: Array, required: true },
    learnerName: { type: String, default: null },
    reportUrl: { type: String, default: null },
    backUrl: { type: String, default: null },
});

const loading = ref(false);
const scoreFormat = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 });

function selectGroup(group) {
    router.get(props.reportUrl ?? route('exams.performance'), { group }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
    });
}
</script>

<template>
    <Head :title="learnerName ? `گزارش عملکرد ${learnerName}` : 'گزارش عملکرد'" />

    <AppLayout>
        <div class="grid min-w-0 gap-3 sm:gap-4">
            <Link v-if="backUrl" :href="backUrl" class="w-fit text-sm font-semibold text-brand-700 hover:underline">
                بازگشت به کلاس
            </Link>
            <UiPageHeader :title="learnerName ? `گزارش عملکرد ${learnerName}` : 'گزارش عملکرد'" icon="document" />

            <PanelEmpty v-if="!groups.length" description="گروه آزمونی برای نمایش گزارش ندارید." />

            <template v-else>
                <PanelCard body-class="[--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]">
                    <div class="grid gap-2 sm:max-w-sm">
                        <label for="performance-exam-group" class="text-sm font-semibold text-ui-ink">گروه آزمون</label>
                        <ElSelect
                            id="performance-exam-group"
                            :model-value="selectedGroup"
                            :disabled="loading"
                            append-to="#profile-overlays"
                            filterable
                            class="w-full"
                            @change="selectGroup"
                        >
                            <ElOption v-for="group in groups" :key="group.id" :label="group.title" :value="group.id" />
                        </ElSelect>
                    </div>
                </PanelCard>

                <div class="grid gap-3 sm:gap-4" aria-live="polite" :aria-busy="loading">
                    <PanelEmpty v-if="!subjects.length" description="هنوز نمره‌ای برای نمایش در این گروه ندارید." />

                    <PanelCard v-for="subject in subjects" :key="subject.key" body-class="[--el-card-padding:0.875rem] sm:[--el-card-padding:1.25rem]">
                        <section :aria-labelledby="'performance-' + subject.key">
                            <h2 :id="'performance-' + subject.key" class="mb-3 wrap-break-word font-bold text-ui-ink">{{ subject.title }}</h2>
                            <ul class="divide-y divide-ui-line border-t border-ui-line">
                                <li v-for="exam in subject.exams" :key="exam.id" class="flex items-baseline justify-between gap-3 py-3 last:pb-0">
                                    <component
                                        :is="learnerName ? 'span' : Link"
                                        :href="learnerName ? undefined : route('exams.show', exam.id)"
                                        class="min-w-0 wrap-break-word text-sm text-ui-ink"
                                        :class="{ 'hover:underline': !learnerName }"
                                    >
                                        {{ exam.title }}
                                    </component>
                                    <span class="shrink-0 text-sm text-ui-muted">
                                        <span class="sr-only">نمره </span>
                                        <strong class="font-bold text-ui-ink">{{ scoreFormat.format(exam.score) }}</strong>
                                        از {{ scoreFormat.format(exam.total_score) }}
                                    </span>
                                </li>
                            </ul>
                        </section>
                    </PanelCard>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
