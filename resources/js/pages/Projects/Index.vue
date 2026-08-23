<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import FilterPanel, { type FilterValues } from '@/components/FilterPanel.vue';
import SearchBar from '@/components/SearchBar.vue';
import SimilarityWarning from '@/components/SimilarityWarning.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { isProposalStatus, statusColor, statusLabel } from '@/composables/useProjectStatus';
import { type BreadcrumbItem, type SharedData, type SimilarProject } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

// ── Types ────────────────────────────────────────────────────────────

interface Department    { id: number; name: string }
interface Specialization { id: number; name: string; department_id: number }
interface Supervisor    { id: number; name: string }
interface ProjectStatus { id: number; status_name: string }

interface Project {
    id: number;
    project_title: string;
    academic_year: string;
    created_by: number | null;
    department_id: number;
    department: Department | null;
    specialization: Specialization | null;
    supervisor: Supervisor | null;
    current_status: ProjectStatus | null;
    students_count: number;
}

interface PaginationLink { url: string | null; label: string; active: boolean }

interface PaginatedProjects {
    data: Project[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface FilterOptions {
    departments:    (Department & { specializations: Specialization[] })[];
    academic_years: string[];
    supervisors:    Supervisor[];
}

// ── Props ────────────────────────────────────────────────────────────

const props = defineProps<{
    projects: PaginatedProjects;
    filterOptions: FilterOptions;
    filters: {
        search?: string;
        department_id?: string | number;
        specialization_id?: string | number;
        academic_year?: string;
        supervisor_id?: string | number;
        status?: string;
        sort?: string;
    };
}>();

// ── Page globals ─────────────────────────────────────────────────────

const page     = usePage<SharedData>();
const flash    = computed(() => page.props.flash ?? {});
const userRole = computed(() => (page.props.auth.user as { role?: string }).role ?? '');

const canCreate = computed(() => ['dept_staff', 'dept_manager', 'super_admin'].includes(userRole.value));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المشاريع', href: '/projects' },
];

// ── Similarity warning (flash from store/update) ─────────────────────

const dismissedWarning = ref(false);
const similarProjects  = computed<SimilarProject[]>(() =>
    dismissedWarning.value ? [] : (flash.value.similarity_warning ?? []),
);

// ── Filter state ─────────────────────────────────────────────────────

const searchQuery = ref(props.filters.search ?? '');
const suggestions = ref<string[]>([]);

const filters = reactive<FilterValues>({
    department_id:     props.filters.department_id     ?? '',
    specialization_id: props.filters.specialization_id ?? '',
    academic_year:     props.filters.academic_year     ?? '',
    supervisor_id:     props.filters.supervisor_id     ?? '',
    sort:              props.filters.sort              ?? 'created_at',
    status:            props.filters.status            ?? '',
});

// Flat specializations list for FilterPanel
const allSpecs = computed(() =>
    props.filterOptions.departments.flatMap(d => d.specializations ?? []),
);

function buildParams(): Record<string, string> {
    const p: Record<string, string> = {};
    if (searchQuery.value)          p.search            = searchQuery.value;
    if (filters.department_id)      p.department_id     = String(filters.department_id);
    if (filters.specialization_id)  p.specialization_id = String(filters.specialization_id);
    if (filters.academic_year)      p.academic_year     = filters.academic_year;
    if (filters.supervisor_id)      p.supervisor_id     = String(filters.supervisor_id);
    if (filters.status)             p.status            = filters.status;
    if (filters.sort && filters.sort !== 'created_at') p.sort = filters.sort;
    return p;
}

function applyFilters() {
    router.get(route('projects.index'), buildParams(), { preserveState: true, replace: true });
}

function clearAll() {
    searchQuery.value = '';
    suggestions.value = [];
    Object.assign(filters, {
        department_id: '', specialization_id: '', academic_year: '',
        supervisor_id: '', sort: 'created_at', status: '',
    });
    router.get(route('projects.index'), {}, { preserveState: true, replace: true });
}

// SearchBar emits
function onSearch(q: string) {
    searchQuery.value = q;
    if (q.length >= 2) loadSuggestions(q);
    else suggestions.value = [];
    applyFilters();
}

function onSelect(s: string) {
    searchQuery.value = s;
    suggestions.value = [];
    applyFilters();
}

async function loadSuggestions(q: string) {
    try {
        const res = await fetch(route('search.suggestions') + '?q=' + encodeURIComponent(q));
        suggestions.value = await res.json() as string[];
    } catch {
        suggestions.value = [];
    }
}

// FilterPanel emits
function onFilterChanged(val: FilterValues) {
    Object.assign(filters, val);
    applyFilters();
}

// ── Active filter chips ──────────────────────────────────────────────

interface Chip { key: keyof FilterValues | 'search'; label: string }

const activeChips = computed<Chip[]>(() => {
    const chips: Chip[] = [];
    if (searchQuery.value) chips.push({ key: 'search', label: `"${searchQuery.value}"` });
    if (filters.department_id) {
        const d = props.filterOptions.departments.find(x => x.id == filters.department_id);
        if (d) chips.push({ key: 'department_id', label: d.name });
    }
    if (filters.specialization_id) {
        const s = allSpecs.value.find(x => x.id == filters.specialization_id);
        if (s) chips.push({ key: 'specialization_id', label: s.name });
    }
    if (filters.academic_year) chips.push({ key: 'academic_year', label: filters.academic_year });
    if (filters.supervisor_id) {
        const s = props.filterOptions.supervisors.find(x => x.id == filters.supervisor_id);
        if (s) chips.push({ key: 'supervisor_id', label: s.name });
    }
    if (filters.status === 'active') chips.push({ key: 'status', label: 'المؤرشفة فقط' });
    return chips;
});

function removeChip(key: Chip['key']) {
    if (key === 'search') {
        searchQuery.value = '';
        suggestions.value = [];
    } else {
        filters[key] = '' as never;
        if (key === 'department_id') filters.specialization_id = '';
    }
    applyFilters();
}

// ── Row permissions ──────────────────────────────────────────────────

function canModify(p: Project): boolean {
    if (userRole.value === 'super_admin') return true;
    if (!isProposalStatus(p.current_status?.status_name)) return false;
    const user = page.props.auth.user;
    if (userRole.value === 'dept_manager' && user.department_id === p.department_id) return true;
    return p.created_by === user.id && user.department_id === p.department_id;
}

function canReplace(p: Project): boolean {
    return canModify(p);
}

function canDeleteProject(p: Project): boolean {
    return canModify(p);
}

function canArchive(p: Project): boolean {
    const user = page.props.auth.user;
    return userRole.value === 'super_admin'
        || (userRole.value === 'dept_manager' && isProposalStatus(p.current_status?.status_name) && user.department_id === p.department_id);
}

// ── Actions ──────────────────────────────────────────────────────────

const confirmDelete = ref<Project | null>(null);

function deleteProject() {
    if (!confirmDelete.value) return;
    router.delete(route('projects.destroy', [confirmDelete.value.id]), {
        onFinish: () => { confirmDelete.value = null; },
    });
}

function archiveProject(id: number) {
    router.post(route('projects.archive', [id]));
}

// ── Status helpers now come from @/composables/useProjectStatus ──────
</script>

<template>
    <Head title="المشاريع" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" dir="rtl">

