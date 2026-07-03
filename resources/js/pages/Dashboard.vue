<script setup lang="ts">
import StatsCard from '@/components/StatsCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem, type SharedData } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'
import { BarChart2, Building2, Calendar, Clock, FolderOpen } from 'lucide-vue-next'
import { computed } from 'vue'

// ── Types ──────────────────────────────────────────────────────────────────

interface StatusItem    { status_name: string; count: number }
interface RecentProject {
    id: number
    project_title: string
    academic_year: string
    department: { name: string } | null
    current_status: { status_name: string } | null
}
interface SpecItem       { id: number; name: string; project_count: number }
interface SupervisorItem { id: number; name: string; department: string | null; project_count: number }

interface DashboardStats {
    // super_admin
    total_projects?: number
    total_departments?: number
    projects_this_year?: number
    pending_approvals?: number
    recent_projects?: RecentProject[]
    by_status?: StatusItem[]
    // dept_manager
    project_count?: number
    avg_score?: string | null
    specializations?: SpecItem[]
    supervisors?: SupervisorItem[]
}

// ── Props & page data ──────────────────────────────────────────────────────

const props = defineProps<{ stats: DashboardStats }>()

const page     = usePage<SharedData>()
const role     = computed(() => page.props.auth?.user?.role ?? '')
const userName = computed(() => page.props.auth?.user?.name ?? '')

const breadcrumbs: BreadcrumbItem[] = [{ title: 'لوحة التحكم', href: '/dashboard' }]

// ── Status bar chart helpers ───────────────────────────────────────────────

const maxStatusCount = computed(() =>
    Math.max(...(props.stats.by_status?.map(s => s.count) ?? [0]), 1),
)

const barWidth = (count: number) =>
    Math.round((count / maxStatusCount.value) * 100) + '%'

const barColors = [
    'bg-blue-500', 'bg-purple-500', 'bg-green-500', 'bg-orange-500',
    'bg-rose-500', 'bg-cyan-500', 'bg-amber-500', 'bg-indigo-500',
    'bg-teal-500', 'bg-pink-500',
]

// ── Report quick-links ─────────────────────────────────────────────────────

