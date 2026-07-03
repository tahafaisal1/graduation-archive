<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { CheckCircle, XCircle } from 'lucide-vue-next'
import { ref, watch } from 'vue'

// ── Types ────────────────────────────────────────────────────────────────────

interface PreviewRow {
    row_number: number
    project_title: string
    academic_year: string
    department_code: string
    supervisor_email: string
    students: string[]
    valid: boolean
    error: string | null
}

interface ImportSummary {
    total_rows: number
    success_count: number
    failed_count: number
    failed_rows: { row_number: number; reason: string }[]
    preview_rows?: PreviewRow[]
}

interface PdfSummary {
    matched: number
    unmatched: string[]
}

const props = defineProps<{
    flash?: {
        success?: string
        error?: string
        preview?: ImportSummary
        import_summary?: ImportSummary
        pdf_summary?: PdfSummary
    }
}>()

// ── State ────────────────────────────────────────────────────────────────────

const currentStep   = ref(1)
const selectedFile  = ref<File | null>(null)
const fileInputRef  = ref<HTMLInputElement | null>(null)
const pdfInputRef   = ref<HTMLInputElement | null>(null)

const previewResult = ref<ImportSummary | null>(null)
const importResult  = ref<ImportSummary | null>(null)
const pdfResult     = ref<PdfSummary | null>(null)

// ── Inertia forms ────────────────────────────────────────────────────────────

const excelForm = useForm<{ file: File | null }>({ file: null })
const pdfForm   = useForm<{ zip_file: File | null }>({ zip_file: null })

// ── Flash watchers ───────────────────────────────────────────────────────────

watch(
    () => props.flash?.preview,
    (val) => {
        if (val) {
            previewResult.value = val
            currentStep.value   = 3
        }
    },
    { immediate: true },
)

watch(
    () => props.flash?.import_summary,
    (val) => {
        if (val) {
            importResult.value = val
            currentStep.value  = 4
        }
    },
    { immediate: true },
)

watch(
    () => props.flash?.pdf_summary,
    (val) => { if (val) pdfResult.value = val },
    { immediate: true },
)

// ── Actions ──────────────────────────────────────────────────────────────────

function onFileChange(e: Event) {
    selectedFile.value = (e.target as HTMLInputElement).files?.[0] ?? null
    excelForm.clearErrors()
}

function onPdfChange(e: Event) {
    pdfForm.zip_file = (e.target as HTMLInputElement).files?.[0] ?? null
}

function doPreview() {
    excelForm.file = selectedFile.value
    excelForm.post(route('import.preview'), { preserveState: true, preserveScroll: true })
}

function doImport() {
    excelForm.file = selectedFile.value
    excelForm.post(route('import.run'), { preserveState: true, preserveScroll: true })
}

function doUploadPdfs() {
    pdfForm.post(route('import.pdfs'), { preserveState: true, preserveScroll: true })
}

function goToStep(n: number) {
    currentStep.value = n
}

function resetWizard() {
    currentStep.value   = 1
    selectedFile.value  = null
    previewResult.value = null
    importResult.value  = null
    pdfResult.value     = null
    excelForm.reset()
    pdfForm.reset()
    if (fileInputRef.value) fileInputRef.value.value = ''
    if (pdfInputRef.value)  pdfInputRef.value.value  = ''
}

function downloadErrorCsv() {
    if (! importResult.value) return
    const rows = importResult.value.failed_rows
    const lines = ['﻿رقم الصف,سبب الخطأ', ...rows.map(r => `${r.row_number},"${r.reason.replace(/"/g, '""')}"`)]
    const blob  = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url   = URL.createObjectURL(blob)
    const a     = Object.assign(document.createElement('a'), { href: url, download: 'import_errors.csv' })
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
}

// ── Column reference ─────────────────────────────────────────────────────────

const columns = [
    { name: 'project_title',       label: 'عنوان المشروع',              required: true  },
    { name: 'description',         label: 'الوصف',                      required: false },
    { name: 'academic_year',       label: 'السنة الدراسية (2023/2024)', required: true  },
    { name: 'department_code',     label: 'رمز القسم',                  required: true  },
    { name: 'specialization_name', label: 'اسم التخصص',                 required: true  },
    { name: 'supervisor_email',    label: 'البريد الإلكتروني للمشرف',   required: true  },
    { name: 'student_1_name',      label: 'اسم الطالب الأول',           required: false },
    { name: 'student_1_reg',       label: 'رقم تسجيل الطالب الأول',     required: false },
    { name: 'student_2_name',      label: 'اسم الطالب الثاني',          required: false },
    { name: 'student_2_reg',       label: 'رقم تسجيل الطالب الثاني',    required: false },
    { name: 'student_3_name',      label: 'اسم الطالب الثالث',          required: false },
    { name: 'student_3_reg',       label: 'رقم تسجيل الطالب الثالث',    required: false },
    { name: 'final_score',         label: 'الدرجة النهائية',            required: false },
]
</script>

