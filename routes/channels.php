<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Battle;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('battles.{id}', function ($user, $id) {
    return Battle::query()
        ->where('id', $id)
        ->where(function ($query) use ($user) {
            $query->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
        })
        ->exists();
});
