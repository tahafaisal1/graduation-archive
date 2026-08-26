<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue'
import StatsCard from '@/components/StatsCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { router } from '@inertiajs/vue3'
import { Building2, FolderOpen, Star } from 'lucide-vue-next'
import { computed, ref } from 'vue'

// ── Types ──────────────────────────────────────────────────────────────────

interface SpecItem {
    id: number
    name: string
    project_count: number
}

interface DeptItem {
    id: number
    name: string
    code: string
    project_count: number
    avg_score: string | null
    scored_count: number
    specializations: SpecItem[]
}

interface SupervisorItem {
    id: number
    name: string
    department: string | null
    project_count: number
}

interface DeptFilter { id: number; name: string }

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    report: {
        departments: DeptItem[]
        supervisors: SupervisorItem[]
    }
    departments: DeptFilter[]
    filter: { department_id: number | null }
}>()

// ── State ──────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'تقرير الأقسام', href: '/reports/department' },
]

const selectedDept = ref<number | ''>(props.filter.department_id ?? '')

function applyFilter() {
    router.get(
        route('reports.department'),
        selectedDept.value ? { department_id: selectedDept.value } : {},
        { preserveScroll: true },
    )
}

// ── Computed summaries ─────────────────────────────────────────────────────

const totalProjects = computed(() =>
    props.report.departments.reduce((s, d) => s + d.project_count, 0),
)

const overallAvg = computed(() => {
    const scored = props.report.departments.filter(d => d.avg_score !== null)
    if (!scored.length) return null
    const sum = scored.reduce((s, d) => s + Number(d.avg_score), 0)
    return (sum / scored.length).toFixed(1)
})

const pdfUrl   = computed(() => route('reports.export.pdf',   { type: 'department' }))
const excelUrl = computed(() => route('reports.export.excel', { type: 'department' }))
</script>

<template>
    <Head title="تقرير الأقسام" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">تقرير الأقسام</h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">إحصائيات المقترحات حسب الأقسام والتخصصات</p>
                </div>
                <ExportButtons :pdf-url="pdfUrl" :excel-url="excelUrl" />
            </div>

            <!-- Filter (super_admin only — departments prop is non-empty) -->
            <div v-if="departments.length > 0" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">تصفية حسب القسم</label>
                    <select
                        v-model="selectedDept"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                        <option value="">جميع الأقسام</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="applyFilter"
                >
                    تطبيق
                </button>
                <button
                    v-if="filter.department_id"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                    @click="selectedDept = ''; applyFilter()"
                >
                    إعادة تعيين
                </button>
            </div>

            <!-- Summary cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <StatsCard
                    title="إجمالي المقترحات"
                    :value="totalProjects"
                    :icon="FolderOpen"
                    color="blue"
                />
                <StatsCard
                    title="عدد الأقسام"
                    :value="report.departments.length"
                    :icon="Building2"
                    color="green"
                />
                <StatsCard
                    title="متوسط الدرجات"
                    :value="overallAvg ?? '—'"
                    :icon="Star"
                    color="purple"
                    sub="للمشاريع المقيَّمة"
                />
            </div>

            <!-- Departments table -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">الأقسام</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">القسم</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">الرمز</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">المقترحات</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">متوسط الدرجة</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">التخصصات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="dept in report.departments" :key="dept.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ dept.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ dept.code }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ dept.project_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ dept.avg_score ? Number(dept.avg_score).toFixed(1) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ dept.specializations.length }} تخصص
                                </td>
                            </tr>
                            <tr v-if="!report.departments.length">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">لا توجد بيانات</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Specializations breakdown -->
            <div
                v-for="dept in report.departments"
                :key="`spec-${dept.id}`"
                class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">
                        تخصصات قسم: <span class="text-blue-600 dark:text-blue-400">{{ dept.name }}</span>
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">التخصص</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">عدد المقترحات</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">النسبة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="spec in dept.specializations" :key="spec.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-2.5 text-sm text-gray-800 dark:text-gray-200">{{ spec.name }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                        {{ spec.project_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                            <div
                                                class="h-2 rounded-full bg-purple-500"
                                                :style="{ width: dept.project_count ? Math.round((spec.project_count / dept.project_count) * 100) + '%' : '0%' }"
                                            />
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ dept.project_count ? Math.round((spec.project_count / dept.project_count) * 100) : 0 }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!dept.specializations.length">
                                <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-400">لا توجد تخصصات</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Supervisors -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">المشرفون وعدد مقترحاتهم</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">المشرف</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">القسم</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">عدد المقترحات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="sup in report.supervisors" :key="sup.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ sup.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ sup.department ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        {{ sup.project_count }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!report.supervisors.length">
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400">لا يوجد مشرفون</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
