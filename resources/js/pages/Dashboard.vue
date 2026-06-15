<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
];

const page = usePage();
const user = computed(() => page.props.auth.user);

const props = defineProps<{
    stats: Record<string, number>;
}>();

const statLabels: Record<string, string> = {
    total_users: 'إجمالي المستخدمين',
    total_projects: 'إجمالي المشاريع',
    total_departments: 'الأقسام',
    dept_projects: 'مشاريع القسم',
    supervised_projects: 'المشاريع المُشرف عليها',
};
</script>

<template>
    <Head title="لوحة التحكم" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-gray-800">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    مرحباً، {{ user.name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    الدور: {{ user.role ?? '—' }}
                </p>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    v-for="(value, key) in props.stats"
                    :key="key"
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-gray-800"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ statLabels[key] ?? key }}
                    </p>
                    <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-gray-100">
                        {{ value }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
