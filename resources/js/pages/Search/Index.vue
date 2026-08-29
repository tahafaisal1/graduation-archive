<script setup lang="ts">
import SearchBar from '@/components/SearchBar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

// ── Types ────────────────────────────────────────────────────────────

interface ResultRow {
    type: 'proposal' | 'project';
    entity_label: string;
    route: string;
    route_id: number;
    title: string;
    description: string | null;
    academic_year: string;
    department: string | null;
    specialization: string | null;
    supervisor: string | null;
    students: string[];
}

interface PaginationLink { url: string | null; label: string; active: boolean }

interface PaginatedResults {
    data: ResultRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

// ── Props ────────────────────────────────────────────────────────────

const props = defineProps<{
    results: PaginatedResults;
    filters: { search?: string };
}>();

// ── Page globals ─────────────────────────────────────────────────────

const page = usePage<SharedData>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'البحث', href: '/search' },
];

// ── Badge styling per entity type ────────────────────────────────────

const badgeClass: Record<string, string> = {
    'مقترح': 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    'مشروع قيد التنفيذ': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    'مشروع مؤرشف': 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
};

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
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">البحث في المقترحات والمشاريع</h1>
                <SearchBar
                    v-model="searchQuery"
                    placeholder="ابحث بالعنوان أو الطالب أو المشرف أو الممتحن أو القسم..."
                    :suggestions="suggestions"
                    class="max-w-2xl"
                    @search="onSearch"
                    @select="onSelect"
                />
            </div>

            <!-- ── Results summary ────────────────────────────────── -->
            <template v-if="searchQuery">
                <p v-if="results.total > 0" class="text-sm text-gray-600 dark:text-gray-400">
                    تم العثور على
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ results.total }}</span>
                    نتيجة لـ
                    <span class="font-semibold text-blue-600 dark:text-blue-400">"{{ searchQuery }}"</span>
                </p>
                <div v-else class="flex flex-col items-center gap-3 py-16 text-gray-500 dark:text-gray-400">
                    <span class="text-4xl">🔍</span>
                    <p class="text-lg font-medium">لا توجد نتائج</p>
                    <p class="text-sm">لم يتم العثور على نتائج تطابق "{{ searchQuery }}"</p>
                </div>
            </template>

            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                اكتب كلمة بحث للعثور على المقترحات والمشاريع
            </p>

            <!-- ── Results list ──────────────────────────────────── -->
            <div v-if="results.total > 0" class="flex flex-col gap-3">
                <a
                    v-for="row in results.data"
                    :key="row.type + '-' + row.route_id"
                    :href="route(row.route, [row.route_id])"
                    class="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-700"
                >
                    <div class="flex items-center gap-2">
                        <span
                            :class="badgeClass[row.entity_label] ?? 'bg-gray-100 text-gray-700'"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >
                            {{ row.entity_label }}
                        </span>
                        <p
                            class="text-sm font-semibold text-blue-600 dark:text-blue-400"
                            v-html="highlight(row.title)"
                        />
                    </div>

                    <!-- Meta row -->
                    <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="row.department">🏛️ {{ row.department }}</span>
                        <span v-if="row.specialization">📚 {{ row.specialization }}</span>
                        <span v-if="row.academic_year">📅 {{ row.academic_year }}</span>
                        <span v-if="row.supervisor">👤 {{ row.supervisor }}</span>
                    </div>

                    <!-- Students -->
                    <div v-if="row.students.length" class="mt-1.5 flex flex-wrap gap-1">
                        <span
                            v-for="(name, i) in row.students"
                            :key="i"
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                        >
                            {{ name }}
                        </span>
                    </div>

                    <!-- Description snippet with highlight -->
                    <p
                        v-if="row.description"
                        class="mt-2 line-clamp-2 text-xs text-gray-500 dark:text-gray-400"
                        v-html="highlight(row.description)"
                    />
                </a>
            </div>

            <!-- ── Pagination ─────────────────────────────────────── -->
            <div v-if="results.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-gray-600 dark:text-gray-400">
                    صفحة {{ results.current_page }} من {{ results.last_page }}
                </p>
                <div class="flex gap-1">
                    <template v-for="link in results.links" :key="link.label">
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