            <!-- ── Header ─────────────────────────────────────────── -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">المشاريع</h1>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        {{ projects.total }}
                    </span>
                </div>
                <a
                    v-if="canCreate"
                    :href="route('projects.create')"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    + إضافة مشروع
                </a>
            </div>

            <!-- ── Flash ──────────────────────────────────────────── -->
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

            <!-- ── Similarity warning ─────────────────────────────── -->
            <SimilarityWarning
                :show="similarProjects.length > 0"
                :similar-projects="similarProjects"
                @continue="dismissedWarning = true"
                @change-title="dismissedWarning = true"
            />

            <!-- ── Search bar ─────────────────────────────────────── -->
            <SearchBar
                v-model="searchQuery"
                placeholder="بحث في العنوان أو الوصف..."
                :suggestions="suggestions"
                @search="onSearch"
                @select="onSelect"
            />

            <!-- ── Advanced filter panel ──────────────────────────── -->
            <FilterPanel
                :departments="filterOptions.departments"
                :specializations="allSpecs"
                :years="filterOptions.academic_years"
                :supervisors="filterOptions.supervisors"
                :model-value="filters"
                @filter-changed="onFilterChanged"
            />

            <!-- ── Active filter chips ────────────────────────────── -->
            <div v-if="activeChips.length > 0" class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">الفلاتر النشطة:</span>
                <button
                    v-for="chip in activeChips"
                    :key="chip.key"
                    type="button"
                    class="flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300"
                    @click="removeChip(chip.key)"
                >
                    {{ chip.label }}
                    <span class="text-blue-500">✕</span>
                </button>
                <button
                    type="button"
                    class="text-xs text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                    @click="clearAll"
                >
                    مسح الكل
                </button>
            </div>

