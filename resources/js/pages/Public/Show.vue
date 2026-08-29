<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Logo from '@/components/Brand/Logo.vue';

interface Department { id: number; name: string; }
interface Specialization { id: number; name: string; }
interface Student { id: number; full_name: string; registration_number: string; }
interface Examiner { id: number; full_name: string; title: string | null; }
interface Evaluation { id: number; examiner_id: number; notes: string | null; }

interface RelatedProposal {
    title: string;
    academic_year: string;
    department: Department | null;
    specialization: Specialization | null;
}

interface RelatedProject {
    id: number;
    proposal: RelatedProposal;
    supervisor: { name: string } | null;
    students: Student[];
}

interface Proposal {
    title: string;
    description: string | null;
    academic_year: string;
    draft_file_path: string | null;
    department: Department | null;
    specialization: Specialization | null;
}

interface Project {
    id: number;
    final_score: string | null;
    final_file_path: string | null;
    visit_count: number;
    proposal: Proposal;
    supervisor: { id: number; name: string } | null;
    students: Student[];
    examiners: Examiner[];
    evaluations: Evaluation[];
}

defineProps<{
    project: Project;
    related: RelatedProject[];
}>();
</script>

<template>
    <Head :title="project.proposal.title + ' — كلية التقنية الإلكترونية'" />

    <div class="min-h-screen bg-background font-body text-text-dark" dir="rtl">

        <!-- HEADER -->
        <header class="sticky top-0 z-50 bg-surface border-b border-border shadow-sm">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <Link :href="route('home')" class="flex items-center gap-3">
                    <Logo size="md" />
                </Link>
                <span class="font-display font-bold text-primary hidden md:block">نظام أرشفة مشاريع التخرج</span>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 py-8">

            <!-- BACK LINK -->
            <Link
                :href="route('public.browse')"
                class="inline-flex items-center gap-1 text-sm text-text-muted hover:text-primary mb-6 transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="m15 18-6-6 6-6"/></svg>
                العودة للمشاريع
            </Link>

            <!-- PROJECT HEADER -->
            <div class="bg-surface border border-border rounded-xl p-6 mb-6">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-2">
                    <h1 class="font-display font-bold text-2xl text-text-dark leading-snug flex-1">
                        {{ project.proposal.title }}
                    </h1>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="inline-block bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-medium">مؤرشف</span>
                        <span class="inline-flex items-center gap-1 text-sm text-text-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            {{ project.visit_count }} مشاهدة
                        </span>
                    </div>
                </div>

                <!-- INFO GRID -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mt-4 text-sm">
                    <div class="flex gap-2">
                        <span class="text-text-muted w-32 shrink-0">القسم:</span>
                        <span class="text-text-dark font-medium">{{ project.proposal.department?.name ?? '—' }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-text-muted w-32 shrink-0">التخصص:</span>
                        <span class="text-text-dark font-medium">{{ project.proposal.specialization?.name ?? '—' }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-text-muted w-32 shrink-0">السنة الدراسية:</span>
                        <span class="text-text-dark font-medium">{{ project.proposal.academic_year }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-text-muted w-32 shrink-0">المشرف:</span>
                        <span class="text-text-dark font-medium">{{ project.supervisor?.name ?? '—' }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-text-muted w-32 shrink-0">الدرجة النهائية:</span>
                        <span class="text-text-dark font-medium">
                            {{ project.final_score !== null ? project.final_score : 'غير محدد' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- STUDENTS SECTION -->
            <div v-if="project.students.length > 0" class="bg-surface border border-border rounded-xl p-6 mb-6">
                <h2 class="font-display font-bold text-lg text-text-dark mb-4">طلبة المشروع</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-right pb-2 font-medium text-text-muted">الاسم</th>
                            <th class="text-right pb-2 font-medium text-text-muted">رقم القيد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="student in project.students"
                            :key="student.id"
                            class="border-b border-border/50 last:border-0"
                        >
                            <td class="py-2 text-text-dark">{{ student.full_name }}</td>
                            <td class="py-2 text-text-muted">{{ student.registration_number }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- DESCRIPTION SECTION -->
            <div v-if="project.proposal.description" class="bg-surface border border-border rounded-xl p-6 mb-6">
                <h2 class="font-display font-bold text-lg text-text-dark mb-3">وصف المشروع</h2>
                <p class="text-sm text-text-dark leading-relaxed whitespace-pre-line">{{ project.proposal.description }}</p>
            </div>

            <!-- PDF SECTION -->
            <div v-if="project.final_file_path || project.proposal.draft_file_path" class="bg-surface border border-border rounded-xl p-6 mb-6">
                <h2 class="font-display font-bold text-lg text-text-dark mb-3">ملف المشروع</h2>
                <a
                    :href="'/storage/' + (project.final_file_path || project.proposal.draft_file_path)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    تحميل الملف
                </a>
            </div>

            <!-- RELATED PROJECTS -->
            <div v-if="related.length > 0" class="mt-8">
                <h2 class="font-display font-bold text-xl text-text-dark mb-4">مشاريع مشابهة</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="rel in related"
                        :key="rel.id"
                        class="bg-surface border border-border rounded-xl p-5 hover:border-primary/40 hover:shadow-md transition-all duration-200 flex flex-col"
                    >
                        <h3 class="font-display font-bold text-base text-text-dark mb-3 line-clamp-2 leading-snug">
                            {{ rel.proposal.title }}
                        </h3>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <span class="inline-block bg-primary/10 text-primary text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ rel.proposal.department?.name ?? '—' }}
                            </span>
                            <span class="inline-block bg-primary-light/15 text-primary-dark text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ rel.proposal.specialization?.name ?? '—' }}
                            </span>
                        </div>
                        <div class="text-xs text-text-muted space-y-1 mb-4 flex-1">
                            <p>السنة الدراسية: <span class="text-text-dark font-medium">{{ rel.proposal.academic_year }}</span></p>
                            <p>المشرف: <span class="text-text-dark font-medium">{{ rel.supervisor?.name ?? '—' }}</span></p>
                            <p>عدد الطلبة: <span class="text-text-dark font-medium">{{ rel.students.length }}</span></p>
                        </div>
                        <Link
                            :href="route('public.show', { id: rel.id })"
                            class="mt-auto inline-flex items-center gap-1 text-sm text-primary font-medium hover:text-primary-dark transition-colors"
                        >
                            عرض التفاصيل
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="rotate-180 shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                        </Link>
                    </div>
                </div>
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
