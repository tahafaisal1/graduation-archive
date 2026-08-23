# Remove Ghost "في انتظار الموافقة" Status Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate every remaining occurrence of the ghost status label "في انتظار الموافقة" (a leftover from the removed digital-approval concept) so the Projects Index/Show pages render exactly two status badges — "مقترح" and "مؤرشف" — matching the paper-based-approval model already documented in CLAUDE.md.

**Architecture:** Investigation (already completed, findings below) found the bug is confined to one frontend file: `resources/js/composables/useProjectStatus.ts` still maps the "مقترح" status to the display label "في انتظار الموافقة" with a yellow "pending" color, even though the DB, backend authorization, and both Vue pages were already correctly migrated to the two-status/paper-approval model in the prior "Paper-Approval Proposal Lifecycle" change. The fix corrects the composable's label/color map, renames its misleading `isPendingApproval` helper (no "approval" concept exists anymore), and removes the same banned string from Dashboard.vue's stat card. No DB migration, no backend authorization change, and no Show.vue template change are needed — those are already correct (see Investigation Findings).

**Tech Stack:** Laravel 12 / PHP 8.2, Vue 3 + Inertia.js 2 + TypeScript, Pest PHP (backend tests). No JS unit-test runner (vitest/jest) is configured in this repo — verification of the composable relies on Pest tests against the actual data contract it consumes, plus `npm run build`.

**Spec:** User's task message (2026-08-24) — "Projects Index page currently displays THREE status badges... find and eliminate [the ghost status] completely," Steps 1–7.

## Global Constraints

- Exactly 2 statuses in the UI: "مقترح" and "مؤرشف" — no third string, anywhere a status is rendered.
- No yellow "pending" color anywhere in status rendering.
- Do not reintroduce any digital-approval concept (no `canApprove`, no "awaiting" language) — approval is paper-based, outside the system (see CLAUDE.md "Key Business Rules").
- Do not touch DB data or migrations — investigation confirmed no stale rows exist (see below).
- Do not weaken or restructure the existing backend authorization (`Project::canBeModifiedBy()`/`canBeArchivedBy()`, the three FormRequests) — investigation confirmed it already matches the required permission matrix exactly.
- Branch: `fix/remove-ghost-pending-status`, worktree at `.claude/worktrees/remove-ghost-pending-status`. Do not merge to `main`.

---

## Investigation Findings (Step 1 — already completed, no further action needed for these)

1. **Root cause, confirmed:** `resources/js/composables/useProjectStatus.ts` lines 9–11:
   ```ts
   const STATUS_LABELS: Record<string, string> = {
       [STATUS_ARCHIVED]: 'مؤرشف',
       [STATUS_PROPOSAL]: 'في انتظار الموافقة',   // ← the bug
   };
   ```
   and line 6: `[STATUS_PROPOSAL]: 'bg-yellow-100 text-yellow-700 ...'` (yellow "pending" color). This composable is imported by both `Projects/Index.vue` and `Projects/Show.vue` (`statusLabel`/`statusColor`/`isPendingApproval`) — it is the single source of every ghost badge in production.

2. **Second occurrence (same banned string, different page):** `resources/js/pages/Dashboard.vue:112` — a `StatsCard` titled literally `"في انتظار الموافقة"` bound to `stats.pending_approvals` (a count of `current_status_id = 2` / "مقترح" projects, from `ReportService::getDashboardStats()`). Not a status badge, but the same misleading "awaiting approval" language the task asks to eliminate everywhere. `ReportService.php:12` has a matching stale comment (`// proposal_submitted — awaiting dept_manager approval`).

3. **No other occurrences of the banned string or English equivalents** ("pending approval", "awaiting", etc.) render to a user. Remaining hits from the codebase-wide grep are all inert: historical plan docs (`docs/superpowers/plans/2026-08-17-*.md`, `2026-08-23-*.md` — write-ups of already-completed past work, not live code), old migration files (`2026_08_17_220723_add_approval_gates...`, `2026_08_23_120000_drop_approval_gate...` — these *add then drop* the columns; both are historical and correct to leave alone), CLAUDE.md/PROGRESS.md prose describing the same history, and a Pest test name string `'dept_staff can create project with pending approval status'` (just a test description, asserts on `current_status_id`, not on any rendered label).

