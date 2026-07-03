<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import { useForm } from '@inertiajs/vue3';

interface Department { id: number; name: string }
interface Examiner {
    id: number;
    full_name: string;
    title: string | null;
    department?: Department | null;
}

const props = defineProps<{
    show: boolean;
    projectId: number;
    availableExaminers: Examiner[];
}>();

const emit = defineEmits<{
    assigned: [];
    cancelled: [];
}>();

const form = useForm({
    examiner_id: '' as number | '',
});

function submit() {
    form.post(route('projects.assign-examiner', [props.projectId]), {
        onSuccess: () => {
            form.reset();
            emit('assigned');
        },
    });
}

function cancel() {
    form.reset();
    emit('cancelled');
}
</script>

<template>
    <Modal :show="show" title="تعيين ممتحن" @close="cancel">
        <form id="assign-examiner-form" class="space-y-4" @submit.prevent="submit">
            <div v-if="availableExaminers.length === 0">
                <p class="text-sm text-gray-500">لا يوجد ممتحنون متاحون للتعيين</p>
            </div>
            <div v-else>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">اختر الممتحن</label>
                <select
                    v-model="form.examiner_id"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    :class="{ 'border-red-500': form.errors.examiner_id }"
                >
                    <option value="">-- اختر ممتحناً --</option>
                    <option v-for="examiner in availableExaminers" :key="examiner.id" :value="examiner.id">
                        {{ examiner.full_name }}{{ examiner.title ? ' — ' + examiner.title : '' }}{{ examiner.department ? ' (' + examiner.department.name + ')' : '' }}
                    </option>
                </select>
                <p v-if="form.errors.examiner_id" class="mt-1 text-xs text-red-600">{{ form.errors.examiner_id }}</p>
            </div>
        </form>
        <template #footer>
            <button
                type="button"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                @click="cancel"
            >
                إلغاء
            </button>
            <button
                v-if="availableExaminers.length > 0"
                type="submit"
                form="assign-examiner-form"
                :disabled="form.processing || !form.examiner_id"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
                تعيين
            </button>
        </template>
    </Modal>
</template>
