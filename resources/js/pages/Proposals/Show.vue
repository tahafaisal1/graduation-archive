<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { isProposalStatus, statusColor, statusLabel } from '@/composables/useProposalStatus';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Department     { id: number; name: string }
interface Specialization { id: number; name: string }
interface Supervisor     { id: number; name: string }
interface ProjectStatus  { id: number; status_name: string }
interface ProposalStudent { id: number; full_name: string; registration_number: string; status: string }
interface InstantiatedProject { id: number }

interface Proposal {
    id: number;
    title: string;
    description: string;
    academic_year: string;
    draft_file_path: string | null;
    created_by: number | null;
    department_id: number;
    department: Department | null;
    specialization: Specialization | null;
    supervisor: Supervisor | null;
    status: ProjectStatus | null;
    students: ProposalStudent[];
    instantiated_project: InstantiatedProject | null;
}

const props = defineProps<{
    proposal: Proposal;
}>();

const page     = usePage<SharedData>();
const flash    = computed(() => page.props.flash ?? {});
const userRole = computed(() => (page.props.auth.user as { role?: string }).role ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المقترحات',    href: '/proposals' },
    { title: props.proposal.title, href: '#' },
];

// ── Permissions ───────────────────────────────────────────────────
const currentUser = computed(() => page.props.auth.user);
const isPending    = computed(() => isProposalStatus(props.proposal.status?.status_name));

const canModify = computed(() => {
    if (userRole.value === 'super_admin') return true;
    if (!isPending.value) return false;
    if (userRole.value === 'dept_manager' && currentUser.value.department_id === props.proposal.department_id) return true;
    return props.proposal.created_by === currentUser.value.id && currentUser.value.department_id === props.proposal.department_id;
});

const canReplace = canModify;
const canDelete  = canModify;

const canInstantiate = computed(() =>
    userRole.value === 'super_admin'
    || (userRole.value === 'dept_manager' && isProposalStatus(props.proposal.status?.status_name) && currentUser.value.department_id === props.proposal.department_id)
);

// ── Proposal actions ────────────────────────────────────────────────
const showConfirmDelete = ref(false);

function deleteProject() {
    router.delete(route('proposals.destroy', [props.proposal.id]), {
        onFinish: () => (showConfirmDelete.value = false),
    });
}

const showConfirmInstantiate = ref(false);

function instantiateProject() {
    router.post(route('proposals.instantiate', [props.proposal.id]), {}, {
        onFinish: () => (showConfirmInstantiate.value = false),
    });
}

// ── Status helpers now come from @/composables/useProposalStatus ──────

const pdfUrl = computed(() =>
    props.proposal.draft_file_path ? `/storage/${props.proposal.draft_file_path}` : null
);

const pdfFileName = computed(() =>
    props.proposal.draft_file_path?.split('/').pop() ?? null
);

const studentStatusLabel: Record<string, string> = {
    active:    'نشط',
    withdrawn: 'منسحب',
    completed: 'مكتمل',
};
</script>

<template>
    <Head :title="proposal.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

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

            <!-- Header row -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ proposal.title }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ proposal.academic_year }}</span>
                        <span
                            v-if="proposal.status"
                            :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusColor(proposal.status.status_name)]"
                        >
                            {{ statusLabel(proposal.status.status_name) }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="proposal.instantiated_project"
                        :href="route('projects.show', proposal.instantiated_project.id)"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        عرض المشروع
                    </a>
                    <button
                        v-else-if="canInstantiate"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="showConfirmInstantiate = true"
                    >
                        تنزيل المشروع
                    </button>
                    <a
                        v-if="canReplace"
                        :href="route('proposals.edit', [proposal.id])"
                        class="rounded-lg bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-600"
                    >
                        تعديل
                    </a>
                    <button
                        v-if="canDelete"
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                        @click="showConfirmDelete = true"
                    >
                        حذف
                    </button>
                    <a
                        :href="route('proposals.index')"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        العودة للقائمة
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- Main column -->
                <div class="space-y-6 lg:col-span-2">

                    <!-- Description -->
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-gray-200">الوصف</h2>
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            {{ proposal.description }}
                        </p>
                    </div>

                    <!-- Students -->
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-gray-200">
                            الطلاب
                            <span class="text-sm font-normal text-gray-400">({{ proposal.students.length }})</span>
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">#</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">الاسم الكامل</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">رقم القيد</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-transparent">
                                    <tr v-for="(student, idx) in proposal.students" :key="student.id">
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                        <td class="px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-200">{{ student.full_name }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ student.registration_number }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                            {{ studentStatusLabel[student.status] ?? student.status }}
                                        </td>
                                    </tr>
                                    <tr v-if="proposal.students.length === 0">
                                        <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">لا يوجد طلاب مسجلون</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Sidebar -->
                <div class="space-y-5">

                    <!-- Meta info -->
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">تفاصيل المقترح</h2>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">القسم</dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ proposal.department?.name ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">التخصص</dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ proposal.specialization?.name ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">المشرف</dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ proposal.supervisor?.name ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">السنة الدراسية</dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ proposal.academic_year }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- PDF Document -->
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">ملف المقترح</h2>
                        <div v-if="pdfUrl" class="flex flex-col gap-3">
                            <div class="flex items-center gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                                <span class="text-2xl">📄</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ pdfFileName }}</p>
                                    <p class="text-xs text-gray-500">PDF</p>
                                </div>
                            </div>
                            <a
                                :href="pdfUrl"
                                target="_blank"
                                download
                                class="flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                            >
                                ↓ تنزيل الملف
                            </a>
                        </div>
                        <p v-else class="text-sm text-gray-500">لا يوجد ملف مرفق</p>
                    </div>

                </div>
            </div>
        </div>

        <!-- Confirm delete proposal -->
        <ConfirmDelete
            :show="showConfirmDelete"
            :item-name="proposal.title"
            @confirmed="deleteProject"
            @cancelled="showConfirmDelete = false"
        />

        <!-- Confirm instantiate project -->
        <ConfirmDelete
            :show="showConfirmInstantiate"
            title="تأكيد إنشاء المشروع"
            message="سيتم أرشفة المقترح وإنشاء مشروع جديد مرتبط به. لا يمكن التراجع عن هذا الإجراء. هل أنت متأكد؟"
            confirm-label="تنزيل المشروع"
            confirm-color="green"
            @confirmed="instantiateProject"
            @cancelled="showConfirmInstantiate = false"
        />
    </AppLayout>
</template>
