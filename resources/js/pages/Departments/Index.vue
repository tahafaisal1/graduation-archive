<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import Modal from '@/components/Modal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();
const flash = computed(() => page.props.flash ?? {});
const authUser = computed(() => page.props.auth.user);
const isSuperAdmin = computed(() => authUser.value?.role === 'super_admin');
const canEditDepartment = (dept: Department) =>
    isSuperAdmin.value || authUser.value?.department_id === dept.id;
const canManageSpecs = (dept: Department) =>
    isSuperAdmin.value || authUser.value?.department_id === dept.id;

interface Specialization {
    id: number;
    name: string;
    department_id: number;
}

interface Department {
    id: number;
    name: string;
    code: string;
    description: string | null;
    specializations_count: number;
    specializations?: Specialization[];
}

defineProps<{
    departments: Department[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'الأقسام', href: '/departments' },
];

// ── Department modal ──────────────────────────────────────────────
const showDeptModal = ref(false);
const deptForm = useForm({ name: '', code: '', description: '' });

function openAddDept() {
    deptForm.reset();
    showDeptModal.value = true;
}

function submitDept() {
    deptForm.post(route('departments.store'), {
        onSuccess: () => { showDeptModal.value = false; deptForm.reset(); },
    });
}

// ── Delete department ─────────────────────────────────────────────
const confirmDeptDelete = ref<Department | null>(null);

function deleteDept() {
    if (!confirmDeptDelete.value) return;
    router.delete(route('departments.destroy', [confirmDeptDelete.value.id]), {
        onFinish: () => (confirmDeptDelete.value = null),
    });
}

// ── Specialization modal ──────────────────────────────────────────
const showSpecModal = ref(false);
const editingSpec = ref<Specialization | null>(null);
const specForm = useForm({ name: '', department_id: 0 });

function openAddSpec(dept: Department) {
    editingSpec.value = null;
    specForm.reset();
    specForm.department_id = dept.id;
    showSpecModal.value = true;
}

function openEditSpec(spec: Specialization) {
    editingSpec.value = spec;
    specForm.name = spec.name;
    specForm.department_id = spec.department_id;
    showSpecModal.value = true;
}

function submitSpec() {
    if (editingSpec.value) {
        specForm.put(route('specializations.update', [editingSpec.value.id]), {
            onSuccess: () => { showSpecModal.value = false; specForm.reset(); },
        });
    } else {
        specForm.post(route('specializations.store'), {
            onSuccess: () => { showSpecModal.value = false; specForm.reset(); },
        });
    }
}

// ── Delete specialization ─────────────────────────────────────────
const confirmSpecDelete = ref<Specialization | null>(null);

function deleteSpec() {
    if (!confirmSpecDelete.value) return;
    router.delete(route('specializations.destroy', [confirmSpecDelete.value.id]), {
        onFinish: () => (confirmSpecDelete.value = null),
    });
}

// ── Expanded rows ─────────────────────────────────────────────────
const expandedDepts = ref<Set<number>>(new Set());

function toggleExpand(id: number) {
    if (expandedDepts.value.has(id)) {
        expandedDepts.value.delete(id);
    } else {
        expandedDepts.value.add(id);
        router.reload({ only: ['departments'] });
    }
}
</script>

