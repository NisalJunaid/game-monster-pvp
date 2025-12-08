<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('type_effectivenesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attack_type_id')->constrained('types')->cascadeOnDelete();
            $table->foreignId('defend_type_id')->constrained('types')->cascadeOnDelete();
            $table->decimal('multiplier', 3, 2)->default(1.00);
            $table->timestamps();

            $table->unique(['attack_type_id', 'defend_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_effectiveness');
        Schema::dropIfExists('type_effectivenesses');
    }
};
