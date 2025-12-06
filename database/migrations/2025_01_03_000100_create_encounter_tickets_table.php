<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('zone_id')->constrained();
            $table->foreignId('species_id')->constrained('monster_species');
            $table->unsignedInteger('rolled_level');
            $table->unsignedBigInteger('seed');
            $table->string('status')->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_tickets');
    }
};
