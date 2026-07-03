<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { ArrowUpDown } from 'lucide-vue-next'
import { computed, ref } from 'vue'

// ── Types ──────────────────────────────────────────────────────────────────

interface ByYearItem { year: string; count: number }

interface SupervisorItem {
    id: number
    name: string
    department: string | null
    project_count: number
    avg_score: string | null
    scored_count: number
    by_year: ByYearItem[]
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{ report: SupervisorItem[] }>()

// ── Setup ──────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'تقرير المشرفين', href: '/reports/supervisors' },
]

const pdfUrl   = computed(() => route('reports.export.pdf',   { type: 'supervisors' }))
const excelUrl = computed(() => route('reports.export.excel', { type: 'supervisors' }))

// ── Client-side department filter ─────────────────────────────────────────

const departments = computed(() => {
    const set = new Set(props.report.map(s => s.department).filter(Boolean) as string[])
    return [...set].sort()
})

const selectedDept = ref('')

// ── Client-side sort ───────────────────────────────────────────────────────

type SortKey = 'name' | 'project_count' | 'avg_score'

const sortKey = ref<SortKey>('project_count')
const sortDir = ref<'asc' | 'desc'>('desc')

function toggleSort(key: SortKey) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortDir.value = 'desc'
    }
}

function sortIndicator(key: SortKey) {
    if (sortKey.value !== key) return ''
    return sortDir.value === 'asc' ? ' ↑' : ' ↓'
}

const filtered = computed(() =>
    selectedDept.value
        ? props.report.filter(s => s.department === selectedDept.value)
        : props.report,
)

const sorted = computed(() => {
    return [...filtered.value].sort((a, b) => {
        let av: number | string = 0
        let bv: number | string = 0

        if (sortKey.value === 'name') {
            av = a.name; bv = b.name
            const dir = sortDir.value === 'asc' ? 1 : -1
            return av < bv ? -dir : av > bv ? dir : 0
        }

        if (sortKey.value === 'avg_score') {
            av = a.avg_score !== null ? Number(a.avg_score) : -1
            bv = b.avg_score !== null ? Number(b.avg_score) : -1
        } else {
            av = a.project_count
            bv = b.project_count
        }

        const dir = sortDir.value === 'asc' ? 1 : -1
        return (av as number) < (bv as number) ? -dir : (av as number) > (bv as number) ? dir : 0
    })
})
</script>

<template>
    <Head title="تقرير المشرفين" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">تقرير المشرفين</h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">إحصائيات المشرفين وعدد مشاريعهم ومتوسط درجاتهم</p>
                </div>
                <ExportButtons :pdf-url="pdfUrl" :excel-url="excelUrl" />
            </div>

            <!-- Department filter -->
            <div v-if="departments.length" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">تصفية حسب القسم</label>
                    <select
                        v-model="selectedDept"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                        <option value="">جميع الأقسام</option>
                        <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>
                <button
                    v-if="selectedDept"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                    @click="selectedDept = ''"
                >
                    إعادة تعيين
                </button>
            </div>

            <!-- Supervisors table -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-700 dark:text-gray-200">
                        قائمة المشرفين
                        <span class="mr-2 text-sm font-normal text-gray-400">({{ sorted.length }})</span>
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">#</th>
                                <th
                                    class="cursor-pointer select-none px-4 py-3 text-right text-xs font-semibold text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                                    @click="toggleSort('name')"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        المشرف <ArrowUpDown :size="12" />
                                    </span>
                                    {{ sortIndicator('name') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">القسم</th>
                                <th
                                    class="cursor-pointer select-none px-4 py-3 text-right text-xs font-semibold text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                                    @click="toggleSort('project_count')"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        عدد المشاريع <ArrowUpDown :size="12" />
                                    </span>
                                    {{ sortIndicator('project_count') }}
                                </th>
                                <th
                                    class="cursor-pointer select-none px-4 py-3 text-right text-xs font-semibold text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                                    @click="toggleSort('avg_score')"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        متوسط الدرجة <ArrowUpDown :size="12" />
                                    </span>
                                    {{ sortIndicator('avg_score') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">المشاريع بالسنة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="(sup, i) in sorted"
                                :key="sup.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-3 text-xs text-gray-400">{{ i + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ sup.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ sup.department ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ sup.project_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ sup.avg_score ? Number(sup.avg_score).toFixed(1) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="yr in sup.by_year"
                                            :key="yr.year"
                                            class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                        >
                                            {{ yr.year }}: {{ yr.count }}
                                        </span>
                                        <span v-if="!sup.by_year.length" class="text-xs text-gray-400">—</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!sorted.length">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">لا يوجد مشرفون</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
