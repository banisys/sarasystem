<script setup>
import JalaliDateInput from '@/Components/JalaliDateInput.vue';

// Documents an applicant uploads in a public form (ADR 0019); `fields` come from
// App\Documents\ApplicantDocuments::fields(). Files are sent as documents[type id][],
// expiry dates as document_expiry[type id].
const props = defineProps({
    fields: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
    inputClass: { type: String, default: '' },
    labelClass: { type: String, default: '' },
    errorClass: { type: String, default: '' },
});

const files = defineModel('files', { type: Object, required: true });
const expiry = defineModel('expiry', { type: Object, required: true });

function onPicked(field, event) {
    files.value[field.id] = Array.from(event.target.files).slice(0, field.maxFiles);
}

function errorFor(field, key = 'documents') {
    return Object.entries(props.errors).find(([name]) => name === `${key}.${field.id}` || name.startsWith(`${key}.${field.id}.`))?.[1];
}
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div v-for="field in fields" :key="field.id" class="rounded-xl border border-dashed border-gray-300 p-4 sm:col-span-2">
            <label :for="`document-${field.id}`" :class="labelClass">{{ field.title }}{{ field.required ? '' : ' (اختیاری)' }}</label>
            <p v-if="field.description" class="mt-1 text-xs text-gray-500">{{ field.description }}</p>
            <input
                :id="`document-${field.id}`"
                type="file"
                :accept="field.accept"
                :multiple="field.maxFiles > 1"
                :class="inputClass"
                @change="onPicked(field, $event)"
            />
            <p class="mt-1 text-xs text-gray-500">
                {{ field.maxFiles > 1 ? `تا ${field.maxFiles} فایل، ` : '' }}هر فایل حداکثر {{ Math.round((field.maxKilobytes / 1024) * 10) / 10 }} مگابایت
            </p>
            <p v-if="errorFor(field)" :class="errorClass">{{ errorFor(field) }}</p>
            <div v-if="field.hasExpiry" class="mt-3 max-w-xs">
                <label :for="`document-expiry-${field.id}`" :class="labelClass">تاریخ انقضا</label>
                <JalaliDateInput :id="`document-expiry-${field.id}`" v-model="expiry[field.id]" :input-class="inputClass" />
                <p v-if="errorFor(field, 'document_expiry')" :class="errorClass">{{ errorFor(field, 'document_expiry') }}</p>
            </div>
        </div>
    </div>
</template>
