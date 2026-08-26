<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_approved_by');
            $table->dropColumn('supervisor_approved_at');
            $table->dropConstrainedForeignId('department_approved_by');
            $table->dropColumn('department_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('supervisor_approved_by')->nullable()->after('current_status_id')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
            $table->foreignId('department_approved_by')->nullable()->after('supervisor_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('department_approved_at')->nullable()->after('department_approved_by');
        });
    }
};
