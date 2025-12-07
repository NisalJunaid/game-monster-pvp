<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BattleController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MonsterController;
use App\Http\Controllers\PvpQueueController;
use App\Http\Controllers\WildBattleController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/monsters', [MonsterController::class, 'index']);
Route::post('/monsters', [MonsterController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/battles/challenge', [BattleController::class, 'challenge']);
    Route::post('/battles/{battle}/act', [BattleController::class, 'act']);
    Route::get('/battles/{battle}', [BattleController::class, 'show']);

    Route::post('/location/update', [LocationController::class, 'update']);
    Route::get('/encounters/current', [EncounterController::class, 'current']);
    Route::post('/encounters/{ticket}/resolve-capture', [EncounterController::class, 'resolveCapture']);
    Route::get('/encounters/{ticket}/battle', [WildBattleController::class, 'show']);
    Route::post('/encounters/{ticket}/battle/move', [WildBattleController::class, 'move']);
    Route::post('/encounters/{ticket}/battle/switch', [WildBattleController::class, 'switchActive']);
    Route::post('/encounters/{ticket}/battle/tame', [WildBattleController::class, 'tame']);
    Route::post('/encounters/{ticket}/battle/run', [WildBattleController::class, 'run']);

    Route::post('/pvp/queue', [PvpQueueController::class, 'queue']);
    Route::post('/pvp/dequeue', [PvpQueueController::class, 'dequeue']);
});
