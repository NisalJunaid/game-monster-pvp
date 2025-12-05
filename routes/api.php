<?php

use App\Http\Controllers\BattleController;
use App\Http\Controllers\MonsterController;
use Illuminate\Support\Facades\Route;

Route::get('/monsters', [MonsterController::class, 'index']);
Route::post('/monsters', [MonsterController::class, 'store']);

Route::post('/battles', [BattleController::class, 'store']);
