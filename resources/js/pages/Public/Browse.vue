<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Logo from '@/components/Brand/Logo.vue';
import { ref, watch, computed } from 'vue';

interface Department { id: number; name: string; }
interface Specialization { id: number; name: string; department_id: number; }
interface Student { id: number; full_name: string; registration_number: string; }
interface Project {
    id: number;
    project_title: string;
    academic_year: string;
    department: Department | null;
    specialization: Specialization | null;
    supervisor: { id: number; name: string } | null;
    students: Student[];
}
interface PaginationLink { url: string | null; label: string; active: boolean; }
interface Paginated {
    data: Project[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

const props = defineProps<{
    projects: Paginated;
    departments: Department[];
    specializations: Specialization[];
    years: string[];
    filters: { search?: string; department_id?: string; specialization_id?: string; academic_year?: string };
}>();

const search       = ref(props.filters.search ?? '');
const departmentId = ref(props.filters.department_id ?? '');
const specId       = ref(props.filters.specialization_id ?? '');
const year         = ref(props.filters.academic_year ?? '');

const filteredSpecs = computed(() =>
    departmentId.value
        ? props.specializations.filter(s => s.department_id === Number(departmentId.value))
        : props.specializations
);

watch(departmentId, () => { specId.value = ''; });

function applyFilters() {
    router.get(route('public.browse'), {
        search: search.value || undefined,
        department_id: departmentId.value || undefined,
        specialization_id: specId.value || undefined,
        academic_year: year.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}

function resetFilters() {
    router.get(route('public.browse'), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="تصفح المشاريع المؤرشفة — كلية التقنية الإلكترونية" />

    <div class="min-h-screen bg-background font-body text-text-dark" dir="rtl">

        <!-- HEADER -->
        <header class="sticky top-0 z-50 bg-surface border-b border-border shadow-sm">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <Link :href="route('home')" class="flex items-center gap-3">
                    <Logo size="md" />
                </Link>
                <span class="font-display font-bold text-primary hidden md:block">نظام أرشفة مشاريع التخرج</span>
                <Link
                    :href="route('login')"
                    class="inline-flex items-center px-5 py-2 rounded-lg border-2 border-primary text-primary font-body text-sm font-medium hover:bg-primary hover:text-white transition-colors"
                >
                    تسجيل الدخول
                </Link>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 py-8">

            <!-- PAGE TITLE -->
            <h1 class="font-display font-bold text-2xl text-text-dark mb-6">المشاريع المؤرشفة</h1>

            <!-- SEARCH & FILTER BAR -->
            <div class="bg-surface border border-border rounded-xl p-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="ابحث عن مشروع..."
                        class="col-span-1 sm:col-span-2 lg:col-span-1 border border-border rounded-lg px-3 py-2 text-sm text-right bg-background focus:outline-none focus:ring-2 focus:ring-primary/40"
                    />
                    <select
                        v-model="departmentId"
                        class="border border-border rounded-lg px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/40"
                    >
                        <option value="">كل الأقسام</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <select
                        v-model="specId"
                        class="border border-border rounded-lg px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/40"
                        :disabled="!departmentId && filteredSpecs.length === specializations.length"
                    >
                        <option value="">كل التخصصات</option>
                        <option v-for="s in filteredSpecs" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <select
                        v-model="year"
                        class="border border-border rounded-lg px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/40"
                    >
                        <option value="">كل السنوات</option>
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="applyFilters"
                        class="px-5 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition-colors"
                    >
                        بحث
                    </button>
                    <button
                        @click="resetFilters"
                        class="px-4 py-2 rounded-lg text-text-muted text-sm hover:text-text-dark transition-colors"
                    >
                        إعادة تعيين
                    </button>
                </div>
            </div>

            <!-- RESULTS INFO -->
            <p class="text-sm text-text-muted mb-4">
                عرض {{ projects.data.length }} من أصل {{ projects.total }} مشروع مؤرشف
            </p>

            <!-- PROJECTS GRID -->
            <div v-if="projects.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="project in projects.data"
                    :key="project.id"
                    class="bg-surface border border-border rounded-xl p-5 hover:border-primary/40 hover:shadow-md transition-all duration-200 flex flex-col"
                >
                    <h2 class="font-display font-bold text-base text-text-dark mb-3 line-clamp-2 leading-snug">
                        {{ project.project_title }}
                    </h2>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="inline-block bg-primary/10 text-primary text-xs px-2 py-0.5 rounded-full font-medium">
                            {{ project.department?.name ?? '—' }}
                        </span>
                        <span class="inline-block bg-primary-light/15 text-primary-dark text-xs px-2 py-0.5 rounded-full font-medium">
                            {{ project.specialization?.name ?? '—' }}
                        </span>
                    </div>
                    <div class="text-xs text-text-muted space-y-1 mb-4 flex-1">
                        <p>السنة الدراسية: <span class="text-text-dark font-medium">{{ project.academic_year }}</span></p>
                        <p>المشرف: <span class="text-text-dark font-medium">{{ project.supervisor?.name ?? '—' }}</span></p>
                        <p>عدد الطلبة: <span class="text-text-dark font-medium">{{ project.students.length }}</span></p>
                    </div>
                    <Link
                        :href="route('public.show', { id: project.id })"
                        class="mt-auto inline-flex items-center gap-1 text-sm text-primary font-medium hover:text-primary-dark transition-colors"
                    >
                        عرض التفاصيل
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="rotate-180 shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                    </Link>
                </div>
            </div>

            <!-- EMPTY STATE -->
            <div v-else class="text-center py-20">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-border mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-text-muted text-lg font-medium mb-4">لا توجد مشاريع تطابق البحث</p>
                <button
                    @click="resetFilters"
                    class="px-5 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition-colors"
                >
                    إعادة تعيين الفلاتر
                </button>
            </div>

            <!-- PAGINATION -->
            <div v-if="projects.last_page > 1" class="flex justify-center gap-1 mt-8 flex-wrap">
                <template v-for="link in projects.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="px-3 py-1.5 rounded-lg text-sm border transition-colors"
                        :class="link.active
                            ? 'bg-primary text-white border-primary'
                            : 'border-border text-text-muted hover:border-primary hover:text-primary'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="px-3 py-1.5 rounded-lg text-sm border border-border text-text-muted opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>

        </main>

        <!-- FOOTER -->
        <footer class="bg-primary-dark py-6 border-t border-primary/20 mt-16">
            <div class="max-w-6xl mx-auto px-6 text-center">
                <p class="font-body text-sm text-primary-light/50">
                    جميع الحقوق محفوظة &copy; {{ new Date().getFullYear() }} — كلية التقنية الإلكترونية
                </p>
            </div>
        </footer>

    </div>
</template>
