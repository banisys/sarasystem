<script setup>
import JalaliDateInput from '@/Components/JalaliDateInput.vue';

// One field an organization added to a customizable form (ADR 0017); `field` is
// App\CustomFields\CustomField::toArray(). The answer is sent as custom[field.key].
const props = defineProps({
    field: { type: Object, required: true },
    error: { type: String, default: null },
    inputClass: { type: String, default: '' },
    labelClass: { type: String, default: '' },
    errorClass: { type: String, default: '' },
});

// '' for text, number, date and select; an array for multi_select; a boolean for checkbox.
const model = defineModel({ default: '' });

const id = `custom-${props.field.key}`;

function toggleOption(option, checked) {
    const selected = Array.isArray(model.value) ? model.value.filter((value) => value !== option) : [];
    model.value = checked ? [...selected, option] : selected;
}
</script>

<template>
    <div :class="{ 'sm:col-span-2': field.type === 'textarea' || field.type === 'multi_select' || field.type === 'checkbox' }">
        <label v-if="field.type === 'checkbox'" :for="id" class="flex items-center gap-2 text-sm text-gray-700">
            <input :id="id" type="checkbox" :checked="!!model" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" @change="model = $event.target.checked" />
            {{ field.label }}
        </label>
        <template v-else>
            <label :for="id" :class="labelClass">{{ field.label }}{{ field.required ? '' : ' (اختیاری)' }}</label>

            <textarea v-if="field.type === 'textarea'" :id="id" v-model="model" rows="3" maxlength="2000" :class="inputClass" />
            <input v-else-if="field.type === 'number'" :id="id" v-model="model" type="text" inputmode="decimal" dir="ltr" :class="inputClass" />
            <JalaliDateInput v-else-if="field.type === 'date'" :id="id" v-model="model" :input-class="inputClass" />
            <select v-else-if="field.type === 'select'" :id="id" v-model="model" :class="inputClass">
                <option value="">انتخاب کنید</option>
                <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
            </select>
            <div v-else-if="field.type === 'multi_select'" :id="id" class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                <label v-for="option in field.options" :key="option" class="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        :checked="Array.isArray(model) && model.includes(option)"
                        class="rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                        @change="toggleOption(option, $event.target.checked)"
                    />
                    {{ option }}
                </label>
            </div>
            <input v-else :id="id" v-model="model" type="text" maxlength="255" :class="inputClass" />
        </template>

        <p v-if="field.help" class="mt-1 text-xs text-gray-500">{{ field.help }}</p>
        <p v-if="error" :class="errorClass">{{ error }}</p>
    </div>
</template>
