<script setup>
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// One subject's scores in a class for a score period (ADR 0032): a number
// out of 20 or a descriptive level, by the stage's scale, and a note.
const props = defineProps({
    classroom: { type: Object, required: true },
    subject: { type: Object, required: true },
    scale: { type: String, required: true },
    maxScore: { type: Number, required: true },
    periods: { type: Array, required: true },
    period: { type: Object, default: null },
    // Why the scores can't be changed now (closed, published, …), or null.
    refusal: { type: String, default: null },
    levels: { type: Array, required: true },
    rows: { type: Array, required: true },
});

const flash = computed(() => usePage().props.flash.status);
const numeric = computed(() => props.scale === 'numeric');
const form = useForm({ period_id: props.period?.id, rows: props.rows.map((row) => ({ ...row, score: row.score ?? '' })) });

function save() {
    form.post(route('school.scores.store', { classroom: props.classroom.id, subjectId: props.subject.id }), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`دفتر نمره · ${classroom.title}`" />

    <AppLayout>
        <div class="space-y-4">
            <UiPageHeader :title="subject.title" :description="classroom.title" icon="book" :back-href="route('school.scores.index')" back-label="دفتر نمره" />

            <nav v-if="periods.length" class="ui-surface flex flex-wrap gap-2 p-3" aria-label="دوره‌های نمره‌دهی">
                <UiButton
                    v-for="item in periods"
                    :key="item.id"
                    :href="route('school.scores.show', { classroom: classroom.id, subjectId: subject.id, period: item.id })"
                    :variant="item.id === period?.id ? 'primary' : 'outline'"
                    :aria-current="item.id === period?.id ? 'true' : undefined"
                >
                    {{ item.title }}<span v-if="!item.is_open"> (بسته)</span>
                </UiButton>
            </nav>

            <UiNotice v-if="flash" tone="success">{{ flash }}</UiNotice>
            <UiNotice v-if="refusal">{{ refusal }}</UiNotice>

            <form v-if="period" class="ui-surface space-y-3 p-4 sm:p-5" @submit.prevent="save">
                <p v-if="!rows.length" class="text-sm text-gray-500">کسی در این کلاس نیست.</p>
                <div v-for="(row, index) in form.rows" :key="row.user_id" class="grid items-center gap-x-3 gap-y-2 rounded-ui border border-ui-line p-3 text-sm sm:grid-cols-[minmax(0,1fr)_minmax(7rem,0.7fr)_minmax(0,1.5fr)]">
                    <span class="font-semibold text-ui-ink">{{ row.name }}</span>
                    <input
                        v-if="numeric"
                        v-model="row.score"
                        type="number"
                        min="0"
                        :max="maxScore"
                        step="0.25"
                        :placeholder="`از ${maxScore}`"
                        :disabled="!!refusal"
                        :aria-label="`نمرهٔ ${row.name}`"
                        :aria-invalid="!!form.errors[`rows.${index}.score`]"
                        dir="ltr"
                        class="ui-field mt-0"
                    />
                    <select v-else v-model="row.level" :disabled="!!refusal" :aria-label="`ارزشیابی ${row.name}`" :aria-invalid="!!form.errors[`rows.${index}.level`]" class="ui-field mt-0">
                        <option value="">—</option>
                        <option v-for="level in levels" :key="level.value" :value="level.value">{{ level.label }}</option>
                    </select>
                    <input v-model="row.note" type="text" maxlength="255" placeholder="توضیح" :disabled="!!refusal" :aria-label="`توضیح برای ${row.name}`" :aria-invalid="!!form.errors[`rows.${index}.note`]" class="ui-field mt-0" />
                    <p v-for="field in ['score', 'level', 'note']" :key="field" v-show="form.errors[`rows.${index}.${field}`]" class="ui-field-error sm:col-span-3" role="alert">
                        {{ form.errors[`rows.${index}.${field}`] }}
                    </p>
                </div>
                <UiNotice v-if="form.errors.form" tone="error">{{ form.errors.form }}</UiNotice>
                <UiButton v-if="!refusal && rows.length" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'در حال ذخیره…' : 'ذخیره‌ی نمره‌ها' }}
                </UiButton>
            </form>
        </div>
    </AppLayout>
</template>
