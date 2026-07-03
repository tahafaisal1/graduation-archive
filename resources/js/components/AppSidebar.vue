<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart2, BookOpen, Building2, FolderOpen, LayoutGrid, Search, Upload, UserCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import Logo from './Brand/Logo.vue';

const page = usePage<SharedData>();
const role = computed(() => page.props.auth?.user?.role ?? '');

const navByRole = computed(() => {
    switch (role.value) {
        case 'super_admin':
            return [
                { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutGrid },
                { title: 'الأقسام', href: '/departments', icon: Building2 },
                { title: 'المستخدمون', href: '/admin/users', icon: Users },
                { title: 'المشاريع', href: '/projects', icon: FolderOpen },
                { title: 'الممتحنون', href: '/examiners', icon: UserCheck },
                { title: 'البحث', href: '/search', icon: Search },
                { title: 'التقارير', href: '/reports/department', icon: BarChart2 },
                { title: 'استيراد', href: '/import', icon: Upload },
                { title: 'تصفح المشاريع', href: '/browse', icon: BookOpen },
            ];
        case 'dept_manager':
            return [
                { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutGrid },
                { title: 'المشاريع', href: '/projects', icon: FolderOpen },
                { title: 'الأقسام', href: '/departments', icon: Building2 },
                { title: 'الممتحنون', href: '/examiners', icon: UserCheck },
                { title: 'البحث', href: '/search', icon: Search },
                { title: 'التقارير', href: '/reports/department', icon: BarChart2 },
                { title: 'تصفح المشاريع', href: '/browse', icon: BookOpen },
            ];
        case 'dept_staff':
            return [
                { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutGrid },
                { title: 'المشاريع', href: '/projects', icon: FolderOpen },
                { title: 'البحث', href: '/search', icon: Search },
                { title: 'تصفح المشاريع', href: '/browse', icon: BookOpen },
            ];
        case 'supervisor':
            return [
                { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutGrid },
                { title: 'مشاريعي', href: '/projects/my', icon: FolderOpen },
                { title: 'البحث', href: '/search', icon: Search },
                { title: 'تصفح المشاريع', href: '/browse', icon: BookOpen },
            ];
        default:
            return [
                { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutGrid },
                { title: 'البحث', href: '/search', icon: Search },
                { title: 'تصفح المشاريع', href: '/browse', icon: BookOpen },
            ];
    }
});
</script>

<template>
    <Sidebar side="right" collapsible="icon" variant="inset">
        <SidebarHeader class="border-b border-sidebar-border">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')" class="flex items-center gap-3 px-1 py-2">
                            <Logo size="sm" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="navByRole" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
