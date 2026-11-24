<script setup>
import { ElInputNumber } from 'element-plus';
import 'element-plus/es/components/input-number/style/css';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelDialog from '@/Components/Ui/PanelDialog.vue';
import PanelFormField from '@/Components/Ui/PanelFormField.vue';
import PanelIcon from '@/Components/PanelIcon.vue';

const props = defineProps({
    seating: { type: Object, required: true },
    url: { type: String, required: true },
});

const open = ref(false);
const selected = ref(null);
const dragging = ref(null);
const dropSeat = ref(null);
const form = useForm({ layout: props.seating.layout });
const names = computed(() => new Map(props.seating.attendees.map(({ id, name }) => [id, name])));
const assigned = computed(() => new Set(form.layout.seats));
const unassigned = computed(() => props.seating.attendees.filter(({ id }) => !assigned.value.has(id)));
const number = new Intl.NumberFormat('fa-IR', { useGrouping: false });

function show() {
    form.defaults('layout', props.seating.layout).resetAndClearErrors();
    selected.value = null;
    endDrag();
    open.value = true;
}

function save() {
    form.patch(props.url, { preserveScroll: true, onSuccess: () => { open.value = false; } });
}

function resize(dimension, value) {
    if (!Number.isInteger(value)) return;

    const previous = form.layout;
    const rows = dimension === 'rows' ? value : previous.rows;
    const columns = dimension === 'columns' ? value : previous.columns;
    const seats = Array.from({ length: rows * columns }, (_, index) => {
        const row = Math.floor(index / columns);
        const column = index % columns;
        return row < previous.rows && column < previous.columns
            ? previous.seats[row * previous.columns + column] : null;
    });
    form.layout = { rows, columns, seats };
}

function chooseSeat(index) {
    if (selected.value === null) {
        selected.value = form.layout.seats[index];
    } else {
        place(selected.value, index);
    }
}

function place(id, index) {
    if (!names.value.has(id)) return;

    const seats = [...form.layout.seats];
    const origin = seats.indexOf(id);
    if (origin !== -1) seats[origin] = seats[index];
    seats[index] = id;
    form.layout.seats = seats;
    selected.value = null;
    endDrag();
}

function remove(id) {
    if (id === null) return;

    form.layout.seats = form.layout.seats.map((seat) => seat === id ? null : seat);
    selected.value = null;
    endDrag();
}

function fill() {
    form.layout.seats = form.layout.seats.map((_, index) => props.seating.attendees[index]?.id ?? null);
    selected.value = null;
}

function clear() {
    form.layout.seats = form.layout.seats.map(() => null);
    selected.value = null;
}

function startDrag(event, id) {
    dragging.value = id;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(id));
}

function previewDrop(event, index) {
    if (dragging.value === null) return;

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dropSeat.value = index;
}

function endDrag() {
    dragging.value = null;
    dropSeat.value = null;
}
</script>

