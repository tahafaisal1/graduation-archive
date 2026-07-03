<script setup lang="ts">
defineProps<{
    show: boolean;
    itemName?: string;
}>();

const emit = defineEmits<{
    confirmed: [];
    cancelled: [];
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
                <div class="absolute inset-0 bg-black/50" @click="emit('cancelled')" />
                <div class="relative z-10 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">تأكيد الحذف</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        هل أنت متأكد من حذف
                        <span v-if="itemName" class="font-medium text-gray-900 dark:text-gray-100">{{ itemName }}</span>؟
                        لا يمكن التراجع عن هذه العملية.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="emit('cancelled')"
                        >
                            إلغاء
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                            @click="emit('confirmed')"
                        >
                            حذف
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
