# Phase 1 Closeout — Playwright walkthrough (2026-08-29)

Real run: app served on `127.0.0.1:8123` (not 8000), a dedicated
`graduation_archive_wt` MariaDB database (`migrate:fresh --seed`, dropped
afterwards), real Mailpit SMTP on `127.0.0.1:1025` / UI `:8025`, real
super_admin login (`admin@admin.com`).

| # | Screenshot | Shows |
|---|---|---|
| 1 | `1-landing-no-login.png` | **Fix 1** — landing page: no login button in the header, hero, or CTA band (`route('login')` absent from the page). |
| 2 | `2-sidebar-no-browse.png` | **Fix 2** — authenticated sidebar (super_admin): no "تصفح المشاريع" entry. |
| 3a | `3a-search-by-student-name.png` | **Fix 3** — `/search?search=السلمي` returns 4 projects matched via a student's name, each with a "مشروع مؤرشف" badge and a link to `projects.show`. |
| 3b | `3b-browse-proposal-only-absent.png` | **Fix 3** — `/browse?search=بوابة التعليم` (a pending-proposal title) returns "لا توجد مشاريع تطابق البحث" — proposals stay out of the public browse. |
| 4 | `4-admin-users-employee-number-column.png` | **Fix 4** — `/admin/users` table header reads "الرقم الوظيفي" (grep confirmed "رقم القيد" is gone from that page). |
| 5a | `5a-create-staff-modal.png` | **Fix 5** — "إضافة موظف جديد" modal: name / email / الرقم الوظيفي / role / department, **no password field**, plus the "دعوة عبر البريد الإلكتروني" note. |
| 5b | `5b-admin-users-after-create.png` | The filled create form (password-free) before submit. |
| 5c | `5c-mailpit-inbox.png` | Mailpit inbox with the invitation message delivered. |
| 5d | `5d-invite-email.png` | The Arabic RTL invite email: exact subject, embedded college logo, inviter name ("Admin"), 24h notice, "إنشاء كلمة المرور" button, plain-text signed-URL fallback. |
| 5e | `5e-setup-password-form.png` | The signed setup link opens the password form with read-only name / email / role. |
| 5f | `5f-logged-in-dashboard.png` | After submitting a password the new user is logged in and lands on `/dashboard` (dept_manager view). |