<template>
    <Head title="استيراد المشاريع" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 max-w-4xl mx-auto w-full" dir="rtl">

            <h1 class="text-2xl font-bold text-gray-800">استيراد المشاريع</h1>

            <!-- ── Step indicator ─────────────────────────────────────────── -->
            <div class="flex items-center gap-0">
                <template v-for="(label, idx) in ['تحميل القالب','رفع ومعاينة','تأكيد الاستيراد','رفع ملفات PDF']" :key="idx">
                    <div class="flex flex-col items-center">
                        <button
                            class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all"
                            :class="[
                                currentStep === idx + 1
                                    ? 'bg-blue-600 border-blue-600 text-white'
                                    : currentStep > idx + 1
                                        ? 'bg-green-500 border-green-500 text-white'
                                        : 'bg-white border-gray-300 text-gray-400',
                            ]"
                            @click="goToStep(idx + 1)"
                        >
                            <span v-if="currentStep <= idx + 1">{{ idx + 1 }}</span>
                            <CheckCircle v-else class="w-5 h-5" />
                        </button>
                        <span
                            class="mt-1 text-xs text-center w-20 leading-tight"
                            :class="currentStep === idx + 1 ? 'text-blue-600 font-semibold' : 'text-gray-400'"
                        >{{ label }}</span>
                    </div>
                    <div v-if="idx < 3" class="flex-1 h-0.5 mb-5 mx-1" :class="currentStep > idx + 1 ? 'bg-green-400' : 'bg-gray-200'" />
                </template>
            </div>

            <!-- ── STEP 1: Download template ──────────────────────────────── -->
            <section v-if="currentStep === 1" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-800">الخطوة 1 — تحميل قالب Excel</h2>
                <p class="text-sm text-gray-600">
                    حمّل القالب أدناه، أدخل بيانات المشاريع في الأعمدة المحددة، ثم انتقل للخطوة التالية لرفعه.
                    لا تغيّر أسماء الأعمدة في الصف الأول.
                </p>

                <!-- Column reference table -->
                <div class="overflow-x-auto rounded-lg border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-blue-600 text-white">
                            <tr>
                                <th class="px-4 py-2 text-right font-medium">اسم العمود</th>
                                <th class="px-4 py-2 text-right font-medium">الوصف</th>
                                <th class="px-4 py-2 text-center font-medium">مطلوب</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(col, i) in columns"
                                :key="col.name"
                                :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                            >
                                <td class="px-4 py-2 font-mono text-gray-700 text-xs">{{ col.name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ col.label }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span v-if="col.required" class="inline-block bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full font-medium">مطلوب</span>
                                    <span v-else class="text-gray-400 text-xs">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <a
                        :href="route('import.template')"
                        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition"
                    >
                        تحميل قالب Excel
                    </a>
                    <button
                        class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2.5 rounded-lg transition"
                        @click="goToStep(2)"
                    >
                        التالي ←
                    </button>
                </div>
            </section>

            <!-- ── STEP 2: Upload & Preview ───────────────────────────────── -->
            <section v-if="currentStep === 2" class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
                <h2 class="text-lg font-semibold text-gray-800">الخطوة 2 — رفع ومعاينة الملف</h2>
                <p class="text-sm text-gray-600">ارفع ملف Excel لمعاينة البيانات والتحقق من صحتها قبل الحفظ.</p>

                <!-- File picker -->
                <div class="flex items-center gap-3">
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".xlsx,.xls"
                        class="hidden"
                        @change="onFileChange"
                    />
                    <button
                        type="button"
                        class="border-2 border-dashed border-gray-300 hover:border-blue-400 text-gray-600 text-sm px-6 py-3 rounded-lg transition flex items-center gap-2"
                        @click="fileInputRef?.click()"
                    >
                        <span>{{ selectedFile ? selectedFile.name : 'اختر ملف xlsx أو xls' }}</span>
                    </button>
                    <button
                        :disabled="!selectedFile || excelForm.processing"
                        class="bg-amber-500 hover:bg-amber-600 disabled:opacity-40 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition"
                        @click="doPreview"
                    >
                        {{ excelForm.processing ? 'جارٍ المعاينة...' : 'معاينة' }}
                    </button>
                </div>
                <p v-if="excelForm.errors.file" class="text-sm text-red-600">{{ excelForm.errors.file }}</p>

                <!-- Preview results -->
                <template v-if="previewResult">
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="bg-gray-50 rounded-xl p-4 border">
                            <div class="text-3xl font-bold text-gray-800">{{ previewResult.total_rows }}</div>
                            <div class="text-xs text-gray-500 mt-1">إجمالي الصفوف</div>
                        </div>
                        <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                            <div class="text-3xl font-bold text-green-700">{{ previewResult.success_count }}</div>
                            <div class="text-xs text-gray-500 mt-1">سيُستورد</div>
                        </div>
                        <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                            <div class="text-3xl font-bold text-red-600">{{ previewResult.failed_count }}</div>
                            <div class="text-xs text-gray-500 mt-1">أخطاء</div>
                        </div>
                    </div>

                    <!-- Row preview table -->
                    <div v-if="previewResult.preview_rows && previewResult.preview_rows.length" class="overflow-x-auto rounded-lg border">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-3 py-2 text-right">#</th>
                                    <th class="px-3 py-2 text-right">عنوان المشروع</th>
                                    <th class="px-3 py-2 text-right">السنة</th>
                                    <th class="px-3 py-2 text-right">القسم</th>
                                    <th class="px-3 py-2 text-right">المشرف</th>
                                    <th class="px-3 py-2 text-right">الطلاب</th>
                                    <th class="px-3 py-2 text-center">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="row in previewResult.preview_rows"
                                    :key="row.row_number"
                                    :class="row.valid ? 'bg-white' : 'bg-red-50'"
                                >
                                    <td class="px-3 py-2 text-gray-400">{{ row.row_number }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-800 max-w-[180px] truncate">{{ row.project_title || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ row.academic_year || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ row.department_code || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 max-w-[140px] truncate">{{ row.supervisor_email || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ row.students.join('، ') || '—' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span v-if="row.valid" title="صالح">
                                            <CheckCircle class="w-4 h-4 text-green-500 mx-auto" />
                                        </span>
                                        <span v-else :title="row.error ?? ''">
                                            <XCircle class="w-4 h-4 text-red-500 mx-auto" />
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="previewResult.total_rows > 10" class="text-xs text-gray-400 p-2 text-center">
                            يُعرض أول 10 صفوف فقط — إجمالي {{ previewResult.total_rows }} صف
                        </p>
                    </div>

                    <!-- Error list -->
                    <div v-if="previewResult.failed_rows.length" class="rounded-lg border border-red-200 bg-red-50 p-4 space-y-1.5">
                        <p class="text-sm font-semibold text-red-700 mb-2">الصفوف التي تحتوي على أخطاء:</p>
                        <div
                            v-for="row in previewResult.failed_rows"
                            :key="row.row_number"
                            class="flex items-start gap-2 text-sm text-red-700"
                        >
                            <XCircle class="w-4 h-4 mt-0.5 shrink-0" />
                            <span>صف {{ row.row_number }}: {{ row.reason }}</span>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            v-if="previewResult.success_count > 0"
                            class="bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition"
                            @click="goToStep(3)"
                        >
                            المتابعة إلى تأكيد الاستيراد ←
                        </button>
                        <button
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-4 py-2.5 rounded-lg transition"
                            @click="doPreview"
                        >
                            إعادة المعاينة
                        </button>
                    </div>
                </template>
            </section>

            <!-- ── STEP 3: Confirm import ──────────────────────────────────── -->
            <section v-if="currentStep === 3" class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
                <h2 class="text-lg font-semibold text-gray-800">الخطوة 3 — تأكيد الاستيراد</h2>

                <!-- Pre-import summary -->
                <template v-if="!importResult">
                    <div v-if="previewResult" class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                        سيتم استيراد <strong>{{ previewResult.success_count }}</strong> مشروع صالح
                        <span v-if="previewResult.failed_count">
                            (سيُتخطى {{ previewResult.failed_count }} صف به أخطاء)
                        </span>.
                    </div>

                    <div class="flex gap-3">
                        <button
                            :disabled="!selectedFile || excelForm.processing"
                            class="bg-blue-700 hover:bg-blue-800 disabled:opacity-40 text-white text-sm font-bold px-7 py-3 rounded-lg transition flex items-center gap-2"
                            @click="doImport"
                        >
                            <span v-if="excelForm.processing" class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                جارٍ الاستيراد...
                            </span>
                            <span v-else>استيراد الصفوف الصالحة</span>
                        </button>
                        <button
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-4 py-3 rounded-lg transition"
                            @click="goToStep(2)"
                        >
                            ← العودة
                        </button>
                    </div>
                </template>

                <!-- Post-import results -->
                <template v-if="importResult">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-xl p-5 text-center">
                            <div class="text-4xl font-bold text-green-700">{{ importResult.success_count }}</div>
                            <div class="text-sm text-gray-600 mt-1">مشروع تم استيراده بنجاح</div>
                        </div>
                        <div class="bg-red-50 border border-red-200 rounded-xl p-5 text-center">
                            <div class="text-4xl font-bold text-red-600">{{ importResult.failed_count }}</div>
                            <div class="text-sm text-gray-600 mt-1">صف فشل الاستيراد</div>
                        </div>
                    </div>

                    <!-- Failed rows table -->
                    <div v-if="importResult.failed_rows.length" class="overflow-x-auto rounded-lg border border-red-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-red-50 text-red-700">
                                <tr>
                                    <th class="px-4 py-2 text-right font-medium">رقم الصف</th>
                                    <th class="px-4 py-2 text-right font-medium">سبب الخطأ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-100">
                                <tr v-for="row in importResult.failed_rows" :key="row.row_number" class="bg-white">
                                    <td class="px-4 py-2 font-mono text-gray-600">{{ row.row_number }}</td>
                                    <td class="px-4 py-2 text-red-700">{{ row.reason }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-1">
                        <button
                            v-if="importResult.failed_count > 0"
                            class="bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium px-5 py-2.5 rounded-lg transition"
                            @click="downloadErrorCsv"
                        >
                            تحميل تقرير الأخطاء (CSV)
                        </button>
                        <button
                            class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition"
                            @click="goToStep(4)"
                        >
                            رفع ملفات PDF ←
                        </button>
                        <button
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-5 py-2.5 rounded-lg transition"
                            @click="resetWizard"
                        >
                            استيراد ملف آخر
                        </button>
                    </div>
                </template>
            </section>

            <!-- ── STEP 4: Upload PDFs ─────────────────────────────────────── -->
            <section v-if="currentStep === 4" class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
                <h2 class="text-lg font-semibold text-gray-800">الخطوة 4 — رفع ملفات PDF (اختياري)</h2>
                <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                    ارفع ملف ZIP يحتوي على ملفات PDF للمشاريع.
                    <strong>يجب أن يكون اسم كل ملف PDF مطابقاً تماماً لعنوان المشروع</strong> (بدون امتداد .pdf).
                    مثال: إذا كان عنوان المشروع "نظام إدارة المخزون" فالملف يجب أن يسمى <code class="bg-amber-100 px-1 rounded">نظام إدارة المخزون.pdf</code>
                </div>

                <div class="flex items-center gap-3">
                    <input
                        ref="pdfInputRef"
                        type="file"
                        accept=".zip"
                        class="hidden"
                        @change="onPdfChange"
                    />
                    <button
                        type="button"
                        class="border-2 border-dashed border-gray-300 hover:border-purple-400 text-gray-600 text-sm px-6 py-3 rounded-lg transition"
                        @click="pdfInputRef?.click()"
                    >
                        {{ pdfForm.zip_file ? pdfForm.zip_file.name : 'اختر ملف ZIP' }}
                    </button>
                    <button
                        :disabled="!pdfForm.zip_file || pdfForm.processing"
                        class="bg-purple-600 hover:bg-purple-700 disabled:opacity-40 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition"
                        @click="doUploadPdfs"
                    >
                        {{ pdfForm.processing ? 'جارٍ الرفع...' : 'رفع ملفات PDF' }}
                    </button>
                </div>
                <p v-if="pdfForm.errors.zip_file" class="text-sm text-red-600">{{ pdfForm.errors.zip_file }}</p>

                <!-- PDF results -->
                <template v-if="pdfResult">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                            <div class="text-3xl font-bold text-green-700">{{ pdfResult.matched }}</div>
                            <div class="text-xs text-gray-500 mt-1">ملف PDF تم ربطه</div>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-center">
                            <div class="text-3xl font-bold text-gray-600">{{ pdfResult.unmatched.length }}</div>
                            <div class="text-xs text-gray-500 mt-1">لم يُطابق مشروعاً</div>
                        </div>
                    </div>
                    <div v-if="pdfResult.unmatched.length" class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-1">
                        <p class="text-sm font-medium text-gray-700 mb-2">الملفات غير المطابقة:</p>
                        <div
                            v-for="name in pdfResult.unmatched"
                            :key="name"
                            class="flex items-center gap-2 text-sm text-gray-600"
                        >
                            <XCircle class="w-4 h-4 text-gray-400 shrink-0" />
                            <span class="font-mono">{{ name }}.pdf</span>
                        </div>
                    </div>
                </template>

                <div class="flex gap-3 pt-1">
                    <button
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-5 py-2.5 rounded-lg transition"
                        @click="resetWizard"
                    >
                        استيراد ملف آخر
                    </button>
                </div>
            </section>

        </div>
    </AppLayout>
</template>
