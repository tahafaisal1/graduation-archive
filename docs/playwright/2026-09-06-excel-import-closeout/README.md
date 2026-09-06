# Manual verification — Excel import closeout (2026-09-06)

Server: `php artisan serve --port=8123`, MariaDB `graduation_archive`, logged in as `admin@admin.com` (super_admin).
Fixture: 4-row xlsx — an `[EXAMPLE]` row (must skip) + 3 valid rows with mixed shapes; ZIP with 2 title-matched PDFs.

| # | Screenshot | What it shows |
|---|---|---|
| 1 | `01-step1-columns-and-example-note.png` | Step 1 column-help table lists `examiner_1_name` / `examiner_1_notes` / `examiner_2_name` / `examiner_2_notes` as "اختياري" (—); amber note "اترك السطر الأول (المثال) كما هو أو احذفه — لن يُستورد." |
| 2 | `02-step3-preimport-copy.png` | Pre-import summary: "سيتم إنشاء مقترح مؤرشف ومشروع مقابل لكل صف صالح — **3** صف" (the `[EXAMPLE]` row correctly excluded) |
| 3 | `03-step3-results-no-autoadvance.png` | After import the wizard stays on **step 3** (step-4 not reached): "**3** مقترح ومشروع تم أرشفتهم بنجاح", "**0** صف فشل الاستيراد". Buttons let the user choose "رفع ملفات PDF ←" or "استيراد ملف آخر". |
| 4 | `04-pdf-upload-results.png` | Step 4 ZIP upload: "**2** ملف PDF تم ربطه", "**0** لم يُطابق مشروعاً" |
| 5 | `05-project32-examiners-score-finalfile.png` | Imported project (2 examiners + 2 notes row): score 88 (ناجح), المناقشون 2/2 with evaluation notes, students with reg numbers, "الملف النهائي" download card present |
| 6 | `06-project34-no-broken-storage-null.png` | Imported project (0 examiners, no score, no PDF): archived, "لم تُسجَّل درجة بعد", "لم يتم تعيين ممتحنين بعد", **no "الملف النهائي" card at all** — the `/storage/null` link is gone (Fix 4b). 0 `a[href="/storage/null"]` elements on the page. |
| 7 | `07-public-browse32-file-served.png` | Public `/browse/32` still shows the "تحميل الملف" link — `Public/Show.vue` fallback (`final_file_path || draft_file_path`) still works (regression check) |

Template download parsed (`downloaded_template.xlsx`): 17 headers, ending
`… | final_score | examiner_1_name | examiner_1_notes | examiner_2_name | examiner_2_notes`;
example row populated including the 4 new cells.

DB after import (`proposals` titled `%161113%`):
- `نظام أرشفة المكتبة` → proposal.status_id=1 (ARCHIVED), project score 88.00, `final_file_path=projects/final/…pdf`, 2 examiners, 2 evaluations, both students have reg
- `منصة التدريب` → score 75.50, `final_file_path` set (ZIP match), 1 examiner, 0 evaluations (name, no notes), student `registration_number = NULL` (Fix 4a)
- `تطبيق الحضور` → ungraded, `final_file_path` NULL, 0 examiners — still archived
