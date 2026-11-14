<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import UiNotice from '@/Components/Ui/UiNotice.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';

defineProps({ banks: Object });
const page = usePage();
const dialog = ref(null);
const editingBank = ref(null);
const bank = useForm({ title: '', description: '', is_shared: false });
const deletion = useForm({});
const secondary = 'inline-flex min-h-9 items-center text-sm font-medium text-brand-700 hover:underline';

function openBank(record = null) {
    bank.resetAndClearErrors();
    editingBank.value = record?.id ?? null;
    if (record) {
        Object.assign(bank, { title: record.title, description: record.description ?? '', is_shared: record.is_shared });
    }
    dialog.value.showModal();
}

function saveBank() {
    const options = { preserveScroll: true, onSuccess: () => dialog.value?.close() };
    if (editingBank.value) bank.patch(route('exams.library.banks.update', editingBank.value), options);
    else bank.post(route('exams.library.banks.store'), options);
}

function removeBank(id) {
    if (!window.confirm('بانک حذف شود؟ سؤال‌های آن باقی می‌مانند.')) return;
    deletion.delete(route('exams.library.banks.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="بانک سؤال" />
        <div class="space-y-6">
            <UiPageHeader title="بانک سؤال" icon="book" tone="plum">
                <template #actions>
                    <UiButton :href="route('exams.library.questions')" variant="outline">همهٔ سؤال‌ها</UiButton>
                    <UiButton variant="outline" aria-haspopup="dialog" @click="openBank()">بانک جدید</UiButton>
                </template>
            </UiPageHeader>

            <UiNotice v-if="page.props.flash.status" tone="success">{{ page.props.flash.status }}</UiNotice>
            <p v-for="(error, key) in deletion.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>

            <section class="ui-surface overflow-hidden" aria-label="بانک‌های سؤال">
                <div v-if="banks.data.length" class="overflow-x-auto">
                    <table class="w-full divide-y divide-ui-line text-start text-sm">
                        <thead class="bg-ui-canvas text-ui-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-start">عنوان بانک</th>
                                <th scope="col" class="px-4 py-3 text-start whitespace-nowrap">مالکیت</th>
                                <th scope="col" class="px-4 py-3 text-start whitespace-nowrap">اشتراک</th>
                                <th scope="col" class="px-4 py-3 text-start whitespace-nowrap">تعداد سؤال</th>
                                <th scope="col" class="px-4 py-3 text-start">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ui-line">
                            <tr v-for="record in banks.data" :key="record.id">
                                <th scope="row" class="min-w-40 px-4 py-2 text-start font-medium">
                                    <Link :href="route('exams.library.banks.show', record.id)" :class="secondary">{{ record.title }}</Link>
                                    <p v-if="record.description" class="whitespace-pre-wrap wrap-break-word text-xs font-normal text-ui-muted">{{ record.description }}</p>
                                </th>
                                <td class="px-4 py-2 whitespace-nowrap">{{ record.is_mine ? 'بانک من' : 'همکار' }}</td>
                                <td class="px-4 py-2">{{ record.is_shared ? 'عمومی' : 'خصوصی' }}</td>
                                <td class="px-4 py-2 tabular-nums">{{ record.questions_count }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3 whitespace-nowrap">
                                        <Link :href="route('exams.library.banks.show', record.id)" :class="secondary">سؤال‌های بانک</Link>
                                        <button v-if="record.is_mine" type="button" :class="secondary" aria-haspopup="dialog" @click="openBank(record)">ویرایش</button>
                                        <button v-if="record.is_mine" type="button" :disabled="deletion.processing" class="text-red-700 hover:underline" @click="removeBank(record.id)">حذف</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-4 text-sm text-ui-muted">هنوز بانکی در دسترس نیست.</p>
                <nav v-if="banks.prev_page_url || banks.next_page_url" class="flex gap-4 border-t border-ui-line px-4 py-2" aria-label="صفحه‌های بانک سؤال">
                    <Link v-if="banks.prev_page_url" :href="banks.prev_page_url" :class="secondary">قبلی</Link>
                    <Link v-if="banks.next_page_url" :href="banks.next_page_url" :class="secondary">بعدی</Link>
                </nav>
            </section>

            <dialog ref="dialog" aria-labelledby="bank-dialog-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-auto rounded-ui bg-white p-5 text-ui-ink shadow-xl backdrop:bg-black/50">
                <form class="space-y-4" novalidate @submit.prevent="saveBank">
                    <h2 id="bank-dialog-title" class="text-lg font-bold">{{ editingBank ? 'ویرایش بانک' : 'بانک جدید' }}</h2>
                    <label class="block space-y-1 text-sm">
                        <span>عنوان بانک</span>
                        <input v-model="bank.title" class="ui-field" autofocus />
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span>توضیحات</span>
                        <textarea v-model="bank.description" rows="3" class="ui-field" />
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="bank.is_shared" type="checkbox" /> عمومی در این شعبه
                    </label>
                    <p v-for="(error, key) in bank.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton type="submit" :disabled="bank.processing">ذخیرهٔ بانک</UiButton>
                        <UiButton variant="outline" @click="dialog.close()">انصراف</UiButton>
                    </div>
                </form>
            </dialog>
        </div>
    </AppLayout>
</template>
