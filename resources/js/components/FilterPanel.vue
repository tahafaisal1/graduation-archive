<script setup lang="ts">
import { computed, ref, watch } from 'vue';

interface Specialization { id: number; name: string; department_id: number }
interface Department    { id: number; name: string; specializations?: Specialization[] }
interface Supervisor    { id: number; name: string }

export interface FilterValues {
    department_id:    string | number;
    specialization_id: string | number;
    academic_year:    string;
    supervisor_id:    string | number;
    sort:             string;
    status:           string;
}

const props = defineProps<{
    departments:    Department[];
    specializations: Specialization[];
    years:          string[];
    supervisors:    Supervisor[];
    modelValue:     FilterValues;
}>();

const emit = defineEmits<{
    'filter-changed': [filters: FilterValues];
}>();

const open = ref(false);

const local = ref<FilterValues>({ ...props.modelValue });

// Specializations matching the selected department
const filteredSpecs = computed(() =>
    props.specializations.filter(s => s.department_id == local.value.department_id),
);

// Number of active non-sort filters
const activeCount = computed(() =>
    [local.value.department_id, local.value.specialization_id, local.value.academic_year, local.value.supervisor_id, local.value.status]
        .filter(v => v !== '' && v != null).length,
);

// When department changes, clear specialization and emit
watch(() => local.value.department_id, () => {
    local.value.specialization_id = '';
    emit('filter-changed', { ...local.value });
});

// All other filter changes emit immediately
watch(
    () => [local.value.specialization_id, local.value.academic_year, local.value.supervisor_id, local.value.sort, local.value.status],
    () => emit('filter-changed', { ...local.value }),
);

// Sync if parent resets modelValue externally
watch(() => props.modelValue, (val) => {
    local.value = { ...val };
}, { deep: true });

function reset() {
    local.value = { department_id: '', specialization_id: '', academic_year: '', supervisor_id: '', sort: 'created_at', status: '' };
    emit('filter-changed', { ...local.value });
}

const selectClass = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700" dir="rtl">
        <!-- Toggle header -->
        <button
            type="button"
            class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800/50"
            @click="open = !open"
        >
            <span class="flex items-center gap-2">
                <span>الفلاتر المتقدمة</span>
                <span
                    v-if="activeCount > 0"
                    class="rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white"
                >
                    {{ activeCount }}
                </span>
            </span>
            <span class="text-gray-400">{{ open ? '▲' : '▼' }}</span>
        </button>

        <!-- Panel body -->
        <div v-show="open" class="grid grid-cols-2 gap-3 border-t border-gray-200 p-4 dark:border-gray-700 lg:grid-cols-3">
            <!-- Department -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">القسم</label>
                <select v-model="local.department_id" :class="selectClass">
                    <option value="">كل الأقسام</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>

            <!-- Specialization (cascades on department) -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">التخصص</label>
                <select v-model="local.specialization_id" :class="selectClass" :disabled="!local.department_id">
                    <option value="">كل التخصصات</option>
                    <option v-for="s in filteredSpecs" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>

            <!-- Academic year -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">السنة الدراسية</label>
                <select v-model="local.academic_year" :class="selectClass">
                    <option value="">كل السنوات</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <!-- Supervisor -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">المشرف</label>
                <select v-model="local.supervisor_id" :class="selectClass">
                    <option value="">كل المشرفين</option>
                    <option v-for="sup in supervisors" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                </select>
            </div>

            <!-- Status -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">الحالة</label>
                <select v-model="local.status" :class="selectClass">
                    <option value="">كل المشاريع</option>
                    <option value="active">المؤرشفة فقط</option>
                </select>
            </div>

            <!-- Sort -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">الترتيب</label>
                <select v-model="local.sort" :class="selectClass">
                    <option value="created_at">الأحدث أولاً</option>
                    <option value="title">حسب العنوان</option>
                    <option value="visit_count">الأكثر زيارة</option>
                </select>
            </div>

            <!-- Reset row -->
            <div class="col-span-full flex justify-end">
                <button
                    v-if="activeCount > 0"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                    @click="reset"
                >
                    إعادة تعيين الفلاتر
                </button>
            </div>
        </div>
    </div>
</template>
