<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'

const props = defineProps<{
    title: string
    value: string | number
    icon?: Component
    sub?: string
    color?: 'blue' | 'green' | 'purple' | 'orange'
}>()

const iconClasses = computed(() => ({
    blue:   'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
    green:  'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
    purple: 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
    orange: 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400',
}[props.color ?? 'blue']))
</script>

<template>
    <div class="flex items-start gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
        <div
            v-if="icon"
            :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-lg', iconClasses]"
        >
            <component :is="icon" :size="22" />
        </div>
        <div class="min-w-0">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ title }}</p>
            <p class="mt-0.5 text-2xl font-bold text-gray-800 dark:text-gray-100">{{ value }}</p>
            <p v-if="sub" class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ sub }}</p>
        </div>
    </div>
</template>
