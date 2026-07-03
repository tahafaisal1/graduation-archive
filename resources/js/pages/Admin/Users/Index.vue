<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import Modal from '@/components/Modal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// ── Types ─────────────────────────────────────────────────────────────
interface Department { id: number; name: string }
interface UserRole   { name: string }

interface UserItem {
    id: number;
    name: string;
    email: string;
    registration_number: string | null;
    department_id: number | null;
    department: Department | null;
    roles: UserRole[];
    is_active: boolean;
    created_at: string;
}

interface PaginationLink { url: string | null; label: string; active: boolean }

interface PaginatedUsers {
    data: UserItem[];
    links: PaginationLink[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
}

// ── Props ──────────────────────────────────────────────────────────────
const props = defineProps<{
    users: PaginatedUsers;
    roles: string[];
    departments: Department[];
    filters: {
        search?: string;
        role?: string;
        department_id?: string;
        is_active?: string;
    };
}>();

// ── Page setup ─────────────────────────────────────────────────────────
const page  = usePage<SharedData>();
const flash = computed(() => page.props.flash ?? {});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المستخدمون', href: '/admin/users' },
];

// ── Role labels + badge colours ────────────────────────────────────────
const roleLabels: Record<string, string> = {
    super_admin:  'مدير النظام',
    dept_manager: 'مدير القسم',
    supervisor:   'مشرف',
    dept_staff:   'موظف القسم',
    viewer:       'مشاهد',
};

const roleBadgeClass: Record<string, string> = {
    super_admin:  'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    dept_manager: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    supervisor:   'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    dept_staff:   'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    viewer:       'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
};

// ── Row number ─────────────────────────────────────────────────────────
const rowNumber = (idx: number) =>
    (props.users.meta.current_page - 1) * props.users.meta.per_page + idx + 1;

// ── Filters ────────────────────────────────────────────────────────────
const search         = ref(props.filters.search ?? '');
const selectedRole   = ref(props.filters.role ?? '');
const selectedDept   = ref(props.filters.department_id ?? '');
const selectedActive = ref(props.filters.is_active ?? '');

let searchTimer: ReturnType<typeof setTimeout>;

function applyFilters() {
    const params: Record<string, string> = {};
    if (search.value)               params.search        = search.value;
    if (selectedRole.value)         params.role          = selectedRole.value;
    if (selectedDept.value)         params.department_id = selectedDept.value;
    if (selectedActive.value !== '') params.is_active    = selectedActive.value;
    router.get(route('admin.users.index'), params, { preserveScroll: true, replace: true });
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 400);
}

function resetFilters() {
    search.value = '';
    selectedRole.value = '';
    selectedDept.value = '';
    selectedActive.value = '';
    router.get(route('admin.users.index'), {}, { preserveScroll: false, replace: true });
}

const hasFilters = computed(
    () => !!(search.value || selectedRole.value || selectedDept.value || selectedActive.value),
);

// ── Create modal ────────────────────────────────────────────────────────
const showCreate = ref(false);

const createForm = useForm({
    name:                '',
    email:               '',
    password:            '',
    registration_number: '',
    role:                '',
    department_id:       '' as string | number,
    is_active:           true,
});

function openCreate() {
    createForm.reset();
    createForm.is_active = true;
    showCreate.value = true;
}

