<?php

use App\Http\Controllers\Admin\AdminZoneController;
use App\Http\Controllers\Admin\AdminZoneSpawnController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/zones/map', [AdminZoneController::class, 'map'])->name('zones.map');
    Route::post('/zones', [AdminZoneController::class, 'store'])->name('zones.store');
    Route::put('/zones/{zone}', [AdminZoneController::class, 'update'])->name('zones.update');

    Route::get('/zones/{zone}/spawns', [AdminZoneSpawnController::class, 'index'])->name('zones.spawns.index');
    Route::post('/zones/{zone}/spawns', [AdminZoneSpawnController::class, 'store'])->name('zones.spawns.store');
    Route::put('/zones/{zone}/spawns/{spawnEntry}', [AdminZoneSpawnController::class, 'update'])->name('zones.spawns.update');
    Route::delete('/zones/{zone}/spawns/{spawnEntry}', [AdminZoneSpawnController::class, 'destroy'])->name('zones.spawns.destroy');
});
