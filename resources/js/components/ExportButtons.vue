<script setup lang="ts">
import { FileDown, FileSpreadsheet } from 'lucide-vue-next'

defineProps<{
    pdfUrl: string
    excelUrl: string
}>()
</script>

<template>
    <div class="flex items-center gap-2">
        <a
            :href="pdfUrl"
            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30"
        >
            <FileDown :size="15" />
            PDF
        </a>
        <a
            :href="excelUrl"
            class="inline-flex items-center gap-1.5 rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 transition hover:bg-green-100 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/30"
        >
            <FileSpreadsheet :size="15" />
            Excel
        </a>
    </div>
</template>
