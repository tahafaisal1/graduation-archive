<script setup lang="ts">
interface Column {
    key: string;
    label: string;
}

defineProps<{
    columns: Column[];
    data: Record<string, unknown>[];
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300"
                    >
                        {{ col.label }}
                    </th>
                    <th v-if="$slots.actions" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                        إجراءات
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                <tr v-for="(row, index) in data" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200"
                    >
                        {{ row[col.key] }}
                    </td>
                    <td v-if="$slots.actions" class="px-4 py-3">
                        <slot name="actions" :row="row" />
                    </td>
                </tr>
                <tr v-if="data.length === 0">
                    <td
                        :colspan="columns.length + ($slots.actions ? 1 : 0)"
                        class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        لا توجد بيانات
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
