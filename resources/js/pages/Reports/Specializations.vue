<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { computed } from 'vue'

// ── Types ──────────────────────────────────────────────────────────────────

interface SpecItem {
    id: number
    name: string
    project_count: number
    department: { id: number; name: string } | null
}

interface ByYearEntry {
    id: number
    spec_name: string
    academic_year: string
    count: number
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    report: {
        top_specializations:  SpecItem[]
        rare_specializations: SpecItem[]
        by_year: Record<string, ByYearEntry[]>
    }
}>()

// ── Setup ──────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'تقرير التخصصات', href: '/reports/specializations' },
]

const pdfUrl   = computed(() => route('reports.export.pdf',   { type: 'specializations' }))
const excelUrl = computed(() => route('reports.export.excel', { type: 'specializations' }))

// ── Bar chart helpers ──────────────────────────────────────────────────────

const maxCount = computed(() =>
    Math.max(...props.report.top_specializations.map(s => s.project_count), 1),
)

const barWidth = (count: number) =>
    Math.round((count / maxCount.value) * 100) + '%'

// ── Trend table — unique academic years across all specs ───────────────────

const allYears = computed(() => {
    const years = new Set<string>()
    Object.values(props.report.by_year).forEach(entries =>
        entries.forEach(e => years.add(e.academic_year)),
    )
    return [...years].sort()
})

// Total projects per academic year (summed across all specs)
const yearTotals = computed(() => {
    const totals: Record<string, number> = {}
    Object.values(props.report.by_year).forEach(entries =>
        entries.forEach(e => {
            totals[e.academic_year] = (totals[e.academic_year] ?? 0) + e.count
        }),
    )
    return totals
})
</script>

<template>
    <Head title="تقرير التخصصات" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">تقرير التخصصات</h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">توزيع المشاريع على التخصصات والاتجاهات السنوية</p>
                </div>
                <ExportButtons :pdf-url="pdfUrl" :excel-url="excelUrl" />
            </div>

            <!-- Top 10 bar chart -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">أكثر 10 تخصصات استخداماً</h2>
                </div>
                <div class="space-y-3 p-5">
                    <div v-for="spec in report.top_specializations" :key="spec.id">
                        <div class="mb-1 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ spec.name }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ spec.department?.name ?? '—' }}
                                </span>
                            </div>
                            <span class="shrink-0 text-sm font-bold text-blue-600 dark:text-blue-400">
                                {{ spec.project_count }}
                            </span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div
                                class="h-2.5 rounded-full bg-blue-500 transition-all"
                                :style="{ width: barWidth(spec.project_count) }"
                            />
                        </div>
                    </div>
                    <p v-if="!report.top_specializations.length" class="text-center text-sm text-gray-400">
                        لا توجد بيانات
                    </p>
                </div>
            </div>

            <!-- Year trend table -->
            <div
                v-if="allYears.length"
                class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">الاتجاه السنوي — إجمالي مشاريع التخصصات</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    السنة الأكاديمية
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    إجمالي المشاريع
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    الشريط البياني
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="yr in allYears"
                                :key="yr"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ yr }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ yearTotals[yr] ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div
                                            class="h-2 rounded-full bg-purple-500"
                                            :style="{ width: Math.round(((yearTotals[yr] ?? 0) / Math.max(...Object.values(yearTotals), 1)) * 100) + '%' }"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rarely used -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">التخصصات الأقل استخداماً</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">التخصص</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">القسم</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">عدد المشاريع</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="spec in report.rare_specializations"
                                :key="spec.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ spec.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ spec.department?.name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                        {{ spec.project_count }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!report.rare_specializations.length">
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400">لا توجد بيانات</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
