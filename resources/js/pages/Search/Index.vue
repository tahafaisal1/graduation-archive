<script setup lang="ts">
import SearchBar from '@/components/SearchBar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// ── Types ────────────────────────────────────────────────────────────

interface Department     { id: number; name: string }
interface Specialization { id: number; name: string; department_id: number }
interface Supervisor     { id: number; name: string }
interface ProjectStatus  { id: number; status_name: string }
interface Student        { id: number; full_name: string }

interface Project {
    id: number;
    project_title: string;
    academic_year: string;
    description: string | null;
    department: Department | null;
    specialization: Specialization | null;
    supervisor: Supervisor | null;
    current_status: ProjectStatus | null;
    students: Student[];
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

// ── Props ────────────────────────────────────────────────────────────

const props = defineProps<{
    projects: PaginatedProjects;
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

const page = usePage<SharedData>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'البحث', href: '/search' },
];

// ── Search state ─────────────────────────────────────────────────────

const searchQuery = ref(props.filters.search ?? '');
const suggestions = ref<string[]>([]);

async function loadSuggestions(q: string) {
    if (q.length < 2) { suggestions.value = []; return; }
    try {
        const res = await fetch(route('search.suggestions') + '?q=' + encodeURIComponent(q));
        suggestions.value = await res.json() as string[];
    } catch {
        suggestions.value = [];
    }
}

function onSearch(q: string) {
    searchQuery.value = q;
    if (q.length >= 2) loadSuggestions(q);
    else suggestions.value = [];
    doSearch(q);
}

function onSelect(s: string) {
    searchQuery.value = s;
    suggestions.value = [];
    doSearch(s);
}

function doSearch(q: string) {
    router.get(route('search.index'), q ? { search: q } : {}, { preserveState: true, replace: true });
}

// ── Group results by department ───────────────────────────────────────

const grouped = computed(() => {
    const map = new Map<string, Project[]>();
    for (const p of props.projects.data) {
        const key = p.department?.name ?? 'غير محدد';
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(p);
    }
    return map;
});

// ── Text highlight ────────────────────────────────────────────────────

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function highlight(text: string): string {
    const safeText = escapeHtml(text);
    const q = searchQuery.value.trim();
    if (!q) return safeText;
    const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return safeText.replace(
        new RegExp(`(${escaped})`, 'gi'),
        '<strong class="font-bold text-blue-600 dark:text-blue-400">$1</strong>',
    );
}
</script>

<template>
    <Head title="البحث" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">

            <!-- ── Search hero ────────────────────────────────────── -->
            <div class="flex flex-col gap-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">البحث في المشاريع</h1>
                <SearchBar
                    v-model="searchQuery"
                    placeholder="ابحث عن مشروع بالعنوان أو الوصف..."
                    :suggestions="suggestions"
                    class="max-w-2xl"
                    @search="onSearch"
                    @select="onSelect"
                />
            </div>

            <!-- ── Results summary ────────────────────────────────── -->
            <template v-if="searchQuery">
                <p v-if="projects.total > 0" class="text-sm text-gray-600 dark:text-gray-400">
                    تم العثور على
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ projects.total }}</span>
                    نتيجة لـ
                    <span class="font-semibold text-blue-600 dark:text-blue-400">"{{ searchQuery }}"</span>
                </p>
                <div v-else class="flex flex-col items-center gap-3 py-16 text-gray-500 dark:text-gray-400">
                    <span class="text-4xl">🔍</span>
                    <p class="text-lg font-medium">لا توجد نتائج</p>
                    <p class="text-sm">لم يتم العثور على مشاريع تطابق "{{ searchQuery }}"</p>
                </div>
            </template>

            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                اكتب كلمة بحث للعثور على المشاريع
            </p>

            <!-- ── Results grouped by department ─────────────────── -->
            <div v-if="projects.total > 0" class="flex flex-col gap-8">
                <section
                    v-for="[deptName, deptProjects] in grouped"
                    :key="deptName"
                >
                    <!-- Department header -->
                    <div class="mb-3 flex items-center gap-3">
                        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            {{ deptName }}
                        </h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            {{ deptProjects.length }}
                        </span>
                        <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
                    </div>

                    <!-- Project cards -->
                    <div class="flex flex-col gap-3">
                        <a
                            v-for="project in deptProjects"
                            :key="project.id"
                            :href="route('projects.show', [project.id])"
                            class="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-700"
                        >
                            <!-- Title with highlight -->
                            <p
                                class="text-sm font-semibold text-blue-600 dark:text-blue-400"
                                v-html="highlight(project.project_title)"
                            />

                            <!-- Meta row -->
                            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="project.specialization">
                                    📚 {{ project.specialization.name }}
                                </span>
                                <span v-if="project.academic_year">
                                    📅 {{ project.academic_year }}
                                </span>
                                <span v-if="project.supervisor">
                                    👤 {{ project.supervisor.name }}
                                </span>
                            </div>

                            <!-- Students -->
                            <div v-if="project.students && project.students.length > 0" class="mt-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="student in project.students"
                                    :key="student.id"
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                >
                                    {{ student.full_name }}
                                </span>
                            </div>

                            <!-- Description snippet with highlight -->
                            <p
                                v-if="project.description"
                                class="mt-2 line-clamp-2 text-xs text-gray-500 dark:text-gray-400"
                                v-html="highlight(project.description)"
                            />
                        </a>
                    </div>
                </section>
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
                                    : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300',
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

        </div>
    </AppLayout>
</template>
