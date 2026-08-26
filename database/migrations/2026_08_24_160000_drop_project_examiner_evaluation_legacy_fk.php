<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Drops the FK pointing at the old (soon-to-be-renamed) `projects` table.
    // The columns stay as plain unsignedBigInteger — their VALUES still point
    // at old project ids until the data-migration command (Task 3) updates
    // them and re-adds the FK against the new `projects` table.
    public function up(): void
    {
        Schema::table('project_examiners', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
    }

    // Irreversible in isolation: by the time down() would run (in reverse
    // migration order), 'projects' has already been renamed back from
    // 'projects_legacy' by migration 2026_08_24_160400's down(), so this is
    // safe — re-adding the FK against 'projects' is correct at that point.
    public function down(): void
    {
        Schema::table('project_examiners', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
