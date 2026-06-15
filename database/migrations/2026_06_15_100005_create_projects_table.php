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
            $table->string('project_title');
            $table->text('description')->nullable();
            $table->string('academic_year', 9);
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('specialization_id')->constrained('specializations');
            $table->foreignId('supervisor_id')->constrained('users');
            $table->unsignedTinyInteger('current_status_id');
            $table->foreign('current_status_id')->references('id')->on('project_status');
            $table->foreignId('based_on_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('draft_file_path')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
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
