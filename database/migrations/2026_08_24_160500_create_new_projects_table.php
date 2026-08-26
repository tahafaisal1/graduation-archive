<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete, not cascade: a hard-deleted proposal must not
            // silently destroy a graded project (and its examiners/evaluations,
            // which cascade from projects.id) — force an explicit decision.
            $table->foreignId('proposal_id')->unique()->constrained('proposals')->restrictOnDelete();
            $table->unsignedTinyInteger('status_id');
            $table->foreign('status_id')->references('id')->on('project_lifecycle_status');
            $table->decimal('final_score', 5, 2)->nullable();
            // Nullable: the 20 rows backfilled from legacy data by the data
            // migration command have no real "who clicked" actor.
            $table->foreignId('instantiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('instantiated_at')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
