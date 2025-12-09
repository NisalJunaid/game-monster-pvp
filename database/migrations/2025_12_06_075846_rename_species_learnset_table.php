<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('species_learnsets') || ! Schema::hasTable('species_learnset')) {
            return;
        }

        Schema::rename('species_learnset', 'species_learnsets');
    }

    public function down(): void
    {
        if (Schema::hasTable('species_learnset') || ! Schema::hasTable('species_learnsets')) {
            return;
        }

        Schema::rename('species_learnsets', 'species_learnset');
    }
};

