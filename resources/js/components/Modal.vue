<script setup lang="ts">
defineProps<{
    show: boolean;
    title?: string;
}>();

defineEmits<{
    close: [];
}>();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center" dir="rtl">
                <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
                <div class="relative z-10 w-full max-w-lg rounded-xl bg-white shadow-xl dark:bg-gray-800">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ title }}</h3>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            @click="$emit('close')"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="px-6 py-4">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