<template>
    <div>
        <PanelButton variant="outline-brand" aria-haspopup="dialog" @click="show">
            <PanelIcon name="users" class="size-5" />
            چیدمان صندلی‌ها
        </PanelButton>
        <PanelDialog v-model="open" title="چیدمان صندلی‌ها" width="min(74rem, calc(100% - 2rem))" :busy="form.processing">
            <form class="space-y-4" @submit.prevent="save">
                <fieldset :disabled="form.processing" class="min-w-0 space-y-4">
                    <p class="text-sm text-ui-muted">نام یک نفر یا صندلی او را انتخاب کنید، سپس صندلی مقصد را بزنید. می‌توانید نام افراد را هم روی صندلی بکشید.</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <PanelFormField label="تعداد ردیف‌ها" for="seating-rows">
                            <ElInputNumber id="seating-rows" :model-value="form.layout.rows" :min="1" :max="seating.max_rows"
                                :value-on-clear="form.layout.rows" :precision="0" @change="resize('rows', $event)" />
                        </PanelFormField>
                        <PanelFormField label="صندلی در هر ردیف" for="seating-columns">
                            <ElInputNumber id="seating-columns" :model-value="form.layout.columns" :min="1" :max="seating.max_columns"
                                :value-on-clear="form.layout.columns" :precision="0" @change="resize('columns', $event)" />
                        </PanelFormField>
                        <PanelButton variant="outline" :disabled="!seating.attendees.length" @click="fill">چیدمان خودکار</PanelButton>
                        <PanelButton variant="outline" :disabled="!form.layout.seats.some((id) => id !== null)" @click="clear">خالی کردن همهٔ صندلی‌ها</PanelButton>
                    </div>
                    <div class="flex min-h-6 flex-wrap items-center gap-2 text-sm">
                        <span aria-live="polite">{{ selected === null ? 'نفر یا صندلی را انتخاب کنید.' : 'انتخاب شده: ' + names.get(selected) }}</span>
                        <PanelButton v-if="selected !== null" variant="link" @click="selected = null">لغو انتخاب</PanelButton>
                        <PanelButton v-if="selected !== null && assigned.has(selected)" variant="link" @click="remove(selected)">خالی کردن صندلی</PanelButton>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem]">
                        <div class="min-w-0 overflow-auto rounded-ui border border-ui-line p-3">
                            <div class="mb-3 rounded-ui bg-ui-canvas p-2 text-center text-sm text-ui-muted">جلوی سالن · جای مراقبان</div>
                            <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${form.layout.columns}, minmax(6.5rem, 1fr))` }">
                                <button v-for="(id, index) in form.layout.seats" :key="index" type="button"
                                    class="grid min-h-16 content-center gap-1 rounded-ui border border-ui-line px-2 py-1 text-sm focus-visible:outline-2 focus-visible:outline-brand-500 aria-pressed:ring-2 aria-pressed:ring-brand-500"
                                    :class="[id === null ? 'border-dashed text-ui-muted' : 'cursor-grab bg-ui-canvas text-ui-ink', { 'outline-2 outline-offset-2 outline-red-500/70 bg-red-50! panel-dark:bg-red-950/40!': dropSeat === index }]"
                                    :aria-pressed="id !== null && selected === id" :draggable="id !== null"
                                    @click="chooseSeat(index)" @dragstart="id !== null && startDrag($event, id)" @dragend="endDrag"
                                    @dragover="previewDrop($event, index)" @dragleave="dropSeat === index && (dropSeat = null)"
                                    @drop.prevent="place(dragging, index)">
                                    <span class="pointer-events-none text-xs">صندلی {{ number.format(index + 1) }}</span>
                                    <span class="pointer-events-none wrap-anywhere font-medium">{{ id === null ? 'خالی' : names.get(id) }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="rounded-ui border border-ui-line p-3" @dragover.prevent @drop.prevent="remove(dragging)">
                            <h3 class="mb-2 text-sm font-medium">بدون صندلی ({{ number.format(unassigned.length) }})</h3>
                            <div v-if="unassigned.length" class="flex max-h-80 flex-wrap gap-2 overflow-auto">
                                <button v-for="person in unassigned" :key="person.id" type="button" draggable="true"
                                    class="cursor-grab rounded-ui bg-ui-canvas px-2 py-1 text-start text-sm aria-pressed:ring-2 aria-pressed:ring-brand-500"
                                    :aria-pressed="selected === person.id" @click="selected = selected === person.id ? null : person.id"
                                    @dragstart="startDrag($event, person.id)" @dragend="endDrag">
                                    {{ person.name }}
                                </button>
                            </div>
                            <p v-else class="text-xs text-ui-muted">همهٔ شرکت‌کنندگان صندلی دارند.</p>
                        </div>
                    </div>
                </fieldset>
                <p v-if="form.errors.form" role="alert" class="text-sm text-red-700 panel-dark:text-red-300">{{ form.errors.form }}</p>
                <div class="flex flex-wrap justify-end gap-2">
                    <PanelButton variant="outline" :disabled="form.processing" @click="open = false">انصراف</PanelButton>
                    <PanelButton type="submit" :loading="form.processing">ذخیرهٔ چیدمان</PanelButton>
                </div>
            </form>
        </PanelDialog>
    </div>
</template>
