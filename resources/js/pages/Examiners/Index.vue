<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import Modal from '@/components/Modal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page  = usePage<SharedData>();
const flash = computed(() => page.props.flash ?? {});

interface Department {
    id: number;
    name: string;
}

interface Examiner {
    id: number;
    full_name: string;
    title: string;
    department_id: number;
    department: Department | null;
    projects_count: number;
}

const props = defineProps<{
    examiners: Examiner[];
    departments: Department[];
    filters: { department_id?: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'الممتحنون', href: '/examiners' },
];

// ── Department filter ─────────────────────────────────────────────
const selectedDept = ref<number | ''>(props.filters.department_id ?? '');

function applyFilter() {
    const params = selectedDept.value !== '' ? { department_id: selectedDept.value } : {};
    router.get(route('examiners.index'), params, { preserveState: true, replace: true });
}

// ── Add / Edit modal ──────────────────────────────────────────────
const showModal       = ref(false);
const editingExaminer = ref<Examiner | null>(null);

const form = useForm({
    full_name:     '',
    title:         '',
    department_id: 0 as number,
});

function openAdd() {
    editingExaminer.value = null;
    form.reset();
    showModal.value = true;
}

function openEdit(examiner: Examiner) {
    editingExaminer.value = examiner;
    form.full_name     = examiner.full_name;
    form.title         = examiner.title;
    form.department_id = examiner.department_id;
    showModal.value    = true;
}

function submitForm() {
    const options = {
        onSuccess: () => { showModal.value = false; form.reset(); },
    };
    if (editingExaminer.value) {
        form.put(route('examiners.update', [editingExaminer.value.id]), options);
    } else {
        form.post(route('examiners.store'), options);
    }
}

// ── Delete ────────────────────────────────────────────────────────
const confirmDelete = ref<Examiner | null>(null);

function deleteExaminer() {
    if (!confirmDelete.value) return;
    router.delete(route('examiners.destroy', [confirmDelete.value.id]), {
        onFinish: () => (confirmDelete.value = null),
    });
}
</script>

<template>
    <Head title="الممتحنون" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">إدارة الممتحنين</h1>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="openAdd"
                >
                    + إضافة ممتحن
                </button>
            </div>

            <!-- Flash messages -->
            <div
                v-if="flash.success"
                class="rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash.error"
                class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
            >
                {{ flash.error }}
            </div>

            <!-- Department filter -->
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">تصفية حسب القسم:</label>
                <select
                    v-model="selectedDept"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    @change="applyFilter"
                >
                    <option value="">جميع الأقسام</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                        {{ dept.name }}
                    </option>
                </select>
                <button
                    v-if="selectedDept !== ''"
                    type="button"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    @click="selectedDept = ''; applyFilter()"
                >
                    ✕ إلغاء التصفية
                </button>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الاسم الكامل</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">اللقب العلمي</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">القسم</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">المشاريع</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="examiner in examiners" :key="examiner.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ examiner.full_name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ examiner.title }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ examiner.department?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400">
                                    {{ examiner.projects_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-700 hover:bg-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400"
                                        @click="openEdit(examiner)"
                                    >
                                        تعديل
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded bg-red-100 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/20 dark:text-red-400"
                                        @click="confirmDelete = examiner"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="examiners.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                                {{ selectedDept !== '' ? 'لا يوجد ممتحنون في هذا القسم' : 'لا يوجد ممتحنون مسجلون' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Add / Edit Modal -->
        <Modal
            :show="showModal"
            :title="editingExaminer ? 'تعديل بيانات الممتحن' : 'إضافة ممتحن جديد'"
            @close="showModal = false"
        >
            <form id="examiner-form" class="space-y-4" @submit.prevent="submitForm">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">الاسم الكامل</label>
                    <input
                        v-model="form.full_name"
                        type="text"
                        maxlength="150"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': form.errors.full_name }"
                    />
                    <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-600">{{ form.errors.full_name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">اللقب العلمي</label>
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="100"
                        placeholder="مثال: دكتور، أستاذ مساعد..."
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': form.errors.title }"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">القسم</label>
                    <select
                        v-model="form.department_id"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': form.errors.department_id }"
                    >
                        <option :value="0" disabled>-- اختر القسم --</option>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                            {{ dept.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.department_id" class="mt-1 text-xs text-red-600">{{ form.errors.department_id }}</p>
                </div>
            </form>

            <template #footer>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="showModal = false"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    form="examiner-form"
                    :disabled="form.processing"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ editingExaminer ? 'تحديث' : 'حفظ' }}
                </button>
            </template>
        </Modal>

        <!-- Confirm delete -->
        <ConfirmDelete
            :show="!!confirmDelete"
            :item-name="confirmDelete?.full_name"
            @confirmed="deleteExaminer"
            @cancelled="confirmDelete = null"
        />
    </AppLayout>
</template>
