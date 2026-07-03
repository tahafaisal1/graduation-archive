<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue'
import StatsCard from '@/components/StatsCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { TrendingDown, TrendingUp } from 'lucide-vue-next'
import { computed } from 'vue'

// ── Types ──────────────────────────────────────────────────────────────────

interface YearlyItem {
    year: string
    count: number
    growth_pct: number | null
}

interface DeptInYear {
    academic_year: string
    department_id: number
    department_name: string
    count: number
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    report: {
        yearly:             YearlyItem[]
        department_by_year: Record<string, DeptInYear[]>
    }
}>()

// ── Setup ──────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'التقرير السنوي', href: '/reports/yearly' },
]

const pdfUrl   = computed(() => route('reports.export.pdf',   { type: 'yearly' }))
const excelUrl = computed(() => route('reports.export.excel', { type: 'yearly' }))

// ── Summary stats ──────────────────────────────────────────────────────────

const totalYears   = computed(() => props.report.yearly.length)
const totalAll     = computed(() => props.report.yearly.reduce((s, y) => s + y.count, 0))
const latestGrowth = computed(() => {
    const last = props.report.yearly[props.report.yearly.length - 1]
    return last?.growth_pct ?? null
})

// ── Dept distribution table columns (unique years) ─────────────────────────

const years = computed(() => Object.keys(props.report.department_by_year).sort())

// Unique department names across all years
const allDepts = computed(() => {
    const names = new Set<string>()
    Object.values(props.report.department_by_year).forEach(entries =>
        entries.forEach(e => names.add(e.department_name)),
    )
    return [...names].sort()
})

// Lookup: dept → year → count
const deptYearMap = computed(() => {
    const map: Record<string, Record<string, number>> = {}
    Object.entries(props.report.department_by_year).forEach(([yr, entries]) => {
        entries.forEach(e => {
            if (!map[e.department_name]) map[e.department_name] = {}
            map[e.department_name][yr] = e.count
        })
    })
    return map
})

// ── Growth styling ─────────────────────────────────────────────────────────

function growthClass(pct: number | null) {
    if (pct === null) return 'text-gray-400 dark:text-gray-500'
    return pct > 0
        ? 'text-green-600 dark:text-green-400'
        : pct < 0
          ? 'text-red-600 dark:text-red-400'
          : 'text-gray-500 dark:text-gray-400'
}
</script>

<template>
    <Head title="التقرير السنوي" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">التقرير السنوي</h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">مقارنة المشاريع عبر السنوات الأكاديمية</p>
                </div>
                <ExportButtons :pdf-url="pdfUrl" :excel-url="excelUrl" />
            </div>

            <!-- Summary cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <StatsCard
                    title="إجمالي المشاريع"
                    :value="totalAll"
                    color="blue"
                />
                <StatsCard
                    title="عدد السنوات"
                    :value="totalYears"
                    color="green"
                />
                <StatsCard
                    title="نمو آخر سنة"
                    :value="latestGrowth !== null ? latestGrowth + '%' : '—'"
                    :color="latestGrowth !== null && latestGrowth >= 0 ? 'green' : 'orange'"
                    sub="مقارنةً بالسنة السابقة"
                />
            </div>

            <!-- Year comparison table -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">المقارنة السنوية</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">السنة الأكاديمية</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">عدد المشاريع</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">نسبة النمو</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">الشريط البياني</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="yr in report.yearly"
                                :key="yr.year"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ yr.year }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ yr.count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="yr.growth_pct !== null"
                                        :class="['inline-flex items-center gap-1 text-sm font-semibold', growthClass(yr.growth_pct)]"
                                    >
                                        <TrendingUp v-if="yr.growth_pct > 0" :size="14" />
                                        <TrendingDown v-else-if="yr.growth_pct < 0" :size="14" />
                                        {{ yr.growth_pct > 0 ? '+' : '' }}{{ yr.growth_pct }}%
                                    </span>
                                    <span v-else class="text-sm text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="h-2.5 w-32 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div
                                            class="h-2.5 rounded-full bg-blue-500"
                                            :style="{ width: Math.round((yr.count / Math.max(...report.yearly.map(y => y.count), 1)) * 100) + '%' }"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!report.yearly.length">
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">لا توجد بيانات</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Department distribution per year -->
            <div
                v-if="allDepts.length && years.length"
                class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">توزيع الأقسام عبر السنوات</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">القسم</th>
                                <th
                                    v-for="yr in years"
                                    :key="yr"
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-400"
                                >
                                    {{ yr }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="dept in allDepts"
                                :key="dept"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ dept }}</td>
                                <td
                                    v-for="yr in years"
                                    :key="yr"
                                    class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ deptYearMap[dept]?.[yr] ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
