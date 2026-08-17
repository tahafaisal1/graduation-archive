<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Removes the 8 Phase-2 project_status placeholder rows (ids 3-10) left behind by the
     * pre-2026-08-17 seeder on any database that was seeded before the lifecycle was scoped
     * down to 2 statuses. ProjectStatusSeeder's updateOrCreate() only ever touches ids 1-2 and
     * never deletes old rows, so on any DB migrated incrementally (php artisan migrate, not
     * migrate:fresh) those legacy rows would otherwise persist indefinitely with stale English
     * status_name text.
     *
     * Deliberately does NOT touch any `projects.current_status_id` value outside {1,2} — if
     * a project still references a legacy status id, the FK constraint (RESTRICT by default)
     * will make this DELETE fail loudly, surfacing the conflict for a human decision instead
     * of silently reassigning real project data.
     */
    public function up(): void
    {
        DB::table('project_status')->whereNotIn('id', [1, 2])->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: the removed rows were dead Phase-2 placeholders with no
        // behavior depending on their content, so recreating stale English status text on
        // rollback would provide no real value.
    }
};
