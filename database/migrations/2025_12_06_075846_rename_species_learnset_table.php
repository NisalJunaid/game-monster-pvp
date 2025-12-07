<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE IF EXISTS species_learnset RENAME TO species_learnsets');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE IF EXISTS species_learnsets RENAME TO species_learnset');
    }
};

