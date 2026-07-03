<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Department    { id: number; name: string }
interface Specialization { id: number; name: string; department_id: number }
interface Supervisor    { id: number; name: string }
interface ProjectStudent { id: number; full_name: string; registration_number: string }

interface Project {
    id: number;
    project_title: string;
    description: string;
    academic_year: string;
    department_id: number;
    specialization_id: number;
    supervisor_id: number;
    draft_file_path: string | null;
    students: ProjectStudent[];
}

interface Student { full_name: string; registration_number: string }

const props = defineProps<{
    project:         Project;
    departments:     Department[];
    specializations: Specialization[];
    supervisors:     Supervisor[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المشاريع',    href: '/projects' },
    { title: 'تعديل المشروع', href: '#' },
];

const form = useForm({
    project_title:     props.project.project_title,
    description:       props.project.description,
    academic_year:     props.project.academic_year,
    department_id:     props.project.department_id as number | null,
    specialization_id: props.project.specialization_id as number | null,
    supervisor_id:     props.project.supervisor_id as number | null,
    pdf_file:          null as File | null,
    students:          props.project.students.map(s => ({
        full_name:           s.full_name,
        registration_number: s.registration_number,
    })) as Student[],
});

const filteredSpecializations = computed(() =>
    form.department_id
        ? props.specializations.filter(s => s.department_id === form.department_id)
        : []
);

watch(() => form.department_id, (newVal, oldVal) => {
    if (oldVal !== null && newVal !== oldVal) form.specialization_id = null;
});

function addStudent() {
    form.students.push({ full_name: '', registration_number: '' });
}

function removeStudent(index: number) {
    if (form.students.length > 1) form.students.splice(index, 1);
}

function onFileChange(e: Event) {
    form.pdf_file = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
    form.put(route('projects.update', [props.project.id]), { forceFormData: true });
}

const currentFileName = computed(() => {
    if (!props.project.draft_file_path) return null;
    return props.project.draft_file_path.split('/').pop() ?? null;
});
</script>

<template>
    <Head title="تعديل المشروع" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">تعديل المشروع</h1>

            <div class="max-w-3xl rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            عنوان المشروع <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.project_title"
                            type="text"
                            maxlength="255"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            :class="{ 'border-red-500': form.errors.project_title }"
                        />
                        <p v-if="form.errors.project_title" class="mt-1 text-xs text-red-600">{{ form.errors.project_title }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            الوصف <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            :class="{ 'border-red-500': form.errors.description }"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <!-- Academic Year -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            السنة الدراسية <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.academic_year"
                            type="text"
                            maxlength="20"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            :class="{ 'border-red-500': form.errors.academic_year }"
                        />
                        <p v-if="form.errors.academic_year" class="mt-1 text-xs text-red-600">{{ form.errors.academic_year }}</p>
                    </div>

                    <!-- Department + Specialization -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                القسم <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.department_id"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                :class="{ 'border-red-500': form.errors.department_id }"
                            >
                                <option :value="null">اختر القسم</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                            <p v-if="form.errors.department_id" class="mt-1 text-xs text-red-600">{{ form.errors.department_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                التخصص <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.specialization_id"
                                :disabled="!form.department_id"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:disabled:bg-gray-800"
                                :class="{ 'border-red-500': form.errors.specialization_id }"
                            >
                                <option :value="null">{{ form.department_id ? 'اختر التخصص' : 'اختر القسم أولاً' }}</option>
                                <option v-for="spec in filteredSpecializations" :key="spec.id" :value="spec.id">{{ spec.name }}</option>
                            </select>
                            <p v-if="form.errors.specialization_id" class="mt-1 text-xs text-red-600">{{ form.errors.specialization_id }}</p>
                        </div>
                    </div>

                    <!-- Supervisor -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            المشرف <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.supervisor_id"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            :class="{ 'border-red-500': form.errors.supervisor_id }"
                        >
                            <option :value="null">اختر المشرف</option>
                            <option v-for="sup in supervisors" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                        </select>
                        <p v-if="form.errors.supervisor_id" class="mt-1 text-xs text-red-600">{{ form.errors.supervisor_id }}</p>
                    </div>

                    <!-- Students -->
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                الطلاب <span class="text-red-500">*</span>
                            </label>
                            <button
                                type="button"
                                class="rounded bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900/20 dark:text-blue-400"
                                @click="addStudent"
                            >
                                + إضافة طالب
                            </button>
                        </div>
                        <p v-if="form.errors.students" class="mb-2 text-xs text-red-600">{{ form.errors.students }}</p>

                        <div class="space-y-3">
                            <div
                                v-for="(student, idx) in form.students"
                                :key="idx"
                                class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">طالب {{ idx + 1 }}</span>
                                    <button
                                        v-if="form.students.length > 1"
                                        type="button"
                                        class="text-xs text-red-500 hover:text-red-700"
                                        @click="removeStudent(idx)"
                                    >
                                        ✕ حذف
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">الاسم الكامل</label>
                                        <input
                                            v-model="student.full_name"
                                            type="text"
                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            :class="{ 'border-red-500': (form.errors as Record<string,string>)[`students.${idx}.full_name`] }"
                                        />
                                        <p v-if="(form.errors as Record<string,string>)[`students.${idx}.full_name`]" class="mt-1 text-xs text-red-600">
                                            {{ (form.errors as Record<string,string>)[`students.${idx}.full_name`] }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">رقم القيد</label>
                                        <input
                                            v-model="student.registration_number"
                                            type="text"
                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            :class="{ 'border-red-500': (form.errors as Record<string,string>)[`students.${idx}.registration_number`] }"
                                        />
                                        <p v-if="(form.errors as Record<string,string>)[`students.${idx}.registration_number`]" class="mt-1 text-xs text-red-600">
                                            {{ (form.errors as Record<string,string>)[`students.${idx}.registration_number`] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PDF Upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            ملف المشروع (PDF)
                        </label>

                        <!-- Current file -->
                        <div
                            v-if="currentFileName && !form.pdf_file"
                            class="mt-1 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-700/50"
                        >
                            <span class="text-lg">📄</span>
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-sm text-gray-700 dark:text-gray-300">{{ currentFileName }}</p>
                                <p class="text-xs text-gray-500">الملف الحالي</p>
                            </div>
                            <a
                                :href="`/storage/${project.draft_file_path}`"
                                target="_blank"
                                class="text-xs text-blue-600 hover:underline dark:text-blue-400"
                            >
                                تنزيل
                            </a>
                        </div>

                        <div class="mt-2">
                            <input
                                type="file"
                                accept=".pdf"
                                class="block w-full text-sm text-gray-600 file:ml-3 file:mr-0 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-gray-400 dark:file:bg-blue-900/20 dark:file:text-blue-400"
                                @change="onFileChange"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ currentFileName ? 'اختر ملفاً جديداً لاستبدال الحالي —' : '' }} PDF فقط، الحجم الأقصى 15 ميجابايت
                            </p>
                        </div>
                        <p v-if="form.errors.pdf_file" class="mt-1 text-xs text-red-600">{{ form.errors.pdf_file }}</p>

                        <!-- Upload progress -->
                        <div v-if="form.progress" class="mt-2">
                            <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-1.5 rounded-full bg-blue-600 transition-all duration-300"
                                    :style="{ width: `${form.progress.percentage}%` }"
                                />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ form.progress.percentage }}%</p>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-3 border-t border-gray-200 pt-5 dark:border-gray-700">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'جاري الحفظ...' : 'تحديث المشروع' }}
                        </button>
                        <a
                            :href="route('projects.show', [project.id])"
                            class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            إلغاء
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </AppLayout>
</template>
