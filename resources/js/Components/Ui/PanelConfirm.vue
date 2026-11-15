<script setup>
import PanelButton from './PanelButton.vue';
import PanelDialog from './PanelDialog.vue';

const open = defineModel({ type: Boolean, required: true });
defineProps({ title: String, confirmLabel: String, busy: Boolean, danger: Boolean });
defineEmits(['confirm']);
</script>

<template>
    <PanelDialog v-model="open" :title="title" :busy="busy" width="min(28rem, calc(100% - 2rem))">
        <slot />
        <template #footer>
            <div class="flex flex-wrap gap-2">
                <PanelButton variant="outline" :disabled="busy" @click="open = false">انصراف</PanelButton>
                <PanelButton :variant="danger ? 'danger' : 'primary'" :loading="busy" @click="$emit('confirm')">
                    {{ confirmLabel }}
                </PanelButton>
            </div>
        </template>
    </PanelDialog>
</template>
