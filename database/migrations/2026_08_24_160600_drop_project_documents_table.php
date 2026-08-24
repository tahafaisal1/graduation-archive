<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Irreversible: project_documents was empty (0 rows) in the live DB at
    // the time of this migration — confirmed via audit — so no data is lost,
    // but down() recreates an empty table, not the original data (there was
    // none).
    public function up(): void
    {
        Schema::dropIfExists('project_documents');
    }

    public function down(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('file_path');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_final')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }
};
