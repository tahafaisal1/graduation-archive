<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('academic_year', 9);
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('specialization_id')->constrained('specializations');
            $table->foreignId('supervisor_id')->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('draft_file_path')->nullable();
            $table->unsignedTinyInteger('status_id');
            $table->foreign('status_id')->references('id')->on('project_status');
            // Points at the NEW projects table (created in a later migration in
            // this same task) — "a proposal building on a previously finished
            // project", not a self-reference to another proposal.
            $table->unsignedBigInteger('based_on_project_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
