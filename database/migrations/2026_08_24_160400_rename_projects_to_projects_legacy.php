<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('projects', 'projects_legacy');
    }

    public function down(): void
    {
        Schema::rename('projects_legacy', 'projects');
    }
};