<template>
    <Head title="الأقسام" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">إدارة الأقسام</h1>
                <button
                    v-if="isSuperAdmin"
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="openAddDept"
                >
                    + إضافة قسم
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

            <!-- Departments Table -->
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="w-8 px-4 py-3" />
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">اسم القسم</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الرمز</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">عدد التخصصات</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <template v-for="dept in departments" :key="dept.id">
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="text-gray-400 hover:text-gray-600"
                                        @click="toggleExpand(dept.id)"
                                    >
                                        {{ expandedDepts.has(dept.id) ? '▲' : '▼' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ dept.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ dept.code }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ dept.specializations_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <a
                                            v-if="canEditDepartment(dept)"
                                            :href="route('departments.edit', [dept.id])"
                                            class="rounded bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-700 hover:bg-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400"
                                        >
                                            تعديل
                                        </a>
                                        <button
                                            v-if="isSuperAdmin"
                                            type="button"
                                            class="rounded bg-red-100 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/20 dark:text-red-400"
                                            @click="confirmDeptDelete = dept"
                                        >
                                            حذف
                                        </button>
                                        <button
                                            v-if="canManageSpecs(dept)"
                                            type="button"
                                            class="rounded bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900/20 dark:text-blue-400"
                                            @click="openAddSpec(dept)"
                                        >
                                            + تخصص
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Specializations sub-row -->
                            <tr v-if="expandedDepts.has(dept.id)" class="bg-gray-50 dark:bg-gray-800/50">
                                <td />
                                <td colspan="4" class="px-8 py-3">
                                    <div v-if="dept.specializations && dept.specializations.length > 0" class="space-y-1">
                                        <div
                                            v-for="spec in dept.specializations"
                                            :key="spec.id"
                                            class="flex items-center justify-between rounded border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900"
                                        >
                                            <span class="text-gray-800 dark:text-gray-200">{{ spec.name }}</span>
                                            <div class="flex gap-2">
                                                <button
                                                    v-if="canManageSpecs(dept)"
                                                    type="button"
                                                    class="text-xs text-yellow-600 hover:underline"
                                                    @click="openEditSpec(spec)"
                                                >
                                                    تعديل
                                                </button>
                                                <button
                                                    v-if="canManageSpecs(dept)"
                                                    type="button"
                                                    class="text-xs text-red-600 hover:underline"
                                                    @click="confirmSpecDelete = spec"
                                                >
                                                    حذف
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-500">لا توجد تخصصات</p>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="departments.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">لا توجد أقسام</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Add Department Modal -->
            <Modal :show="showDeptModal" title="إضافة قسم جديد" @close="showDeptModal = false">
                <form id="dept-form" @submit.prevent="submitDept" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">اسم القسم</label>
                        <input
                            v-model="deptForm.name"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            :class="{ 'border-red-500': deptForm.errors.name }"
                        />
                        <p v-if="deptForm.errors.name" class="mt-1 text-xs text-red-600">{{ deptForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">الرمز</label>
                        <input
                            v-model="deptForm.code"
                            type="text"
                            maxlength="10"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            :class="{ 'border-red-500': deptForm.errors.code }"
                        />
                        <p v-if="deptForm.errors.code" class="mt-1 text-xs text-red-600">{{ deptForm.errors.code }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">الوصف</label>
                        <textarea
                            v-model="deptForm.description"
                            rows="3"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>
                </form>
                <template #footer>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
                        @click="showDeptModal = false"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        form="dept-form"
                        :disabled="deptForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        حفظ
                    </button>
                </template>
            </Modal>

            <!-- Add/Edit Specialization Modal -->
            <Modal :show="showSpecModal" :title="editingSpec ? 'تعديل التخصص' : 'إضافة تخصص'" @close="showSpecModal = false">
                <form id="spec-form" @submit.prevent="submitSpec" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">اسم التخصص</label>
                        <input
                            v-model="specForm.name"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            :class="{ 'border-red-500': specForm.errors.name }"
                        />
                        <p v-if="specForm.errors.name" class="mt-1 text-xs text-red-600">{{ specForm.errors.name }}</p>
                    </div>
                </form>
                <template #footer>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
                        @click="showSpecModal = false"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        form="spec-form"
                        :disabled="specForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        حفظ
                    </button>
                </template>
            </Modal>

            <!-- Confirm Delete Department -->
            <ConfirmDelete
                :show="!!confirmDeptDelete"
                :item-name="confirmDeptDelete?.name"
                @confirmed="deleteDept"
                @cancelled="confirmDeptDelete = null"
            />

            <!-- Confirm Delete Specialization -->
            <ConfirmDelete
                :show="!!confirmSpecDelete"
                :item-name="confirmSpecDelete?.name"
                @confirmed="deleteSpec"
                @cancelled="confirmSpecDelete = null"
            />
        </div>
    </AppLayout>
</template>