4. **`supervisor_approved_at`/`department_approved_by` etc. — already fully removed.** All remaining grep hits are historical (migration `drop_approval_gate_columns...`, docs, CLAUDE.md changelog prose). No live model, controller, or Vue file references them. `canApprove` does not exist anywhere in current Vue code (only in historical plan docs describing its removal).

5. **Database — clean, no stale rows.** Queried directly:
   - `project_status` table: exactly 2 rows — `{id:1, status_name:"مؤرشف", sort_order:2}`, `{id:2, status_name:"مقترح", sort_order:1}`.
   - `projects.current_status_id` distribution: `{1: 20, 2: 5}` — all 25 projects reference one of the two valid status IDs. **No migration to reassign stale rows is needed (Step 2 of the user's task is a no-op).**

6. **Backend authorization — already fully correct**, matching the user's Step 4 requirement verbatim:
   - `Project::canBeModifiedBy()`/`canBeArchivedBy()` (`app/Models/Project.php:72-98`) already gate on `current_status_id === STATUS_PENDING` + ownership/department, with `super_admin` bypassing both checks.
   - `UpdateProjectRequest`, `DeleteProjectRequest`, `ArchiveProjectRequest` already call these methods and return `false` (→ 403) otherwise.
   - `ProjectController::edit()` (`app/Http/Controllers/ProjectController.php:148`) already calls `canBeModifiedBy()` and `abort(403)`s.
   - Existing Pest tests already cover "dept_manager cannot replace/delete/re-archive an archived project" and "super_admin can replace an archived project" (`tests/Feature/Project/ProjectTest.php:194,313,331,414`). **No new backend authorization code or 403 tests are needed — this is already shipped and tested.**

7. **`Projects/Index.vue` row-action gating — already fully correct**, matching Step 4's UI requirement: `canReplace`/`canDeleteProject`/`canArchive` (lines 197-217) already resolve to "show only عرض" for a non-super_admin on an archived (`مؤرشف`) row, because they all short-circuit through `isPendingApproval(...)`. Once Task 1 below fixes the composable, no template changes to the row-actions column are needed.

8. **`Projects/Show.vue` — already fully correct** per Step 5: the page renders only title, description, students, examiners/evaluations, meta info (department/specialization/supervisor/year), score, status badge, and PDF download. No approval-related section exists anywhere in the template. No changes needed there beyond what Task 1's composable fix cascades in automatically (the status badge on this page uses the same `statusLabel`/`statusColor`).

**Net effect:** the fix is far smaller than the task's worst-case framing — one composable file, one dashboard label, one stale comment, plus tests and docs. No migration, no controller/FormRequest change, no Show.vue template change.

---

## File Structure

- Modify `resources/js/composables/useProjectStatus.ts` — fix the label/color map; rename `isPendingApproval` → `isProposalStatus` (the function is legitimate permission-gating logic, not dead code — but its name embeds the exact "approval" framing this task eliminates).
- Modify `resources/js/pages/Projects/Index.vue` — update the renamed import.
- Modify `resources/js/pages/Projects/Show.vue` — update the renamed import.
- Modify `resources/js/pages/Dashboard.vue` — replace the banned string in the `StatsCard` title.
- Modify `app/Services/ReportService.php` — fix the stale comment (no behavior change).
- Modify `tests/Feature/Project/ProjectTest.php` — add one test asserting the Projects Index Inertia response never renders a third status value.
- Modify `CLAUDE.md`, `PROGRESS.md` — add a "Bugfixes / Corrections" note per Step 7.

---

### Task 1: Fix the ghost badge in useProjectStatus.ts

**Files:**
- Modify: `resources/js/composables/useProjectStatus.ts`
- Modify: `resources/js/pages/Projects/Index.vue:7,199,216`
- Modify: `resources/js/pages/Projects/Show.vue:6,67`
- Test: `tests/Feature/Project/ProjectTest.php` (new test appended)

**Interfaces:**
- Consumes: nothing new — `STATUS_ARCHIVED`/`STATUS_PROPOSAL` string constants already exported from the composable.
- Produces: `statusLabel(name)` now returns only `'مقترح'` or `'مؤرشف'` (or the raw joined name as a fallback for an unrecognized value, per the user's Step 3 spec — never an invented third label). `statusColor(name)` now returns only a blue token for `'مقترح'` and the existing green token for `'مؤرشف'`. `isProposalStatus(name)` replaces `isPendingApproval(name)` with an identical boolean signature (`(name?: string | null) => boolean`) — a straight rename, same behavior, consumed by `Index.vue` and `Show.vue`.

- [ ] **Step 1: Write the failing Pest test**

Append to `tests/Feature/Project/ProjectTest.php` (this file already has `use App\Models\Project;` and role/department helpers in scope — follow the existing test style in the file):

```php
test('projects index never renders a status other than مقترح or مؤرشف', function () {
    $manager = userWithRole('dept_manager');

    $department = \App\Models\Department::factory()->create(['id' => $manager->department_id]);
    $specialization = \App\Models\Specialization::factory()->create(['department_id' => $department->id]);
    $supervisor = userWithRole('supervisor');

    \App\Models\Project::factory()->create([
        'department_id'      => $department->id,
        'specialization_id'  => $specialization->id,
        'supervisor_id'      => $supervisor->id,
        'current_status_id'  => Project::STATUS_PENDING,
    ]);
    \App\Models\Project::factory()->create([
        'department_id'      => $department->id,
        'specialization_id'  => $specialization->id,
        'supervisor_id'      => $supervisor->id,
        'current_status_id'  => Project::STATUS_ARCHIVED,
    ]);

    $response = $this->actingAs($manager)->get(route('projects.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Index')
        ->where('projects.data', fn ($rows) => collect($rows)
            ->pluck('current_status.status_name')
            ->every(fn ($name) => in_array($name, ['مقترح', 'مؤرشف'], true)))
    );
});
```

This test asserts the *data contract* `useProjectStatus.ts` consumes (`current_status.status_name`) never carries a third value — the real risk surface, since there is no JS test runner in this repo to unit-test `statusLabel()` directly (confirmed: no vitest/jest config exists, `package.json` has no `test` script).

- [ ] **Step 2: Run test to verify it currently passes (this test targets backend data, which is already correct — it is a regression guard, not a reproduction of the frontend bug)**

Run: `php artisan test --filter="projects index never renders a status other than"`
Expected: PASS (the backend data was already correct per Investigation Finding #5 — this step confirms the test itself is valid before moving on).

- [ ] **Step 3: Fix the label and color map**

In `resources/js/composables/useProjectStatus.ts`, replace the full file with:

```ts
export const STATUS_ARCHIVED = 'مؤرشف';
export const STATUS_PROPOSAL = 'مقترح';

const STATUS_COLORS: Record<string, string> = {
    [STATUS_ARCHIVED]: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    [STATUS_PROPOSAL]: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
};

const STATUS_LABELS: Record<string, string> = {
    [STATUS_ARCHIVED]: 'مؤرشف',
    [STATUS_PROPOSAL]: 'مقترح',
};

export function statusColor(name?: string | null) {
    return (name && STATUS_COLORS[name]) ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
}

export function statusLabel(name?: string | null) {
    return (name && STATUS_LABELS[name]) ?? name ?? '';
}

export function isProposalStatus(name?: string | null) {
    return name === STATUS_PROPOSAL;
}

export function useProjectStatus() {
    return { STATUS_ARCHIVED, STATUS_PROPOSAL, statusColor, statusLabel, isProposalStatus };
}
```

- [ ] **Step 4: Update the rename in Projects/Index.vue**

In `resources/js/pages/Projects/Index.vue`:

Line 7 — change:
```ts
import { isPendingApproval, statusColor, statusLabel } from '@/composables/useProjectStatus';
```
to:
```ts
import { isProposalStatus, statusColor, statusLabel } from '@/composables/useProjectStatus';
```

Line 199 (inside `canModify`) — change:
```ts
if (!isPendingApproval(p.current_status?.status_name)) return false;
```
to:
```ts
if (!isProposalStatus(p.current_status?.status_name)) return false;
```

Line 216 (inside `canArchive`) — change:
```ts
|| (userRole.value === 'dept_manager' && isPendingApproval(p.current_status?.status_name) && user.department_id === p.department_id);
```
to:
```ts
|| (userRole.value === 'dept_manager' && isProposalStatus(p.current_status?.status_name) && user.department_id === p.department_id);
```

- [ ] **Step 5: Update the rename in Projects/Show.vue**

In `resources/js/pages/Projects/Show.vue`:

Line 6 — change:
```ts
import { isPendingApproval, statusColor, statusLabel } from '@/composables/useProjectStatus';
```
to:
```ts
import { isProposalStatus, statusColor, statusLabel } from '@/composables/useProjectStatus';
```

Line 67 — change:
```ts
const isPending    = computed(() => isPendingApproval(props.project.current_status?.status_name));
```
to:
```ts
const isPending    = computed(() => isProposalStatus(props.project.current_status?.status_name));
```

- [ ] **Step 6: Run the backend test again to confirm nothing broke**

Run: `php artisan test --filter="projects index never renders a status other than"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add resources/js/composables/useProjectStatus.ts resources/js/pages/Projects/Index.vue resources/js/pages/Projects/Show.vue tests/Feature/Project/ProjectTest.php
git commit -m "fix: remove ghost 'في انتظار الموافقة' status badge from useProjectStatus"
```

---

### Task 2: Remove the same banned phrase from the Dashboard stat card

**Files:**
- Modify: `resources/js/pages/Dashboard.vue:112`
- Modify: `app/Services/ReportService.php:12` (comment only, no behavior change)

**Interfaces:**
- Consumes: nothing new — `stats.pending_approvals` prop (unchanged key name; only the displayed Arabic title text changes, so `tests/Feature/Report/ReportTest.php`'s existing `->has('stats.pending_approvals')` assertion is unaffected).
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Fix the StatsCard title**

In `resources/js/pages/Dashboard.vue`, line 112, change:
```vue
                        title="في انتظار الموافقة"
```
to:
```vue
                        title="مشاريع مقترحة"
```
(This stat counts `current_status_id = 2` / "مقترح" projects — "مشاريع مقترحة" ("proposed projects") describes exactly that, with no "awaiting approval" framing.)

- [ ] **Step 2: Fix the stale comment in ReportService**

In `app/Services/ReportService.php`, line 12, change:
```php
    private const STATUS_PROPOSAL = 2; // proposal_submitted — awaiting dept_manager approval
```
to:
```php
    private const STATUS_PROPOSAL = 2; // مقترح — see Project::STATUS_PENDING
```

- [ ] **Step 3: Run the existing report test to confirm no regression**

Run: `php artisan test --filter=ReportTest`
Expected: PASS (all existing assertions target the `pending_approvals` key and its numeric value, not the Arabic title text, so this is a pure regression check).

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Dashboard.vue app/Services/ReportService.php
git commit -m "fix: remove leftover 'awaiting approval' language from dashboard stat card"
```

---

### Task 3: Update CLAUDE.md and PROGRESS.md

**Files:**
- Modify: `CLAUDE.md`
- Modify: `PROGRESS.md`

**Interfaces:** none (documentation only).

- [ ] **Step 1: Add a "Bugfixes / Corrections" entry to CLAUDE.md**

In `CLAUDE.md`, immediately under the `## Current Status` heading (i.e., as the new first bullet, above the existing `✅ Paper-Approval Proposal Lifecycle` entry), insert:

```markdown
- 🐛 **Bugfix — Ghost "في انتظار الموافقة" status badge** — the prior Paper-Approval Proposal
  Lifecycle change (below) updated the DB, backend authorization, and both Vue pages to the
  2-status model, but missed `resources/js/composables/useProjectStatus.ts`, which still mapped
  the "مقترح" status to the display label "في انتظار الموافقة" (yellow) instead of "مقترح" (blue).
  This produced a third, non-existent status badge in production even though only 2 statuses have
  ever existed in the DB (confirmed via direct query: `project_status` has exactly 2 rows, all 25
  seeded projects reference a valid `current_status_id`). Fixed the label/color map and renamed
  `isPendingApproval()` → `isProposalStatus()` (no digital "approval" concept exists — see the
  paper-approval bullet above). Also removed the same "awaiting approval" phrase from the
  super_admin dashboard's stat card title (`resources/js/pages/Dashboard.vue`), which counted
  "مقترح" projects but labeled them as "awaiting approval". **"في انتظار الموافقة" is not a valid
  system state — it must never appear anywhere in the UI.**
```

- [ ] **Step 2: Add the matching entry to PROGRESS.md**

In `PROGRESS.md`, find the `### Current Development Phase and Status` section (or the nearest changelog-style section documenting the Paper-Approval Proposal Lifecycle change) and add, directly above it:

```markdown
### Bugfixes / Corrections

- **2026-08-24 — Ghost "في انتظار الموافقة" status badge removed.** Root cause:
  `resources/js/composables/useProjectStatus.ts` still labeled the "مقترح" status as
  "في انتظار الموافقة" (yellow), a leftover from before the system moved to paper-based approval.
  Confirmed via direct DB query that no stale data was involved — `project_status` has exactly 2
  rows (`مؤرشف`, `مقترح`) and all 25 seeded projects reference one of them. Backend authorization
  (`Project::canBeModifiedBy()`/`canBeArchivedBy()`, the three project FormRequests,
  `ProjectController::edit()`) and `Projects/Show.vue` were already fully correct for the 2-status/
  paper-approval model — only the frontend label/color map and one dashboard stat card title
  needed fixing. `isPendingApproval()` renamed to `isProposalStatus()` throughout
  `Projects/Index.vue` and `Projects/Show.vue`.
```

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md PROGRESS.md
git commit -m "docs: record ghost pending-approval status bugfix"
```

---

### Task 4: Full verification

**Files:** none modified — this task only runs commands and records their output.

- [ ] **Step 1: Run the full backend suite**

Run: `php artisan test`
Expected: all tests pass (record the exact pass count, e.g. "239/239" — one more than the previous 238/238 baseline from the new Task 1 test).

- [ ] **Step 2: Run the frontend build**

Run: `npm run build`
Expected: build succeeds with no TypeScript/Vue compile errors (this will catch any remaining import of the removed `isPendingApproval` name anywhere not covered above).

- [ ] **Step 3: If either fails, use superpowers:systematic-debugging**

Do not patch symptoms — find the root cause before editing further.

- [ ] **Step 4: Manual confirmation of the fix**

Start the dev server (`npm run dev` in one terminal, ensure `php artisan serve` or the existing local server is running), log in as a `dept_manager` or `super_admin` seeded user, and open `/projects`. Confirm:
- Every status badge reads either "مقترح" (blue) or "مؤرشف" (green) — no yellow badge, no third label anywhere.
- On a "مقترح" row (as `dept_manager`/creator): عرض, تعديل, أرشفة, حذف are all present and clickable.
- On a "مؤرشف" row (as `dept_manager`, not `super_admin`): only عرض is shown.
- Open a "مؤرشف" project's Show page: status badge reads "مؤرشف", no approval-related section is present anywhere, تعديل/حذف/أرشفة buttons are absent (super_admin still sees them, matching the documented bypass).

Record what was observed (screenshot or plain description) for the final report.

- [ ] **Step 5: Commit anything left uncommitted**

```bash
git status
git add -A
git commit -m "chore: final verification pass"
```
(Skip if there is nothing to commit.)

---

## Self-Review

**Spec coverage:**
- Step 1 (locate every source) → Investigation Findings section above, completed before any code was touched.
- Step 2 (fix stale DB rows) → confirmed no-op (Finding #5); explicitly noted, no migration written.
- Step 3 (fix useProjectStatus.ts) → Task 1.
- Step 4 (row-actions gating, frontend + backend enforcement) → confirmed already correct (Findings #6, #7); no new code, existing tests cited.
- Step 5 (Show.vue cleanup) → confirmed already correct (Finding #8); no changes needed.
- Step 6 (tests, full suite, build) → Task 1 Step 1 (Pest test), Task 4 Steps 1–2 (full suite + build).
- Step 7 (docs) → Task 3.
- Final report items 1–6 → Task 4 Step 4 plus the accumulated findings/test output feed directly into the report the executing agent hands back to the user.

**Placeholder scan:** no TBD/TODO, no "add appropriate handling" — every step has literal code or an exact command.

**Type consistency:** `isProposalStatus(name?: string | null): boolean` is the one renamed symbol; verified identical at its definition (Task 1 Step 3) and both call sites (Task 1 Steps 4–5).
