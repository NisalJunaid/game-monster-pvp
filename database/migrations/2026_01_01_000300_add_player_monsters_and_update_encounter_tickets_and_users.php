<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounter_tickets', function (Blueprint $table) {
            $table->unsignedInteger('current_hp')->nullable()->after('rolled_level');
            $table->unsignedInteger('max_hp')->nullable()->after('current_hp');
            $table->json('battle_state')->nullable()->after('max_hp');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_starter')->default(false)->after('is_admin');
        });

        Schema::create('player_monsters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('species_id')->constrained('monster_species');
            $table->unsignedInteger('level');
            $table->unsignedInteger('exp')->default(0);
            $table->unsignedInteger('current_hp');
            $table->unsignedInteger('max_hp');
            $table->string('nickname')->nullable();
            $table->boolean('is_in_team')->default(false);
            $table->unsignedTinyInteger('team_slot')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'team_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_monsters');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_starter');
        });

        Schema::table('encounter_tickets', function (Blueprint $table) {
            $table->dropColumn(['current_hp', 'max_hp', 'battle_state']);
        });
    }
};
