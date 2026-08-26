<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

interface Department     { id: number; name: string }
interface Specialization { id: number; name: string }
interface Supervisor     { id: number; name: string }
interface ProjectStatus  { id: number; status_name: string }
interface Proposal {
    id: number;
    title: string;
    department: Department | null;
    specialization: Specialization | null;
    supervisor: Supervisor | null;
}
interface Project {
    id: number;
    final_score: string | null;
    proposal: Proposal;
    status: ProjectStatus | null;
}
interface PaginationLink { url: string | null; label: string; active: boolean }
interface PaginatedProjects {
    data: Project[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

defineProps<{ projects: PaginatedProjects }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المشاريع', href: '/projects' },
];
</script>

<template>
    <Head title="المشاريع" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" dir="rtl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">المشاريع</h1>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">العنوان</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">القسم</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">المشرف</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الحالة</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الدرجة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="project in projects.data" :key="project.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <a :href="route('projects.show', [project.id])" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ project.proposal.title }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.proposal.department?.name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.proposal.supervisor?.name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.status?.status_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.final_score ?? '—' }}</td>
                        </tr>
                        <tr v-if="projects.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">لا توجد مشاريع بعد</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="projects.last_page > 1" class="flex gap-1">
                <template v-for="link in projects.links" :key="link.label">
                    <button
                        v-if="link.url"
                        type="button"
                        :class="['min-w-8 rounded px-3 py-1', link.active ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700']"
                        @click="router.visit(link.url)"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
