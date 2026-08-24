<script setup lang="ts">
import AssignExaminerModal from '@/components/AssignExaminerModal.vue';
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import ScoreInput from '@/components/ScoreInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Department     { id: number; name: string }
interface Specialization { id: number; name: string }
interface Supervisor     { id: number; name: string }
interface ProjectStatus  { id: number; status_name: string }
interface Student        { id: number; full_name: string; registration_number: string; status: string }
interface Examiner {
    id: number; full_name: string; title: string | null;
    department?: { id: number; name: string } | null;
}
interface Evaluation { id: number; examiner_id: number; notes: string; created_at: string }
interface Proposal {
    id: number; title: string; description: string; academic_year: string;
    department: Department | null; specialization: Specialization | null;
}

interface Project {
    id: number;
    final_score: string | null;
    visit_count: number;
    proposal: Proposal;
    status: ProjectStatus | null;
    supervisor: Supervisor | null;
    students: Student[];
    examiners: Examiner[];
    evaluations: Evaluation[];
}

const props = defineProps<{ project: Project; availableExaminers: Examiner[] }>();

const page     = usePage<SharedData>();
const flash    = computed(() => page.props.flash ?? {});
const userRole = computed(() => (page.props.auth.user as { role?: string }).role ?? '');
const canManage = computed(() => ['dept_manager', 'super_admin'].includes(userRole.value));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المشاريع', href: '/projects' },
    { title: props.project.proposal.title, href: '#' },
];

const showAssignModal       = ref(false);
const confirmRemoveExaminer = ref<Examiner | null>(null);

function removeExaminer() {
    if (!confirmRemoveExaminer.value) return;
    router.delete(route('projects.remove-examiner', [props.project.id, confirmRemoveExaminer.value.id]), {
        onFinish: () => (confirmRemoveExaminer.value = null),
    });
}

function evaluationFor(examinerId: number): Evaluation | null {
    return props.project.evaluations.find(e => e.examiner_id === examinerId) ?? null;
}

const PASS_THRESHOLD = 50;
const finalScore = computed(() => {
    if (props.project.final_score === null || props.project.final_score === '') return null;
    const n = Number(props.project.final_score);
    return isNaN(n) ? null : n;
});
const scoreIsPass = computed(() => finalScore.value !== null && finalScore.value >= PASS_THRESHOLD);
</script>

<template>
    <Head :title="project.proposal.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">
            <div v-if="flash.success" class="rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{{ flash.success }}</div>

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ project.proposal.title }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ project.proposal.academic_year }}</span>
                        <span>•</span>
                        <span>{{ project.visit_count }} مشاهدة</span>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                            {{ project.status?.status_name }}
                        </span>
                    </div>
                </div>
                <a :href="route('projects.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    العودة للقائمة
                </a>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-gray-200">الوصف</h2>
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ project.proposal.description }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-gray-200">
                            الطلاب <span class="text-sm font-normal text-gray-400">({{ project.students.length }})</span>
                        </h2>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-transparent">
                                <tr v-for="(student, idx) in project.students" :key="student.id">
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-200">{{ student.full_name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ student.registration_number }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Examiners + Evaluations — logic unchanged from the old conflated Show.vue -->
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                المناقشون <span class="text-sm font-normal text-gray-400">({{ project.examiners.length }}/2)</span>
                            </h2>
                            <button v-if="canManage && project.examiners.length < 2" type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="showAssignModal = true">
                                + تعيين ممتحن
                            </button>
                        </div>
                        <div v-if="project.examiners.length > 0" class="space-y-4">
                            <div v-for="examiner in project.examiners" :key="examiner.id" class="rounded-lg border border-gray-100 p-4 dark:border-gray-700">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ examiner.full_name }}</p>
                                        <p v-if="examiner.title" class="text-xs text-gray-500">{{ examiner.title }}</p>
                                    </div>
                                    <button v-if="canManage" type="button" class="shrink-0 rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20" @click="confirmRemoveExaminer = examiner">
                                        إزالة
                                    </button>
                                </div>
                                <div v-if="evaluationFor(examiner.id)" class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                                    <p class="mb-1 text-xs font-medium text-gray-400">ملاحظات التقييم</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ evaluationFor(examiner.id)!.notes }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">لم يتم تعيين ممتحنين بعد</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">تفاصيل المشروع</h2>
                        <dl class="space-y-3">
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">القسم</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ project.proposal.department?.name ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">التخصص</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ project.proposal.specialization?.name ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">المشرف</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ project.supervisor?.name ?? '—' }}</dd></div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">الدرجة النهائية</h2>
                        <ScoreInput v-if="canManage" :project-id="project.id" :current-score="project.final_score" />
                        <template v-else>
                            <div v-if="finalScore !== null" class="flex items-center gap-3">
                                <span class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ finalScore }}</span>
                                <span :class="['rounded-full px-3 py-1 text-sm font-medium', scoreIsPass ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400']">
                                    {{ scoreIsPass ? 'ناجح' : 'راسب' }}
                                </span>
                            </div>
                            <p v-else class="text-sm text-gray-500">لم تُسجَّل درجة بعد</p>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDelete :show="!!confirmRemoveExaminer" :item-name="confirmRemoveExaminer?.full_name" @confirmed="removeExaminer" @cancelled="confirmRemoveExaminer = null" />
        <AssignExaminerModal :show="showAssignModal" :project-id="project.id" :available-examiners="availableExaminers" @assigned="showAssignModal = false" @cancelled="showAssignModal = false" />
    </AppLayout>
</template>
