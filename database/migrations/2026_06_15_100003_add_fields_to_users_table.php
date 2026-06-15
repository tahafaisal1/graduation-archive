<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->unique()->after('name');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('registration_number');
            $table->boolean('is_active')->default(true)->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique(['registration_number']);
            $table->dropColumn(['registration_number', 'department_id', 'is_active']);
        });
    }
};