            <!-- ── Results count ──────────────────────────────────── -->
            <p v-if="projects.from" class="text-xs text-gray-500 dark:text-gray-400">
                عرض {{ projects.from }}–{{ projects.to }} من {{ projects.total }} مشروع
            </p>

            <!-- ── Table ──────────────────────────────────────────── -->
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">العنوان</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">القسم</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">التخصص</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">المشرف</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">السنة</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الحالة</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">الطلاب</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr
                            v-for="project in projects.data"
                            :key="project.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="max-w-xs px-4 py-3">
                                <a
                                    :href="route('projects.show', [project.id])"
                                    class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {{ project.project_title }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ project.department?.name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ project.specialization?.name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ project.supervisor?.name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ project.academic_year }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="project.current_status"
                                    :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusColor(project.current_status.status_name)]"
                                >
                                    {{ statusLabel(project.current_status.status_name) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">
                                {{ project.students_count }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <a
                                        :href="route('projects.show', [project.id])"
                                        class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                    >
                                        عرض
                                    </a>
                                    <a
                                        v-if="canReplace(project)"
                                        :href="route('projects.edit', [project.id])"
                                        class="rounded bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-700 hover:bg-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400"
                                    >
                                        تعديل
                                    </a>
                                    <button
                                        v-if="canArchive(project)"
                                        type="button"
                                        class="rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-200 dark:bg-green-900/20 dark:text-green-400"
                                        @click="archiveProject(project.id)"
                                    >
                                        أرشفة
                                    </button>
                                    <button
                                        v-if="canDeleteProject(project)"
                                        type="button"
                                        class="rounded bg-red-100 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/20 dark:text-red-400"
                                        @click="confirmDelete = project"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="projects.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">
                                لا توجد مشاريع مطابقة للبحث
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Pagination ─────────────────────────────────────── -->
            <div v-if="projects.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-gray-600 dark:text-gray-400">
                    صفحة {{ projects.current_page }} من {{ projects.last_page }}
                </p>
                <div class="flex gap-1">
                    <template v-for="link in projects.links" :key="link.label">
                        <button
                            v-if="link.url"
                            type="button"
                            :class="[
                                'min-w-8 rounded px-3 py-1',
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                            @click="router.visit(link.url)"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="min-w-8 rounded border border-gray-200 px-3 py-1 text-gray-400 dark:border-gray-700"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>

            <!-- ── Confirm delete ─────────────────────────────────── -->
            <ConfirmDelete
                :show="!!confirmDelete"
                :item-name="confirmDelete?.project_title"
                @confirmed="deleteProject"
                @cancelled="confirmDelete = null"
            />
        </div>
    </AppLayout>
</template>