const reportLinks = [
    { title: 'تقرير الأقسام',     href: '/reports/department',      color: 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400' },
    { title: 'تقرير التخصصات',   href: '/reports/specializations',  color: 'border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 dark:border-purple-800 dark:bg-purple-900/20 dark:text-purple-400' },
    { title: 'تقرير المشرفين',    href: '/reports/supervisors',      color: 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400' },
    { title: 'التقرير السنوي',    href: '/reports/yearly',           color: 'border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 dark:border-orange-800 dark:bg-orange-900/20 dark:text-orange-400' },
]
</script>

<template>
    <Head title="لوحة التحكم" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- Welcome header -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    مرحباً، {{ userName }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ role === 'super_admin' ? 'مدير النظام' : role === 'dept_manager' ? 'مدير القسم' : role === 'dept_staff' ? 'موظف القسم' : role === 'supervisor' ? 'مشرف' : role }}
                </p>
            </div>

            <!-- ── SUPER ADMIN ─────────────────────────────────────────── -->
            <template v-if="role === 'super_admin'">

                <!-- 4 Stats cards -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatsCard
                        title="إجمالي المشاريع"
                        :value="stats.total_projects ?? 0"
                        :icon="FolderOpen"
                        color="blue"
                    />
                    <StatsCard
                        title="الأقسام"
                        :value="stats.total_departments ?? 0"
                        :icon="Building2"
                        color="green"
                    />
                    <StatsCard
                        title="مشاريع هذا العام"
                        :value="stats.projects_this_year ?? 0"
                        :icon="Calendar"
                        color="purple"
                    />
                    <StatsCard
                        title="في انتظار الموافقة"
                        :value="stats.pending_approvals ?? 0"
                        :icon="Clock"
                        color="orange"
                    />
                </div>

                <!-- Recent projects + Status chart -->
                <div class="grid gap-6 lg:grid-cols-5">

                    <!-- Recent projects table (3/5) -->
                    <div class="lg:col-span-3 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">آخر المشاريع المضافة</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/60">
                                    <tr>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">عنوان المشروع</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">القسم</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">السنة</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="p in stats.recent_projects"
                                        :key="p.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                    >
                                        <td class="px-4 py-2.5">
                                            <Link
                                                :href="`/projects/${p.id}`"
                                                class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                {{ p.project_title }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">
                                            {{ p.department?.name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">
                                            {{ p.academic_year }}
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                {{ p.current_status?.status_name ?? '—' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!stats.recent_projects?.length">
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">لا توجد مشاريع</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Status breakdown (2/5) -->
                    <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">المشاريع حسب الحالة</h3>
                        </div>
                        <div class="space-y-3 p-5">
                            <div
                                v-for="(s, i) in stats.by_status"
                                :key="s.status_name"
                            >
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="text-gray-700 dark:text-gray-300">{{ s.status_name }}</span>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ s.count }}</span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-700">
                                    <div
                                        :class="['h-2 rounded-full transition-all', barColors[i % barColors.length]]"
                                        :style="{ width: barWidth(s.count) }"
                                    />
                                </div>
                            </div>
                            <p v-if="!stats.by_status?.length" class="text-center text-sm text-gray-400">لا توجد بيانات</p>
                        </div>
                    </div>
                </div>

                <!-- Quick links to reports -->
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-3 font-semibold text-gray-700 dark:text-gray-200">
                        <BarChart2 :size="18" class="ml-2 inline-block text-blue-500" />
                        التقارير
                    </h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            v-for="link in reportLinks"
                            :key="link.href"
                            :href="link.href"
                            :class="['block rounded-lg border px-4 py-3 text-sm font-medium transition', link.color]"
                        >
                            {{ link.title }}
                        </Link>
                    </div>
                </div>

            </template>

            <!-- ── DEPT MANAGER ───────────────────────────────────────── -->
            <template v-else-if="role === 'dept_manager'">

                <!-- Stats cards -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <StatsCard
                        title="مشاريع القسم"
                        :value="stats.project_count ?? 0"
                        :icon="FolderOpen"
                        color="blue"
                    />
                    <StatsCard
                        title="متوسط الدرجات"
                        :value="stats.avg_score ? Number(stats.avg_score).toFixed(1) : '—'"
                        :icon="BarChart2"
                        color="green"
                        sub="للمشاريع المقيَّمة"
                    />
                    <StatsCard
                        title="التخصصات النشطة"
                        :value="stats.specializations?.length ?? 0"
                        :icon="Building2"
                        color="purple"
                    />
                </div>

                <!-- Specializations + Supervisors -->
                <div class="grid gap-6 lg:grid-cols-2">

                    <!-- Specializations table -->
                    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">التخصصات</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/60">
                                    <tr>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">التخصص</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">عدد المشاريع</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="spec in stats.specializations" :key="spec.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <td class="px-4 py-2.5 text-sm text-gray-800 dark:text-gray-200">{{ spec.name }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                {{ spec.project_count }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!stats.specializations?.length">
                                        <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-400">لا توجد تخصصات</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Supervisors list -->
                    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">المشرفون</h3>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div
                                v-for="sup in stats.supervisors"
                                :key="sup.id"
                                class="flex items-center justify-between px-5 py-3"
                            >
                                <span class="text-sm text-gray-800 dark:text-gray-200">{{ sup.name }}</span>
                                <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    {{ sup.project_count }} مشروع
                                </span>
                            </div>
                            <div v-if="!stats.supervisors?.length" class="px-5 py-6 text-center text-sm text-gray-400">
                                لا يوجد مشرفون
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-3 font-semibold text-gray-700 dark:text-gray-200">
                        <BarChart2 :size="18" class="ml-2 inline-block text-blue-500" />
                        التقارير
                    </h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            v-for="link in reportLinks"
                            :key="link.href"
                            :href="link.href"
                            :class="['block rounded-lg border px-4 py-3 text-sm font-medium transition', link.color]"
                        >
                            {{ link.title }}
                        </Link>
                    </div>
                </div>

            </template>

            <!-- ── DEFAULT (dept_staff / supervisor / viewer) ────────── -->
            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2">
                    <StatsCard
                        title="إجمالي المشاريع"
                        :value="stats.total_projects ?? 0"
                        :icon="FolderOpen"
                        color="blue"
                    />
                    <div class="flex items-center justify-center rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <Link
                            href="/projects"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            <FolderOpen :size="16" />
                            عرض المشاريع
                        </Link>
                    </div>
                </div>
            </template>

        </div>
    </AppLayout>
</template>
