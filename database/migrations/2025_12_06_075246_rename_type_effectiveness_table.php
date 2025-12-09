<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('type_effectivenesses') || ! Schema::hasTable('type_effectiveness')) {
            return;
        }

        Schema::rename('type_effectiveness', 'type_effectivenesses');
    }

    public function down(): void
    {
        if (Schema::hasTable('type_effectiveness') || ! Schema::hasTable('type_effectivenesses')) {
            return;
        }

        Schema::rename('type_effectivenesses', 'type_effectiveness');
    }
};

