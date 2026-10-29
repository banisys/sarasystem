<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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
            <Link :href="route('school.scores.index')" class="text-sm text-violet-700 hover:underline">‹ دفتر نمره</Link>
            <h1 class="text-xl font-bold text-gray-900">{{ classroom.title }} · {{ subject.title }}</h1>

            <div class="flex flex-wrap gap-2 text-sm">
                <Link
                    v-for="item in periods"
                    :key="item.id"
                    :href="route('school.scores.show', { classroom: classroom.id, subjectId: subject.id, period: item.id })"
                    class="rounded-lg border px-3 py-1"
                    :class="item.id === period?.id ? 'border-violet-600 bg-violet-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                >
                    {{ item.title }}<span v-if="!item.is_open"> (بسته)</span>
                </Link>
            </div>

            <p v-if="flash" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ flash }}</p>
            <p v-if="refusal" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">{{ refusal }}</p>

            <form v-if="period" class="space-y-2 rounded-2xl bg-white p-5 shadow-sm" @submit.prevent="save">
                <p v-if="!rows.length" class="text-sm text-gray-500">کسی در این کلاس نیست.</p>
                <div v-for="(row, index) in form.rows" :key="row.user_id" class="flex flex-wrap items-center gap-2 border-b border-gray-100 pb-2 text-sm">
                    <span class="w-44 font-medium text-gray-900">{{ row.name }}</span>
                    <input
                        v-if="numeric"
                        v-model="row.score"
                        type="number"
                        min="0"
                        :max="maxScore"
                        step="0.25"
                        :placeholder="`از ${maxScore}`"
                        :disabled="!!refusal"
                        class="w-24 rounded-lg border-gray-300 text-sm"
                    />
                    <select v-else v-model="row.level" :disabled="!!refusal" class="rounded-lg border-gray-300 text-sm">
                        <option value="">—</option>
                        <option v-for="level in levels" :key="level.value" :value="level.value">{{ level.label }}</option>
                    </select>
                    <input v-model="row.note" type="text" maxlength="255" placeholder="توضیح" :disabled="!!refusal" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm" />
                    <p v-if="form.errors[`rows.${index}.score`]" class="w-full text-xs text-red-600">{{ form.errors[`rows.${index}.score`] }}</p>
                </div>
                <p v-if="form.errors.form" class="text-sm text-red-600">{{ form.errors.form }}</p>
                <button
                    v-if="!refusal && rows.length"
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-50"
                >
                    ذخیره‌ی نمره‌ها
                </button>
            </form>
        </div>
    </AppLayout>
</template>
