<script setup>
import { computed, ref } from 'vue';
import { useHttp } from '@inertiajs/vue3';
import PanelIcon from '@/Components/PanelIcon.vue';

const props = defineProps({ exam: { type: Object, required: true } });
const dialog = ref(null);
const trigger = ref(null);
const questions = ref([]);
const editable = ref(false);
const shuffled = ref(false);
const dragged = ref(null);
const error = ref('');
const notice = ref('');
const changed = ref(false);
const preview = useHttp();
const reorder = useHttp({ order: [], revision: '' });
const canMove = computed(() => editable.value && !reorder.processing);
let requestId = 0;

async function open() {
    const currentRequest = ++requestId;
    questions.value = [];
    editable.value = false;
    shuffled.value = false;
    error.value = '';
    notice.value = '';
    changed.value = false;
    dialog.value.showModal();

    try {
        const data = await preview.get(props.exam.preview_url);
        if (currentRequest !== requestId) return;
        questions.value = data.questions;
        editable.value = data.can_reorder;
        shuffled.value = data.shuffled;
        reorder.revision = data.revision;
    } catch {
        if (currentRequest === requestId) error.value = 'دریافت پیش‌نمایش ممکن نشد. دوباره تلاش کنید.';
    }
}

function close() {
    requestId++;
    preview.cancel();
    dragged.value = null;
    trigger.value?.focus();
}

function move(from, to) {
    if (!canMove.value || from === to || from < 0 || to < 0 || to >= questions.value.length) return;
    const [question] = questions.value.splice(from, 1);
    questions.value.splice(to, 0, question);
    changed.value = true;
    notice.value = '';
}

function startDrag(event, index) {
    if (!canMove.value) return;
    dragged.value = index;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(index));
}

function drop(index) {
    if (dragged.value !== null) move(dragged.value, index);
    dragged.value = null;
}

async function save() {
    reorder.order = questions.value.map((question) => question.index);
    error.value = '';
    try {
        const result = await reorder.patch(props.exam.preview_url, {
            onError: (errors) => { error.value = Object.values(errors)[0]; },
        });
        if (!result) return;
        notice.value = result.message;
        changed.value = false;
        shuffled.value = false;
        reorder.revision = result.revision;
        questions.value = questions.value.map((question, index) => ({ ...question, index }));
    } catch {
        error.value = 'ذخیرهٔ ترتیب ممکن نشد. دوباره تلاش کنید.';
    }
}
</script>

<template>
    <div>
        <button ref="trigger" type="button" title="پیش‌نمایش سؤال‌ها" aria-label="پیش‌نمایش سؤال‌ها" aria-haspopup="dialog"
            class="grid size-10 place-items-center rounded-lg border border-violet-200 text-violet-700 hover:bg-violet-50" @click="open">
            <PanelIcon name="clipboard" class="size-5" />
        </button>
        <dialog ref="dialog" :aria-labelledby="`exam-preview-${exam.id}`"
            class="m-auto w-[calc(100%_-_2rem)] max-w-2xl rounded-2xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/45"
            @cancel="reorder.processing && $event.preventDefault()" @close="close">
            <div class="flex max-h-[80dvh] flex-col">
                <header class="flex items-start justify-between gap-3 border-b border-gray-200 p-4">
                    <div>
                        <h2 :id="`exam-preview-${exam.id}`" class="font-semibold">پیش‌نمایش سؤال‌ها</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ exam.title }}</p>
                    </div>
                    <button type="button" autofocus aria-label="بستن" :disabled="reorder.processing" class="grid size-9 shrink-0 place-items-center rounded-lg hover:bg-gray-100"
                        @click="dialog.close()">
                        <PanelIcon name="close" class="size-5" />
                    </button>
                </header>
                <div class="min-h-0 space-y-3 overflow-y-auto p-4">
                    <p v-if="preview.processing" class="text-sm text-gray-600" role="status">در حال دریافت سؤال‌ها…</p>
                    <p v-if="error" class="text-sm text-red-700" role="alert">{{ error }}</p>
                    <p v-if="editable && questions.length > 1" class="text-sm text-gray-600">برای تغییر ترتیب، سؤال‌ها را بکشید یا از فلش‌های کنارشان استفاده کنید.</p>
                    <p v-if="shuffled" class="text-sm text-gray-600">ترتیب نمایش برای شرکت‌کنندگان تصادفی است. ذخیرهٔ ترتیب، نمایش را ثابت می‌کند.</p>
                    <ol class="space-y-2">
                        <li v-for="(question, index) in questions" :key="question.index"
                            class="flex items-start gap-3 rounded-lg border border-gray-200 p-3"
                            :class="{ 'cursor-grab': canMove && questions.length > 1, 'opacity-50': dragged === index }"
                            :draggable="canMove && questions.length > 1"
                            @dragstart="startDrag($event, index)" @dragend="dragged = null" @dragover.prevent @drop.prevent="drop(index)">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gray-100 text-sm">{{ index + 1 }}</span>
                            <div class="min-w-0 flex-1 space-y-1">
                                <p class="whitespace-pre-wrap break-words text-sm">{{ question.body }}</p>
                                <ul v-if="question.options.length" class="list-disc space-y-1 ps-5 text-sm text-gray-700">
                                    <li v-for="(option, optionIndex) in question.options" :key="optionIndex" class="break-words">{{ option }}</li>
                                </ul>
                                <p class="text-xs text-gray-600">{{ question.type }} · {{ question.score }} نمره</p>
                            </div>
                            <div v-if="editable && questions.length > 1" class="flex shrink-0 gap-1">
                                <button type="button" :disabled="!canMove || index === 0" :aria-label="`سؤال ${index + 1} به بالا`"
                                    class="grid size-8 place-items-center rounded border border-gray-200 hover:bg-gray-100 disabled:opacity-30"
                                    @click="move(index, index - 1)">↑</button>
                                <button type="button" :disabled="!canMove || index === questions.length - 1" :aria-label="`سؤال ${index + 1} به پایین`"
                                    class="grid size-8 place-items-center rounded border border-gray-200 hover:bg-gray-100 disabled:opacity-30"
                                    @click="move(index, index + 1)">↓</button>
                            </div>
                        </li>
                    </ol>
                </div>
                <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 p-4">
                    <span v-if="notice" role="status" class="me-auto text-sm text-emerald-700">{{ notice }}</span>
                    <button type="button" :disabled="reorder.processing" class="text-sm text-gray-700" @click="dialog.close()">بستن</button>
                    <button v-if="editable && questions.length > 1" type="button" :disabled="(!changed && !shuffled) || reorder.processing"
                        class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" @click="save">ذخیرهٔ ترتیب</button>
                </footer>
            </div>
        </dialog>
    </div>
</template>