function submitCreate() {
    createForm.post(route('admin.users.store'), {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
}

// ── Edit modal ──────────────────────────────────────────────────────────
const showEdit    = ref(false);
const editingUser = ref<UserItem | null>(null);

const editForm = useForm({
    name:                '',
    email:               '',
    password:            '',
    registration_number: '',
    role:                '',
    department_id:       '' as string | number,
});

function openEdit(user: UserItem) {
    editingUser.value        = user;
    editForm.name            = user.name;
    editForm.email           = user.email;
    editForm.password        = '';
    editForm.registration_number = user.registration_number ?? '';
    editForm.role            = user.roles[0]?.name ?? '';
    editForm.department_id   = user.department_id ?? '';
    showEdit.value           = true;
}

function submitEdit() {
    if (!editingUser.value) return;
    editForm.patch(route('admin.users.update', editingUser.value.id), {
        onSuccess: () => { showEdit.value = false; editForm.reset(); },
    });
}

// ── Toggle active ───────────────────────────────────────────────────────
function toggleActive(user: UserItem) {
    router.patch(route('admin.users.toggle-active', user.id), {}, { preserveScroll: true });
}

// ── Delete ──────────────────────────────────────────────────────────────
const confirmDelete = ref<UserItem | null>(null);

function deleteUser() {
    if (!confirmDelete.value) return;
    router.delete(route('admin.users.destroy', confirmDelete.value.id), {
        onFinish: () => (confirmDelete.value = null),
    });
}
</script>

<template>
    <Head title="إدارة المستخدمين" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- ── Header ─────────────────────────────────────── -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">إدارة المستخدمين</h1>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="openCreate"
                >
                    + إضافة مستخدم
                </button>
            </div>

            <!-- ── Flash messages ──────────────────────────────── -->
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

            <!-- ── Filter bar ──────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="البحث بالاسم أو البريد أو رقم القيد..."
                    class="min-w-60 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    @input="onSearchInput"
                />
                <select
                    v-model="selectedRole"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    @change="applyFilters"
                >
                    <option value="">جميع الأدوار</option>
                    <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                </select>
                <select
                    v-model="selectedDept"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    @change="applyFilters"
                >
                    <option value="">جميع الأقسام</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                        {{ dept.name }}
                    </option>
                </select>
                <select
                    v-model="selectedActive"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    @change="applyFilters"
                >
                    <option value="">جميع الحالات</option>
                    <option value="1">نشط</option>
                    <option value="0">غير نشط</option>
                </select>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    @click="resetFilters"
                >
                    ✕ مسح الفلاتر
                </button>
            </div>

            <!-- ── Results count ───────────────────────────────── -->
            <p class="text-sm text-gray-500 dark:text-gray-400">
                إجمالي المستخدمين: {{ users.meta.total }}
            </p>

            <!-- ── Table ──────────────────────────────────────── -->
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="w-12 px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">#</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الاسم</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">البريد الإلكتروني</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">رقم القيد</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">القسم</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الدور</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الحالة</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr
                            v-for="(user, idx) in users.data"
                            :key="user.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            <td class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">
                                {{ rowNumber(idx) }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ user.name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ user.registration_number ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ user.department?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    :class="roleBadgeClass[user.roles[0]?.name] ?? 'bg-gray-100 text-gray-700'"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ roleLabels[user.roles[0]?.name] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    :class="user.is_active
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ user.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-700 hover:bg-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400"
                                        @click="openEdit(user)"
                                    >
                                        تعديل
                                    </button>
                                    <button
                                        type="button"
                                        :class="user.is_active
                                            ? 'bg-orange-100 text-orange-700 hover:bg-orange-200 dark:bg-orange-900/20 dark:text-orange-400'
                                            : 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/20 dark:text-green-400'"
                                        class="rounded px-3 py-1 text-xs font-medium"
                                        @click="toggleActive(user)"
                                    >
                                        {{ user.is_active ? 'إيقاف' : 'تفعيل' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded bg-red-100 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/20 dark:text-red-400"
                                        @click="confirmDelete = user"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">
                                لا يوجد مستخدمون
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Pagination ──────────────────────────────────── -->
            <div v-if="users.meta.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-gray-600 dark:text-gray-400">
                    صفحة {{ users.meta.current_page }} من {{ users.meta.last_page }}
                </p>
                <div class="flex gap-1">
                    <template v-for="link in users.links" :key="link.label">
                        <button
                            v-if="link.url"
                            type="button"
                            :class="[
                                'min-w-8 rounded px-3 py-1',
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                            v-html="link.label"
                            @click="router.visit(link.url)"
                        />
                        <span
                            v-else
                            class="min-w-8 rounded border border-gray-200 px-3 py-1 text-gray-400 dark:border-gray-700"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>

        </div>

        <!-- ── CreateUserModal ─────────────────────────────────── -->
        <Modal :show="showCreate" title="إضافة مستخدم جديد" @close="showCreate = false">
            <form id="create-user-form" class="space-y-4" @submit.prevent="submitCreate">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">الاسم</label>
                    <input
                        v-model="createForm.name"
                        type="text"
                        maxlength="100"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': createForm.errors.name }"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">البريد الإلكتروني</label>
                    <input
                        v-model="createForm.email"
                        type="email"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': createForm.errors.email }"
                    />
                    <p v-if="createForm.errors.email" class="mt-1 text-xs text-red-600">{{ createForm.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">كلمة المرور</label>
                    <input
                        v-model="createForm.password"
                        type="password"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': createForm.errors.password }"
                    />
                    <p v-if="createForm.errors.password" class="mt-1 text-xs text-red-600">{{ createForm.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">رقم القيد</label>
                    <input
                        v-model="createForm.registration_number"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': createForm.errors.registration_number }"
                    />
                    <p v-if="createForm.errors.registration_number" class="mt-1 text-xs text-red-600">{{ createForm.errors.registration_number }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">الدور</label>
                    <select
                        v-model="createForm.role"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': createForm.errors.role }"
                    >
                        <option value="" disabled>-- اختر الدور --</option>
                        <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                    </select>
                    <p v-if="createForm.errors.role" class="mt-1 text-xs text-red-600">{{ createForm.errors.role }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">القسم</label>
                    <select
                        v-model="createForm.department_id"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': createForm.errors.department_id }"
                    >
                        <option value="">-- بدون قسم --</option>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <p v-if="createForm.errors.department_id" class="mt-1 text-xs text-red-600">{{ createForm.errors.department_id }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="create-is-active"
                        v-model="createForm.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <label for="create-is-active" class="text-sm text-gray-700 dark:text-gray-300">نشط عند الإنشاء</label>
                </div>
            </form>

            <template #footer>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="showCreate = false"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    form="create-user-form"
                    :disabled="createForm.processing"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    حفظ
                </button>
            </template>
        </Modal>

        <!-- ── EditUserModal ───────────────────────────────────── -->
        <Modal :show="showEdit" title="تعديل بيانات المستخدم" @close="showEdit = false">
            <form id="edit-user-form" class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">الاسم</label>
                    <input
                        v-model="editForm.name"
                        type="text"
                        maxlength="100"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': editForm.errors.name }"
                    />
                    <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">البريد الإلكتروني</label>
                    <input
                        v-model="editForm.email"
                        type="email"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': editForm.errors.email }"
                    />
                    <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        كلمة المرور
                        <span class="mr-1 text-xs font-normal text-gray-400">(اتركها فارغة للإبقاء على الحالية)</span>
                    </label>
                    <input
                        v-model="editForm.password"
                        type="password"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': editForm.errors.password }"
                    />
                    <p v-if="editForm.errors.password" class="mt-1 text-xs text-red-600">{{ editForm.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">رقم القيد</label>
                    <input
                        v-model="editForm.registration_number"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': editForm.errors.registration_number }"
                    />
                    <p v-if="editForm.errors.registration_number" class="mt-1 text-xs text-red-600">{{ editForm.errors.registration_number }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">الدور</label>
                    <select
                        v-model="editForm.role"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': editForm.errors.role }"
                    >
                        <option value="" disabled>-- اختر الدور --</option>
                        <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                    </select>
                    <p v-if="editForm.errors.role" class="mt-1 text-xs text-red-600">{{ editForm.errors.role }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">القسم</label>
                    <select
                        v-model="editForm.department_id"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': editForm.errors.department_id }"
                    >
                        <option value="">-- بدون قسم --</option>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <p v-if="editForm.errors.department_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.department_id }}</p>
                </div>
            </form>

            <template #footer>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="showEdit = false"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    form="edit-user-form"
                    :disabled="editForm.processing"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    تحديث
                </button>
            </template>
        </Modal>

        <!-- ── Confirm delete ──────────────────────────────────── -->
        <ConfirmDelete
            :show="!!confirmDelete"
            :item-name="confirmDelete?.name"
            @confirmed="deleteUser"
            @cancelled="confirmDelete = null"
        />
    </AppLayout>
</template>
