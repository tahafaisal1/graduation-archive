<script setup lang="ts">
import type { SimilarProject } from '@/types';

defineProps<{
    show: boolean;
    similarProjects: SimilarProject[];
}>();

const emit = defineEmits<{
    'continue': [];
    'change-title': [];
}>();
</script>

<template>
    <div
        v-if="show && similarProjects.length > 0"
        class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-700 dark:bg-yellow-900/20"
        dir="rtl"
    >
        <div class="flex items-start gap-3">
            <span class="mt-0.5 text-xl">⚠️</span>
            <div class="flex-1">
                <p class="font-medium text-yellow-800 dark:text-yellow-300">
                    تحذير: توجد مشاريع بعناوين مشابهة
                </p>
                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                    تم العثور على {{ similarProjects.length }} مشروع مشابه — تحقق من القائمة قبل المتابعة:
                </p>

                <ul class="mt-2 space-y-1">
                    <li
                        v-for="p in similarProjects"
                        :key="p.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span class="text-yellow-600 dark:text-yellow-500">•</span>
                        <a
                            :href="route('projects.show', [p.id])"
                            class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {{ p.project_title }}
                        </a>
                        <span class="text-gray-500 dark:text-gray-400">
                            — {{ p.department ?? '' }} {{ p.academic_year ? `(${p.academic_year})` : '' }}
                        </span>
                    </li>
                </ul>

                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-yellow-400 px-4 py-1.5 text-sm font-medium text-yellow-800 hover:bg-yellow-100 dark:border-yellow-600 dark:text-yellow-300 dark:hover:bg-yellow-900/40"
                        @click="emit('continue')"
                    >
                        متابعة على أي حال
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-yellow-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-yellow-700"
                        @click="emit('change-title')"
                    >
                        تغيير العنوان
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
