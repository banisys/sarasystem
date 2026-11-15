<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ElAlert, ElButton, ElCheckbox, ElDialog, ElForm, ElFormItem, ElInput, ElPagination, ElTable, ElTableColumn, ElTag,
} from 'element-plus';
import 'element-plus/es/components/alert/style/css';
import 'element-plus/es/components/button/style/css';
import 'element-plus/es/components/checkbox/style/css';
import 'element-plus/es/components/dialog/style/css';
import 'element-plus/es/components/form/style/css';
import 'element-plus/es/components/input/style/css';
import 'element-plus/es/components/pagination/style/css';
import 'element-plus/es/components/table/style/css';
import 'element-plus/es/components/tag/style/css';
import { ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ banks: Object });

const page = usePage();
const bankDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const editingBank = ref(null);
const deletingBank = ref(null);
const titleInput = ref(null);
const bank = useForm({ title: '', description: '', is_shared: false });
const deletion = useForm({});

function openBank(record = null) {
    bank.resetAndClearErrors();
    editingBank.value = record?.id ?? null;
    if (record) {
        Object.assign(bank, { title: record.title, description: record.description ?? '', is_shared: record.is_shared });
    }
    bankDialogOpen.value = true;
}

function saveBank() {
    if (bank.processing) return;

    const options = { preserveScroll: true, onSuccess: () => { bankDialogOpen.value = false; } };
    if (editingBank.value) bank.patch(route('exams.library.banks.update', editingBank.value), options);
    else bank.post(route('exams.library.banks.store'), options);
}

function confirmRemoval(record) {
    deletion.clearErrors();
    deletingBank.value = record.id;
    deleteDialogOpen.value = true;
}

function removeBank() {
    if (deletion.processing) return;

    deletion.delete(route('exams.library.banks.destroy', deletingBank.value), {
        preserveScroll: true,
        onSuccess: () => { deleteDialogOpen.value = false; },
    });
}

function goToBankPage(number) {
    router.get(route('exams.library'), { banks_page: number });
}
</script>

<template>
    <AppLayout>
        <Head title="بانک سؤال" />
        <div class="space-y-6">
            <UiPageHeader title="بانک سؤال" icon="book" tone="plum" />

            <ElAlert v-if="page.props.flash.status" :title="page.props.flash.status" type="success" :closable="false" show-icon />

            <section class="ui-surface overflow-hidden" aria-label="بانک‌های سؤال">
                <div class="flex justify-end border-b border-ui-line px-4 py-3">
                    <ElButton type="primary" plain aria-haspopup="dialog" @click="openBank()">
                        <span class="inline-flex items-center gap-1.5">
                            <PanelIcon name="plus" class="size-4" />
                            بانک جدید
                        </span>
                    </ElButton>
                </div>

                <ElTable
                    :data="banks.data" row-key="id" empty-text="هنوز بانکی در دسترس نیست."
                    table-layout="auto" native-scrollbar :scrollbar-tabindex="0" flexible
                    aria-label="فهرست بانک‌های سؤال"
                >
                    <ElTableColumn label="عنوان بانک" min-width="172" align="right">
                        <template #default="{ row }">
                            <Link :href="route('exams.library.banks.show', row.id)" class="font-semibold text-brand-700 hover:underline">{{ row.title }}</Link>
                            <p v-if="row.description" class="whitespace-pre-wrap wrap-break-word text-xs text-ui-muted">{{ row.description }}</p>
                        </template>
                    </ElTableColumn>
                    <ElTableColumn label="مالکیت" width="94" align="right">
                        <template #default="{ row }">{{ row.is_mine ? 'بانک من' : 'همکار' }}</template>
                    </ElTableColumn>
                    <ElTableColumn label="اشتراک" width="94" align="right">
                        <template #default="{ row }">
                            <ElTag :type="row.is_shared ? 'success' : 'info'" size="small">{{ row.is_shared ? 'عمومی' : 'خصوصی' }}</ElTag>
                        </template>
                    </ElTableColumn>
                    <ElTableColumn prop="questions_count" label="تعداد سؤال" width="106" align="right" class-name="tabular-nums" />
                    <ElTableColumn label="عملیات" width="218" align="right">
                        <template #default="{ row }">
                            <div class="flex items-center gap-3 whitespace-nowrap">
                                <Link :href="route('exams.library.banks.show', row.id)" class="font-medium text-brand-700 hover:underline">سؤال‌های بانک</Link>
                                <template v-if="row.is_mine">
                                    <ElButton type="primary" link aria-haspopup="dialog" @click="openBank(row)">ویرایش</ElButton>
                                    <ElButton type="danger" link aria-haspopup="dialog" @click="confirmRemoval(row)">حذف</ElButton>
                                </template>
                            </div>
                        </template>
                    </ElTableColumn>
                </ElTable>

                <nav v-if="banks.last_page > 1" class="border-t border-ui-line px-4 py-2" aria-label="صفحه‌های بانک سؤال">
                    <ElPagination
                        :current-page="banks.current_page" :page-size="banks.per_page" :total="banks.total"
                        :pager-count="5" layout="prev, pager, next" prev-text="قبلی" next-text="بعدی" background
                        @update:current-page="goToBankPage"
                    />
                </nav>
            </section>

            <ElDialog
                v-model="bankDialogOpen" :title="editingBank ? 'ویرایش بانک' : 'بانک جدید'"
                width="min(32rem, calc(100% - 2rem))" align-center destroy-on-close
                :show-close="!bank.processing" :close-on-click-modal="false" :close-on-press-escape="!bank.processing"
                @opened="titleInput?.focus()"
            >
                <ElForm label-position="top" :model="bank" :disabled="bank.processing" novalidate @submit.prevent="saveBank">
                    <ElFormItem label="عنوان بانک" prop="title" :error="bank.errors.title">
                        <ElInput ref="titleInput" v-model="bank.title" />
                    </ElFormItem>
                    <ElFormItem label="توضیحات" prop="description" :error="bank.errors.description">
                        <ElInput v-model="bank.description" type="textarea" :rows="3" />
                    </ElFormItem>
                    <ElFormItem prop="is_shared" :error="bank.errors.is_shared">
                        <ElCheckbox v-model="bank.is_shared">عمومی در این شعبه</ElCheckbox>
                    </ElFormItem>
                    <div class="flex flex-wrap gap-2">
                        <ElButton type="primary" native-type="submit" :loading="bank.processing">ذخیرهٔ بانک</ElButton>
                        <ElButton @click="bankDialogOpen = false">انصراف</ElButton>
                    </div>
                </ElForm>
            </ElDialog>

            <ElDialog
                v-model="deleteDialogOpen" title="حذف بانک" width="min(26rem, calc(100% - 2rem))" align-center destroy-on-close
                :show-close="!deletion.processing" :close-on-click-modal="false" :close-on-press-escape="!deletion.processing"
            >
                <p class="text-sm">بانک حذف شود؟ سؤال‌های آن باقی می‌مانند.</p>
                <ElAlert v-if="deletion.errors.delete_bank" class="mt-3" :title="deletion.errors.delete_bank" type="error" :closable="false" />
                <template #footer>
                    <div class="flex flex-wrap gap-2">
                        <ElButton :disabled="deletion.processing" @click="deleteDialogOpen = false">انصراف</ElButton>
                        <ElButton type="danger" :loading="deletion.processing" @click="removeBank">حذف بانک</ElButton>
                    </div>
                </template>
            </ElDialog>
        </div>
    </AppLayout>
</template>
