<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Rename only if old table exists and new one doesn't
        DB::statement('ALTER TABLE IF EXISTS type_effectiveness RENAME TO type_effectivenesses');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE IF EXISTS type_effectiveness RENAME TO type_effectivenesses');
    }
};

