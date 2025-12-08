<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_learnsets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->constrained('monster_species')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage_number');
            $table->foreignId('move_id')->constrained('moves')->cascadeOnDelete();
            $table->unsignedTinyInteger('learn_level')->nullable();
            $table->string('learn_method');
            $table->timestamps();

            $table->unique(['species_id', 'stage_number', 'move_id', 'learn_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_learnset');
        Schema::dropIfExists('species_learnsets');
    }
};
