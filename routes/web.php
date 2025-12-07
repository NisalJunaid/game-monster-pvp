<?php

use App\Http\Controllers\Admin\AdminZoneController;
use App\Http\Controllers\Admin\AdminZoneSpawnController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BattleController as WebBattleController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EncounterController as WebEncounterController;
use App\Http\Controllers\Web\PvpController as WebPvpController;
use App\Events\BattleUpdated;
use App\Models\Battle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

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

Route::get('/', fn () => view('home'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/encounters', [WebEncounterController::class, 'index'])->name('encounters.index');
    Route::post('/encounters/location', [WebEncounterController::class, 'update'])->name('encounters.update');
    Route::post('/encounters/{ticket}/resolve', [WebEncounterController::class, 'resolve'])->name('encounters.resolve');

    Route::get('/pvp', [WebPvpController::class, 'index'])->name('pvp.index');
    Route::post('/pvp/queue', [WebPvpController::class, 'queue'])->name('pvp.queue');
    Route::delete('/pvp/queue', [WebPvpController::class, 'dequeue'])->name('pvp.dequeue');
    Route::get('/pvp/fragment', [WebPvpController::class, 'fragment'])->name('pvp.fragment');
    Route::get('/pvp/status', [WebPvpController::class, 'status'])->name('pvp.status');
    Route::get('/pvp/battle-fragment/{battle}', [WebPvpController::class, 'battleFragment'])->name('pvp.battle_fragment');

    Route::get('/battles/{battle}', [WebBattleController::class, 'show'])->name('battles.show');
    Route::get('/battles/{battle}/state', [WebBattleController::class, 'state'])->name('battles.state');
    Route::post('/battles/{battle}', [WebBattleController::class, 'act'])->name('battles.act');

    Route::middleware(function ($request, $next) {
        if (! config('app.debug')) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    })->group(function () {
        Route::get('/debug/broadcasting', function (Request $request) {
            return response()->json([
                'app_url' => config('app.url'),
                'session_domain' => config('session.domain'),
                'session_secure' => config('session.secure'),
                'sanctum_stateful' => config('sanctum.stateful') ?? null,
                'broadcasting_auth_route' => Route::has('broadcasting.auth'),
                'broadcasting_default' => config('broadcasting.default'),
                'pusher' => [
                    'host' => config('broadcasting.connections.pusher.options.host'),
                    'port' => config('broadcasting.connections.pusher.options.port'),
                    'scheme' => config('broadcasting.connections.pusher.options.scheme'),
                ],
            ]);
        })->name('debug.broadcasting');

        Route::post('/debug/broadcast-test/{battle}', function (Request $request, Battle $battle) {
            if (! in_array($request->user()->id, [$battle->player1_id, $battle->player2_id], true)) {
                abort(Response::HTTP_FORBIDDEN, 'You are not part of this battle.');
            }

            $state = $battle->meta_json;

            broadcast(new BattleUpdated(
                battleId: $battle->id,
                state: $state,
                status: $battle->status,
                nextActorId: $state['next_actor_id'] ?? null,
                winnerUserId: $battle->winner_user_id,
            ));

            return response()->json(['ok' => true]);
        })->name('debug.broadcast_test');
    });
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/zones/map', [AdminZoneController::class, 'map'])->name('zones.map');
    Route::post('/zones', [AdminZoneController::class, 'store'])->name('zones.store');
    Route::put('/zones/{zone}', [AdminZoneController::class, 'update'])->name('zones.update');

    Route::get('/zones/{zone}/spawns', [AdminZoneSpawnController::class, 'index'])->name('zones.spawns.index');
    Route::post('/zones/{zone}/spawns', [AdminZoneSpawnController::class, 'store'])->name('zones.spawns.store');
    Route::put('/zones/{zone}/spawns/{spawnEntry}', [AdminZoneSpawnController::class, 'update'])->name('zones.spawns.update');
    Route::delete('/zones/{zone}/spawns/{spawnEntry}', [AdminZoneSpawnController::class, 'destroy'])->name('zones.spawns.destroy');
    Route::post('/zones/{zone}/spawns/generate', [AdminZoneSpawnController::class, 'generate'])->name('zones.spawns.generate');
});
